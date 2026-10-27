<?php
// api/history.php — 注文履歴 + 再注文. Owner: Lin
// ログインしている人だけ。自分の注文しか見えない。
//   GET                              → 注文の一覧 (新しい順、bowl つき)
//   POST ?action=reorder { order_bowl_id }  → その bowl 1つをカートに入れる
//   POST ?action=reorder { order_id }       → その注文の bowl を全部カートに入れる
// 再注文は「今の値段」。売り切れが1つでもあれば、何もカートに入れずに error を返す。

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/api.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/bowl.php';

$method = require_method('GET', 'POST');
require_login();
$user_id = (int) current_user_id();

// ========== GET: 一覧 ==========
if ($method === 'GET') {
    $stmt = $pdo->prepare('SELECT * FROM v_order_history WHERE user_id = ? ORDER BY ordered_at DESC, order_id DESC');
    $stmt->execute([$user_id]);
    $orders = [];
    foreach ($stmt->fetchAll() as $r) {
        $orders[(int) $r['order_id']] = [
            'order_id'       => (int) $r['order_id'],
            'order_number'   => $r['order_number'],
            'ordered_at'     => $r['ordered_at'],
            'pickup_time'    => $r['pickup_time'],
            'status'         => $r['status'],
            'payment_status' => $r['payment_status'],
            'total_price'    => (int) $r['total_price'],
            'bowls'          => [],
        ];
    }

    $stmt = $pdo->prepare('SELECT * FROM v_order_history_bowls WHERE user_id = ? ORDER BY order_bowl_id');
    $stmt->execute([$user_id]);
    foreach ($stmt->fetchAll() as $r) {
        $oid = (int) $r['order_id'];
        if (!isset($orders[$oid])) {
            continue;
        }
        $orders[$oid]['bowls'][] = [
            'order_bowl_id'       => (int) $r['order_bowl_id'],
            'menu_name'           => $r['menu_name'],
            'has_soup'            => (bool) $r['has_soup'],
            'soup_name'           => $r['soup_name'],
            'spice_name'          => $r['spice_name'],
            'quantity'            => (int) $r['quantity'],
            'added_ingredients'   => $r['added_ingredients'],     // 'エビ ×2, 牛肉 ×1' or null
            'removed_ingredients' => $r['removed_ingredients'],   // 'もやし' or null
            'bowl_total'          => (int) $r['bowl_total'],
        ];
    }
    send_json(array_values($orders));
}

// ========== POST ?action=reorder ==========
if (($_GET['action'] ?? '') !== 'reorder') {
    send_error('Invalid action', 400);
}
$in = read_json();

$sql = 'SELECT ob.id, ob.menu_id, ob.menu_name, ob.has_soup, ob.soup_id, ob.spice_level_id, ob.quantity
        FROM order_bowls ob
        JOIN orders o ON o.id = ob.order_id
        WHERE o.user_id = ? AND ';
if (isset($in['order_bowl_id'])) {
    $stmt = $pdo->prepare($sql . 'ob.id = ?');
    $stmt->execute([$user_id, (int) $in['order_bowl_id']]);
} elseif (isset($in['order_id'])) {
    $stmt = $pdo->prepare($sql . 'ob.order_id = ? ORDER BY ob.id');
    $stmt->execute([$user_id, (int) $in['order_id']]);
} else {
    send_error('Missing field: order_bowl_id or order_id', 400);
}
$rows = $stmt->fetchAll();
if (count($rows) === 0) {
    send_error('注文が見つかりません', 404);
}

// 1. まず全部チェック (1つでもだめなら、どれも入れない)
$itemStmt = $pdo->prepare(
    'SELECT ingredient_id, action, SUM(quantity) AS quantity
     FROM order_items
     WHERE order_bowl_id = ? AND ingredient_id IS NOT NULL
     GROUP BY ingredient_id, action'
);
$bowls = [];
$total = 0;
foreach ($rows as $r) {
    $itemStmt->execute([(int) $r['id']]);
    $bowl  = bowl_from_row($r);
    $items = $itemStmt->fetchAll();
    $qty   = (int) $r['quantity'];

    $check = bowl_price($pdo, $bowl, $items, $qty);
    if ($check['error'] !== null) {
        send_error($r['menu_name'] . ': ' . $check['error'], 409);
    }
    $bowls[] = [$bowl, $items, $qty];
    $total  += $check['price'];
}

// 2. カートに入れる
$pdo->beginTransaction();
$ids = [];
foreach ($bowls as [$bowl, $items, $qty]) {
    $ids[] = add_bowl_to_cart($pdo, $user_id, $bowl, $items, $qty);
}
$pdo->commit();

send_json(['cart_bowl_ids' => $ids, 'price' => $total], 201);
