<?php
// api/home.php — ホームページのブロック (バナー・割引・広告・並び順). Owner: Lin
//   GET                  anyone → 表示 ON + 期間の中のブロックだけ (sort_order 順)
//   GET    ?all=1        admin  → 全部
//   POST                 admin  → 割引 (promo) / 広告 (ad) を追加
//   PUT    ?id=          admin  → 変更 (送られた項目だけ)
//   DELETE ?id=          admin  → 割引 / 広告を削除 (それ以外は表示 OFF にするだけ)
//   PUT    ?action=order admin  → 並び順を保存 { ids: [3, 1, 2, …] }
//   POST   ?action=reset admin  → 基本デザインに戻す
//   POST   ?action=upload admin → 画像 (multipart, name="image") → { url }
// 割引ブロックは「お知らせ」です。カートの金額は変わりません。

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/api.php';
require __DIR__ . '/../includes/auth.php';   // require_admin() → 401 / 403

$method = require_method('GET', 'POST', 'PUT', 'DELETE');

// 基本デザイン。割引・広告は見本なので表示 OFF (お店が本当の内容を書いてから ON にする)
function seed_blocks(PDO $pdo): void {
    $rows = [
        ['hero', "自分だけの一杯を、\n好きな辛さで。", '30種類以上の具材とスープから選べる、できたての麻辣湯。', null, 'メニューを見る', 'menu.php', 1],
        ['notices', 'お知らせ', null, null, null, null, 1],
        ['promo', 'トッピング1品無料', 'ご来店のお客さまに、お好きなトッピングを1品サービス。', 'SAMPLE', 'メニューを見る', 'menu.php', 0],
        ['promo', 'お友だち紹介キャンペーン', 'ファミリー&フレンズでレシピをシェアしよう。', 'SAMPLE', 'くわしく見る', 'friends.php', 0],
        ['custom', '一から、自分で作る', '春雨とスープをベースに、好きな具材を好きなだけ。', null, '自分で作る', 'customize.php', 1],
        ['ad', 'ランチタイム限定セット', '平日11:00〜14:00。ここに広告やキャンペーンの画像を入れられます。', null, 'メニューを見る', 'menu.php', 0],
    ];
    $stmt = $pdo->prepare(
        'INSERT INTO home_blocks (type, title, body, badge, link_label, link_url, is_visible, sort_order)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    foreach ($rows as $i => $r) {
        $stmt->execute([$r[0], $r[1], $r[2], $r[3], $r[4], $r[5], $r[6], $i + 1]);
    }
}

function block_json(array $r): array {
    return [
        'id'         => (int) $r['id'],
        'type'       => $r['type'],
        'title'      => $r['title'],
        'body'       => $r['body'],
        'badge'      => $r['badge'],
        'image_url'  => $r['image_url'],
        'link_label' => $r['link_label'],
        'link_url'   => $r['link_url'],
        'sort_order' => (int) $r['sort_order'],
        'is_visible' => (bool) $r['is_visible'],
        'start_date' => $r['start_date'],
        'end_date'   => $r['end_date'],
    ];
}

// ========== GET ==========
if ($method === 'GET') {
    $all = isset($_GET['all']);
    if ($all) {
        require_admin();
    }
    if ((int) $pdo->query('SELECT COUNT(*) FROM home_blocks')->fetchColumn() === 0) {
        seed_blocks($pdo);
    }
    $sql = 'SELECT * FROM home_blocks'
         . ($all ? '' : ' WHERE is_visible = 1
                          AND (start_date IS NULL OR start_date <= CURDATE())
                          AND (end_date   IS NULL OR end_date   >= CURDATE())')
         . ' ORDER BY sort_order, id';
    send_json(array_map('block_json', $pdo->query($sql)->fetchAll()));
}

// ========== ここから下は admin だけ ==========
require_admin();
$action = $_GET['action'] ?? '';
$id     = (int) ($_GET['id'] ?? 0);

// ---------- 画像アップロード ----------
// 拡張子は名前ではなく中身 (getimagesize) で決める。.php などは保存されない
if ($action === 'upload' && $method === 'POST') {
    $file = $_FILES['image'] ?? null;
    if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        send_error('画像をアップロードできませんでした (2MB まで)', 400);
    }
    if ($file['size'] > 2 * 1024 * 1024) {
        send_error('画像は 2MB までです', 400);
    }
    $info = @getimagesize($file['tmp_name']);
    $exts = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
    if (!$info || !isset($exts[$info[2]])) {
        send_error('JPG / PNG / WebP の画像を選んでください', 400);
    }
    $dir = __DIR__ . '/../assets/uploads';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $name = bin2hex(random_bytes(12)) . '.' . $exts[$info[2]];
    if (!move_uploaded_file($file['tmp_name'], "$dir/$name")) {
        send_error('画像を保存できませんでした', 500);
    }
    send_json(['url' => "../assets/uploads/$name"], 201);
}

$in = read_json();

// ---------- 基本デザインに戻す ----------
if ($action === 'reset' && $method === 'POST') {
    $pdo->beginTransaction();
    $pdo->exec('DELETE FROM home_blocks');
    seed_blocks($pdo);
    $pdo->commit();
    send_json(['reset' => true]);
}

