<?php
// api/menus.php — Menus API. Owner: Myat
//   GET          anyone → menu list
//   GET ?id=3    anyone → one menu + its default ingredients

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/api.php';

$method = require_method('GET');

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