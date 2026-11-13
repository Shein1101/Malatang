<?php
// api/store.php — 店舗ページ (お店の情報・写真・お知らせ). Owner: Myat (FEAT-10)
//   GET                                   anyone → { store, photos, announcements }  (公開中のものだけ)
//   GET    ?all=1                         admin  → 非表示の写真・下書きのお知らせも全部
//   PUT                                   admin  → お店の情報を変更 (店名・説明・住所・電話・営業時間・営業中)
//   POST   ?type=photo | announcement     admin  → 追加
//   PUT    ?type=photo | announcement &id admin  → 変更 (表示 ON/OFF など)
//   DELETE ?type=photo | announcement &id admin  → 削除 (注文とつながっていないので本当に消す)

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/api.php';
require __DIR__ . '/../includes/auth.php';   // require_admin() → 401 / 403

$method  = require_method('GET', 'POST', 'PUT', 'DELETE');

// ========== GET ==========
if ($method === 'GET') {
    $all = isset($_GET['all']);
    if ($all) {
        require_admin();
    }

    // 1. お店の情報 (1行だけ)
    $s = $pdo->query('SELECT store_name, description, address, phone, opening_hours, is_open, banner_url
                      FROM store_settings WHERE id = 1')->fetch();
    $store = $s ? [
        'store_name'    => $s['store_name'],
        'description'   => $s['description'],
        'address'       => $s['address'],
        'phone'         => $s['phone'],
        'opening_hours' => $s['opening_hours'],
        'is_open'       => (bool) $s['is_open'],
        'banner_url'    => $s['banner_url'],
    ] : null;

    // 2. 写真 (お客さん: 表示 ON だけ)
    $sql = 'SELECT id, image_url, caption, sort_order, is_visible FROM store_photos'
         . ($all ? '' : ' WHERE is_visible = 1')
         . ' ORDER BY sort_order, id';
    $photos = [];
    foreach ($pdo->query($sql)->fetchAll() as $r) {
        $photos[] = [
            'id'         => (int) $r['id'],
            'image_url'  => $r['image_url'],
            'caption'    => $r['caption'],
            'sort_order' => (int) $r['sort_order'],
            'is_visible' => (bool) $r['is_visible'],
        ];
    }

    // 3. お知らせ (お客さん: 公開中 かつ 今日が期間の中)
    $sql = 'SELECT id, title, body, is_published, start_date, end_date FROM announcements'
         . ($all ? '' : ' WHERE is_published = 1
                          AND (start_date IS NULL OR start_date <= CURDATE())
                          AND (end_date   IS NULL OR end_date   >= CURDATE())')
         . ' ORDER BY id DESC';
    $news = [];
    foreach ($pdo->query($sql)->fetchAll() as $r) {
        $news[] = [
            'id'           => (int) $r['id'],
            'title'        => $r['title'],
            'body'         => $r['body'],
            'is_published' => (bool) $r['is_published'],
            'start_date'   => $r['start_date'],
            'end_date'     => $r['end_date'],
        ];
    }

    send_json(['store' => $store, 'photos' => $photos, 'announcements' => $news]);
}

// ========== ここから下は admin だけ ==========
require_admin();
$in   = read_json();
$type = $_GET['type'] ?? '';          // '' = お店の情報 / 'photo' / 'announcement'
$id   = (int) ($_GET['id'] ?? 0);

// 日付は 'YYYY-MM-DD' か 空 (= NULL)
function date_or_null($v) {
    if ($v === null || $v === '') {
        return null;
    }
    $d = DateTime::createFromFormat('Y-m-d', $v);
    if (!$d || $d->format('Y-m-d') !== $v) {
        send_error('Date must be YYYY-MM-DD', 400);
    }
    return $v;
}

// 送られた項目だけで UPDATE する (ingredients / menus と同じやり方)
// $fields = [ 'JSONの名前' => 'bool' / 'int' / 'date' / 'text' ]
function update_fields(PDO $pdo, string $table, int $id, array $in, array $fields): void {
    $sets = [];
    $values = [];
    foreach ($fields as $key => $kind) {
        if (!array_key_exists($key, $in)) {
            continue;
        }
        $v = $in[$key];
        if ($kind === 'bool') $v = $v ? 1 : 0;
        if ($kind === 'int')  $v = (int) $v;
        if ($kind === 'date') $v = date_or_null($v);
        if ($kind === 'text') $v = ($v === null) ? null : trim((string) $v);
        $sets[] = "$key = ?";
        $values[] = $v;
    }
    if (count($sets) === 0) {
        send_error('Nothing to change', 400);
    }
    $values[] = $id;
    $stmt = $pdo->prepare("UPDATE $table SET " . implode(', ', $sets) . ' WHERE id = ?');
    $stmt->execute($values);
}

// 写真・お知らせが本当にあるか
function must_exist(PDO $pdo, string $table, int $id): void {
    $stmt = $pdo->prepare("SELECT id FROM $table WHERE id = ?");
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        send_error('Not found', 404);
    }
}

// ---------- お店の情報 (PUT のみ) ----------
if ($type === '') {
    if ($method !== 'PUT') {
        send_error('Use type=photo or type=announcement', 400);
    }
    if (array_key_exists('store_name', $in) && trim((string) $in['store_name']) === '') {
        send_error('Store name is required', 400);
    }
    update_fields($pdo, 'store_settings', 1, $in, [
        'store_name' => 'text', 'description' => 'text', 'address' => 'text', 'phone' => 'text',
        'opening_hours' => 'text', 'is_open' => 'bool', 'banner_url' => 'text',
    ]);
    send_json(['updated' => true]);
}

// ---------- 写真 ----------
if ($type === 'photo') {
    $fields = ['image_url' => 'text', 'caption' => 'text', 'sort_order' => 'int', 'is_visible' => 'bool'];

    if ($method === 'POST') {
        require_fields($in, ['image_url']);
        $stmt = $pdo->prepare('INSERT INTO store_photos (image_url, caption, sort_order) VALUES (?, ?, ?)');
        $stmt->execute([trim($in['image_url']), $in['caption'] ?? null, (int) ($in['sort_order'] ?? 0)]);
        send_json(['id' => (int) $pdo->lastInsertId()], 201);
    }
    must_exist($pdo, 'store_photos', $id);
    if ($method === 'PUT') {
        update_fields($pdo, 'store_photos', $id, $in, $fields);
        send_json(['id' => $id, 'updated' => true]);
    }
    if ($method === 'DELETE') {
        $pdo->prepare('DELETE FROM store_photos WHERE id = ?')->execute([$id]);
        send_json(['id' => $id, 'deleted' => true]);
    }
}

// ---------- お知らせ ----------
if ($type === 'announcement') {
    $fields = ['title' => 'text', 'body' => 'text', 'is_published' => 'bool', 'start_date' => 'date', 'end_date' => 'date'];

    if ($method === 'POST') {
        require_fields($in, ['title']);
        $stmt = $pdo->prepare(
            'INSERT INTO announcements (title, body, is_published, start_date, end_date, created_by)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            trim($in['title']),
            $in['body'] ?? null,
            !empty($in['is_published']) ? 1 : 0,
            date_or_null($in['start_date'] ?? null),
            date_or_null($in['end_date'] ?? null),
            $_SESSION['user_id'] ?? null,        // だれが書いたか
        ]);
        send_json(['id' => (int) $pdo->lastInsertId()], 201);
    }
    must_exist($pdo, 'announcements', $id);
    if ($method === 'PUT') {
        update_fields($pdo, 'announcements', $id, $in, $fields);
        send_json(['id' => $id, 'updated' => true]);
    }
    if ($method === 'DELETE') {
        $pdo->prepare('DELETE FROM announcements WHERE id = ?')->execute([$id]);
        send_json(['id' => $id, 'deleted' => true]);
    }
}

send_error('Unknown type', 400);