// ---------- 並び順 ----------
if ($action === 'order' && $method === 'PUT') {
    if (!isset($in['ids']) || !is_array($in['ids'])) {
        send_error('Missing field: ids', 400);
    }
    $ids = array_map('intval', $in['ids']);
    $now = array_map('intval', $pdo->query('SELECT id FROM home_blocks')->fetchAll(PDO::FETCH_COLUMN));
    sort($now);
    $sent = $ids;
    sort($sent);
    if ($sent !== $now) {                     // 別のタブで追加・削除された
        send_error('ページを読み込み直してください', 409);
    }
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('UPDATE home_blocks SET sort_order = ? WHERE id = ?');
    foreach ($ids as $i => $blockId) {
        $stmt->execute([$i + 1, $blockId]);
    }
    $pdo->commit();
    send_json(['updated' => true]);
}

// 日付は 'YYYY-MM-DD' か 空 (= NULL)
function clean_date($v) {
    if ($v === null || $v === '') {
        return null;
    }
    if (!is_string($v)) {
        send_error('Date must be YYYY-MM-DD', 400);
    }
    $d = DateTime::createFromFormat('Y-m-d', $v);
    if (!$d || $d->format('Y-m-d') !== $v) {
        send_error('Date must be YYYY-MM-DD', 400);
    }
    return $v;
}

// リンク・画像の URL: http(s) か このサイトの中だけ (javascript: などは入れない)
function clean_url($v) {
    $v = trim(is_scalar($v) ? (string) $v : '?');
    if ($v === '') {
        return null;
    }
    // "//example.com" は別のサイトへ行くので、サイトの中のリンクとして認めない
    if (str_starts_with($v, '//') || !preg_match('#^(https?://[^\s"\'<>]+|[A-Za-z0-9_\-./?=&%\#]+)$#', $v)) {
        send_error('URL が正しくありません (http:// から始まる URL か menu.php のように書いてください)', 400);
    }
    return $v;
}

// 文字: 長さを見て、空は NULL
function clean_text($v, int $max) {
    if ($v !== null && !is_scalar($v)) {
        send_error('文字で入力してください', 400);
    }
    $v = trim((string) $v);
    if (mb_strlen($v) > $max) {
        send_error("{$max}文字以内で入力してください", 400);
    }
    return $v === '' ? null : $v;
}

// 送られた項目だけ、きれいにして返す
function clean_fields(array $in): array {
    $out = [];
    $rules = [
        'title'      => fn($v) => clean_text($v, 100),
        'body'       => fn($v) => clean_text($v, 1000),
        'badge'      => fn($v) => clean_text($v, 30),
        'link_label' => fn($v) => clean_text($v, 30),
        'image_url'  => fn($v) => clean_url($v),
        'link_url'   => fn($v) => clean_url($v),
        'start_date' => fn($v) => clean_date($v),
        'end_date'   => fn($v) => clean_date($v),
        'is_visible' => fn($v) => $v ? 1 : 0,
    ];
    foreach ($rules as $key => $rule) {
        if (array_key_exists($key, $in)) {
            $out[$key] = $rule($in[$key]);
        }
    }
    return $out;
}

// 「いつから」が「いつまで」より後だと、そのブロックは ずっと表示されない
function check_period($start, $end): void {
    if ($start !== null && $end !== null && $start > $end) {
        send_error('「いつから」は「いつまで」より前の日にしてください', 400);
    }
}

// ---------- 追加 (割引・広告だけ。ほかは1つずつ最初からある) ----------
if ($method === 'POST') {
    $type = $in['type'] ?? '';
    if ($type !== 'promo' && $type !== 'ad') {
        send_error('Type must be promo or ad', 400);
    }
    $f = clean_fields($in);
    check_period($f['start_date'] ?? null, $f['end_date'] ?? null);
    $order = (int) $pdo->query('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM home_blocks')->fetchColumn();
    $stmt = $pdo->prepare(
        'INSERT INTO home_blocks (type, title, body, badge, image_url, link_label, link_url, start_date, end_date, is_visible, sort_order)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $type, $f['title'] ?? null, $f['body'] ?? null, $f['badge'] ?? null, $f['image_url'] ?? null,
        $f['link_label'] ?? null, $f['link_url'] ?? null, $f['start_date'] ?? null, $f['end_date'] ?? null,
        $f['is_visible'] ?? 0, $order,
    ]);
    send_json(['id' => (int) $pdo->lastInsertId()], 201);
}

// ---------- 1つのブロック ----------
$stmt = $pdo->prepare('SELECT type, start_date, end_date FROM home_blocks WHERE id = ?');
$stmt->execute([$id]);
$block = $stmt->fetch();
if ($block === false) {
    send_error('Not found', 404);
}
$type = $block['type'];

if ($method === 'PUT') {
    $f = clean_fields($in);
    if (count($f) === 0) {
        send_error('Nothing to change', 400);
    }
    // 片方だけ送られたときは、今の値と比べる
    check_period(
        array_key_exists('start_date', $f) ? $f['start_date'] : $block['start_date'],
        array_key_exists('end_date', $f) ? $f['end_date'] : $block['end_date']
    );
    $sets = implode(', ', array_map(fn($k) => "$k = ?", array_keys($f)));   // $k は clean_fields のキーだけ
    $stmt = $pdo->prepare("UPDATE home_blocks SET $sets WHERE id = ?");
    $stmt->execute([...array_values($f), $id]);
    send_json(['id' => $id, 'updated' => true]);
}

if ($method === 'DELETE') {
    if ($type !== 'promo' && $type !== 'ad') {
        send_error('このブロックは削除できません。表示を OFF にしてください', 400);
    }
    $pdo->prepare('DELETE FROM home_blocks WHERE id = ?')->execute([$id]);
    send_json(['id' => $id, 'deleted' => true]);
}
