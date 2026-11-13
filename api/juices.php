<?php
// api/juices.php — ジュース (飲み物). Owner: Myat
//   GET                 anyone → 販売中のジュース           [{ id, name, description, price, image_url }]
//   GET  ?all=1         admin  → 止めているものも全部 (管理画面用)
//   POST                admin  → 追加  { name, description, price, image_url }
//   PUT  ?id=3          admin  → 変更  { name, description, price, image_url, is_available, sort_order }
//   DELETE ?id=3        admin  → 削除 (注文とつながっていないので本当に消せる)
// 今は「メニューに表示するだけ」。カート・注文にはまだ入れていません。

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/api.php';
require __DIR__ . '/../includes/auth.php';   // require_admin() → 401 / 403

$method = require_method('GET', 'POST', 'PUT', 'DELETE');

function juice_json(array $r): array {
    return [
        'id'           => (int) $r['id'],
        'name'         => $r['name'],
        'description'  => $r['description'],
        'price'        => (int) $r['price'],
        'image_url'    => $r['image_url'],
        'is_available' => (bool) $r['is_available'],
        'sort_order'   => (int) $r['sort_order'],
    ];
}

// ========== GET ==========
if ($method === 'GET') {
    $all = isset($_GET['all']);       // ?all=1 → 止めているものも見せる
    if ($all) {
        require_admin();
    }
    $sql = 'SELECT id, name, description, price, image_url, is_available, sort_order FROM juices'
         . ($all ? '' : ' WHERE is_available = 1')
         . ' ORDER BY sort_order, id';
    send_json(array_map('juice_json', $pdo->query($sql)->fetchAll()));
}

// ========== ここから下は admin だけ ==========
require_admin();
$in = read_json();
$id = (int) ($_GET['id'] ?? 0);

// 0 以上の整数
function juice_int($value, string $label): int {
    $n = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    if ($n === false) {
        send_error("$label must be a number 0 or more", 400);
    }
    return $n;
}

// 名前・説明をきれいにする (空の説明は NULL)
function juice_text($v, int $max, string $label, bool $required): ?string {
    $v = trim((string) ($v ?? ''));
    if ($required && $v === '') {
        send_error("$label is required", 400);
    }
    if (mb_strlen($v) > $max) {
        send_error("$label must be $max characters or fewer", 400);
    }
    return $v === '' ? null : $v;
}

// ========== POST: 追加 ==========
if ($method === 'POST') {
    require_fields($in, ['name']);
    $name  = juice_text($in['name'] ?? '', 100, '名前', true);
    $desc  = juice_text($in['description'] ?? '', 500, '説明', false);
    $image = juice_text($in['image_url'] ?? '', 500, '画像URL', false);
    $price = juice_int($in['price'] ?? 0, 'price');
    $order = (int) $pdo->query('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM juices')->fetchColumn();

    $stmt = $pdo->prepare(
        'INSERT INTO juices (name, description, price, image_url, sort_order) VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$name, $desc, $price, $image, $order]);
    send_json(['id' => (int) $pdo->lastInsertId()], 201);
}

// 以下は 1つのジュースに対して
$find = $pdo->prepare('SELECT id FROM juices WHERE id = ?');
$find->execute([$id]);
if (!$find->fetch()) {
    send_error('Juice not found', 404);
}

// ========== PUT: 変更 ==========
if ($method === 'PUT') {
    $sets = [];
    $values = [];
    if (array_key_exists('name', $in))         { $sets[] = 'name = ?';         $values[] = juice_text($in['name'], 100, '名前', true); }
    if (array_key_exists('description', $in))  { $sets[] = 'description = ?';  $values[] = juice_text($in['description'], 500, '説明', false); }
    if (array_key_exists('image_url', $in))    { $sets[] = 'image_url = ?';    $values[] = juice_text($in['image_url'], 500, '画像URL', false); }
    if (array_key_exists('price', $in))        { $sets[] = 'price = ?';        $values[] = juice_int($in['price'], 'price'); }
    if (array_key_exists('sort_order', $in))   { $sets[] = 'sort_order = ?';   $values[] = juice_int($in['sort_order'], 'sort_order'); }
    if (array_key_exists('is_available', $in)) { $sets[] = 'is_available = ?'; $values[] = $in['is_available'] ? 1 : 0; }
    if (count($sets) === 0) {
        send_error('Nothing to change', 400);
    }
    $values[] = $id;
    $pdo->prepare('UPDATE juices SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($values);
    send_json(['id' => $id, 'updated' => true]);
}

// ========== DELETE: 削除 ==========
if ($method === 'DELETE') {
    $pdo->prepare('DELETE FROM juices WHERE id = ?')->execute([$id]);
    send_json(['id' => $id, 'deleted' => true]);
}
