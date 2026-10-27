<?php
// api/menus.php — Menus API. Owner: Myat
//   GET          anyone → menu list
//   GET ?id=3    anyone → one menu + its default ingredients

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/api.php';

$method = require_method('GET', 'POST', 'PUT', 'DELETE');   // 🆕

// ========== GET ==========
if ($method === 'GET') {

    // 🆕 ---------- ?id=3 があるとき: menu ひとつ + 具材 ----------
    if (isset($_GET['id'])) {
        $id = (int) $_GET['id'];

        // 1. menu をひとつ探す
        $stmt = $pdo->prepare(
            'SELECT id, name, description, price, image_url, is_available, sort_order
             FROM menus
             WHERE id = ? AND is_deleted = 0'
        );
        $stmt->execute([$id]);
        $menu = $stmt->fetch();
        if (!$menu) {
            send_error('Menu not found', 404);
        }

        // 2. その menu に最初から入っている具材 (menu_ingredients と ingredients をつなぐ)
        $stmt = $pdo->prepare(
            'SELECT i.id, i.name, i.price, i.is_available,
                    c.name AS category_name,
                    mi.quantity, mi.is_removable
             FROM menu_ingredients mi
             JOIN ingredients i ON i.id = mi.ingredient_id
             JOIN categories  c ON c.id = i.category_id
             WHERE mi.menu_id = ? AND i.is_deleted = 0
             ORDER BY c.sort_order, i.sort_order'
        );
        $stmt->execute([$id]);

        $ingredients = [];
        foreach ($stmt->fetchAll() as $r) {
            $ingredients[] = [
                'ingredient_id' => (int) $r['id'],
                'name'          => $r['name'],
                'category_name' => $r['category_name'],
                'price'         => (int) $r['price'],
                'is_available'  => (bool) $r['is_available'],
                'quantity'      => (int) $r['quantity'],
                'is_removable'  => (bool) $r['is_removable'],   // false = お客さんは外せない (春雨など)
            ];
        }

        // 3. menu と具材をひとつにして返す
        send_json([
            'id'           => (int) $menu['id'],
            'name'         => $menu['name'],
            'description'  => $menu['description'],
            'price'        => (int) $menu['price'],
            'image_url'    => $menu['image_url'],
            'is_available' => (bool) $menu['is_available'],
            'ingredients'  => $ingredients,
        ]);
    }

    // ---------- ?id がないとき: menu の一覧 (前と同じ) ----------
    $sql = 'SELECT id, name, description, price, image_url, is_available, sort_order
            FROM menus
            WHERE is_deleted = 0
            ORDER BY sort_order, id';
    $rows = $pdo->query($sql)->fetchAll();

    $data = [];
    foreach ($rows as $r) {
        $data[] = [
            'id'           => (int) $r['id'],
            'name'         => $r['name'],
            'description'  => $r['description'],
            'price'        => (int) $r['price'],
            'image_url'    => $r['image_url'],
            'is_available' => (bool) $r['is_available'],
            'sort_order'   => (int) $r['sort_order'],
        ];
    }
    send_json($data);
}  

// ========== 🆕 ここから下は admin だけ ==========
$isAdmin  = ($_SESSION['role'] ?? '') === 'admin';
$devAdmin = ($env['DEV_ADMIN'] ?? '0') === '1';   // .env の DEV_ADMIN=1 (テスト用)
if (!$isAdmin && !$devAdmin) {
    send_error('Admin only', 403);
}

// 値段は 0 以上の整数か
function check_price($value): int {
    $p = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    if ($p === false) {
        send_error('Price must be a number 0 or more', 400);
    }
    return $p;
}   
// ========== 🆕 POST: menu を追加 ==========
// 春雨は自動で入れる (外せない)。ほかの具材はお客さんがカスタマイズで選ぶ
if ($method === 'POST') {
    $in = read_json();
    require_fields($in, ['name', 'price']);

    $stmt = $pdo->prepare(
        'INSERT INTO menus (name, description, price, image_url, is_available, sort_order)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        trim($in['name']),
        $in['description'] ?? null,
        check_price($in['price']),
        $in['image_url'] ?? null,
        isset($in['is_available']) ? ($in['is_available'] ? 1 : 0) : 1,
        (int) ($in['sort_order'] ?? 0),
    ]);
    $id = (int) $pdo->lastInsertId();

    // 春雨を「外せない具材」として入れる
    $pdo->prepare(
        'INSERT INTO menu_ingredients (menu_id, ingredient_id, quantity, is_removable)
         SELECT ?, id, 1, 0 FROM ingredients WHERE name = ? AND is_deleted = 0 LIMIT 1'
    )->execute([$id, '春雨']);

    send_json(['id' => $id], 201);
}

// ========== 🆕 PUT: menu を変更 (?id=3) ==========
if ($method === 'PUT') {
    $id = (int) ($_GET['id'] ?? 0);
    $find = $pdo->prepare('SELECT id FROM menus WHERE id = ? AND is_deleted = 0');
    $find->execute([$id]);
    if (!$find->fetch()) {
        send_error('Menu not found', 404);
    }

    $in = read_json();
    $sets = [];
    $values = [];
    if (isset($in['name']))         { $sets[] = 'name = ?';         $values[] = trim($in['name']); }
    if (isset($in['description']))  { $sets[] = 'description = ?';  $values[] = $in['description']; }
    if (isset($in['price']))        { $sets[] = 'price = ?';        $values[] = check_price($in['price']); }
    if (isset($in['image_url']))    { $sets[] = 'image_url = ?';    $values[] = $in['image_url']; }
    if (isset($in['is_available'])) { $sets[] = 'is_available = ?'; $values[] = $in['is_available'] ? 1 : 0; }
    if (isset($in['sort_order']))   { $sets[] = 'sort_order = ?';   $values[] = (int) $in['sort_order']; }
    if (count($sets) === 0) {
        send_error('Nothing to change', 400);
    }

    // 値段を変えたら price_history に残る (DB の trigger)。だれが変えたかを渡す
    $pdo->prepare('SET @current_user_id = ?')->execute([$_SESSION['user_id'] ?? null]);

    $values[] = $id;
    $pdo->prepare('UPDATE menus SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($values);
    send_json(['id' => $id, 'updated' => true]);
}

// ========== 🆕 DELETE: menu を削除 (?id=3) ==========
// 本当には消さない。is_deleted = 1 にする (昔の注文がこの menu を使っているので)
if ($method === 'DELETE') {
    $id = (int) ($_GET['id'] ?? 0);
    $stmt = $pdo->prepare('UPDATE menus SET is_deleted = 1 WHERE id = ? AND is_deleted = 0');
    $stmt->execute([$id]);
    if ($stmt->rowCount() === 0) {
        send_error('Menu not found', 404);
    }
    send_json(['id' => $id, 'deleted' => true]);
}

