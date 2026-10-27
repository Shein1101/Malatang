<?php
// api/options.php — スープと辛さ. Owner: Myat
//   GET                          anyone → { soups, spice_levels }  (販売中のものだけ)
//   GET  ?all=1                  admin  → 止めているものも全部 (管理画面用)
//   POST ?type=spice             admin  → 辛さを追加  { level, name, extra_price }
//   PUT  ?type=spice&id=3        admin  → 辛さを変更  { name, extra_price, is_available }
// 辛さは削除しない (昔の注文が使っているので)。止めたいときは is_available = false。

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/api.php';
require __DIR__ . '/../includes/auth.php';   // require_admin() → 401 / 403

$method = require_method('GET', 'POST', 'PUT');


// ========== GET ==========
if ($method === 'GET') {
    $all = isset($_GET['all']);              // ?all=1 → 止めているものも見せる
    if ($all) {
        require_admin();
    }

    // 1. スープ
    $sql = 'SELECT id, name, description, price, is_available FROM soups'
         . ($all ? '' : ' WHERE is_available = 1')
         . ' ORDER BY sort_order, id';
    $soups = [];
    foreach ($pdo->query($sql)->fetchAll() as $r) {
        $soups[] = [
            'id'           => (int) $r['id'],
            'name'         => $r['name'],
            'description'  => $r['description'],
            'price'        => (int) $r['price'],
            'is_available' => (bool) $r['is_available'],
        ];
    }

    // 2. 辛さ (1 → 5 の順)
    $sql = 'SELECT id, name, level, extra_price, is_available FROM spice_levels'
         . ($all ? '' : ' WHERE is_available = 1')
         . ' ORDER BY level';
    $spice_levels = [];
    foreach ($pdo->query($sql)->fetchAll() as $r) {
        $spice_levels[] = [
            'id'           => (int) $r['id'],
            'name'         => $r['name'],          // 説明 (例: 辛くない)
            'level'        => (int) $r['level'],   // 番号 (例: 1)
            'extra_price'  => (int) $r['extra_price'],
            'is_available' => (bool) $r['is_available'],
        ];
    }

    send_json(['soups' => $soups, 'spice_levels' => $spice_levels]);
}

// ========== ここから下は admin だけ ==========
require_admin();
if (($_GET['type'] ?? '') !== 'spice') {
    send_error('type=spice only (soups: coming next)', 400);
}
$in = read_json();

// 0 以上の整数かチェックする小さな関数
function check_int($value, string $label): int {
    $n = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    if ($n === false) {
        send_error("$label must be a number 0 or more", 400);
    }
    return $n;
}

// ========== POST: 辛さを追加 ==========
if ($method === 'POST') {
    require_fields($in, ['level', 'name']);
    $level = check_int($in['level'], 'level');
    $extra = check_int($in['extra_price'] ?? 0, 'extra_price');

    // 同じ番号がもうあるか
    $check = $pdo->prepare('SELECT id FROM spice_levels WHERE level = ?');
    $check->execute([$level]);
    if ($check->fetch()) {
        send_error("Level $level already exists", 400);
    }

    $stmt = $pdo->prepare('INSERT INTO spice_levels (name, level, extra_price) VALUES (?, ?, ?)');
    $stmt->execute([trim($in['name']), $level, $extra]);
    send_json(['id' => (int) $pdo->lastInsertId()], 201);
}

// ========== PUT: 辛さを変更 (?id=3) ==========
if ($method === 'PUT') {
    $id = (int) ($_GET['id'] ?? 0);
    $find = $pdo->prepare('SELECT id FROM spice_levels WHERE id = ?');
    $find->execute([$id]);
    if (!$find->fetch()) {
        send_error('Spice level not found', 404);
    }

    $sets = [];
    $values = [];
    if (isset($in['name']))         { $sets[] = 'name = ?';         $values[] = trim($in['name']); }
    if (isset($in['extra_price']))  { $sets[] = 'extra_price = ?';  $values[] = check_int($in['extra_price'], 'extra_price'); }
    if (isset($in['is_available'])) { $sets[] = 'is_available = ?'; $values[] = $in['is_available'] ? 1 : 0; }
    if (count($sets) === 0) {
        send_error('Nothing to change', 400);
    }

    // 追加料金を変えたら price_history に残る (DB の trigger)。だれが変えたかを渡す
    $pdo->prepare('SET @current_user_id = ?')->execute([$_SESSION['user_id'] ?? null]);

    $values[] = $id;
    $pdo->prepare('UPDATE spice_levels SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($values);
    send_json(['id' => $id, 'updated' => true]);
}
