<?php
// api/cart.php — カート API. Owner: Zan (FEAT-11, FEAT-13)
//   GET                anyone → 自分の cart の中身 (今の値段つき)
//   POST               anyone → bowl を1つ追加  { menu_id, has_soup, soup_id, spice_level_id, quantity, ingredients:[…] }
//   PUT    ?id=7       anyone → 杯数を変える   { quantity }
//   DELETE ?id=7       anyone → bowl を消す
// login していない人 (guest) も使える。持ち主は includes/cart.php の cart_owner() で決まる。
// 値段は画面から受け取らない。いつも server で計算する。

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/api.php';
require __DIR__ . '/../includes/cart.php';

$method = require_method('GET', 'POST', 'PUT', 'DELETE');
[$where, $owner] = cart_owner();

// ========== GET: cart を見る ==========
if ($method === 'GET') {
    send_json(load_cart($pdo));
}

// ========== POST: bowl を追加 ==========
if ($method === 'POST') {
    [$bowl, $items, $quantity] = read_bowl_input(read_json());

    // 1. 今の値段で買えるか (売り切れ・外せない具材・在庫など) → だめなら 400
    $check = bowl_price($pdo, $bowl, $items, $quantity);
    if ($check['error'] !== null) {
        send_error($check['error'], 400);
    }

    // 2. cart_bowls と cart_bowl_ingredients に入れる (2つの表なので transaction)
    $pdo->beginTransaction();
    $stmt = $pdo->prepare(
        'INSERT INTO cart_bowls (user_id, guest_token, menu_id, has_soup, soup_id, spice_level_id, quantity)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $where === 'user_id = ?' ? $owner : null,
        $where === 'guest_token = ?' ? $owner : null,
        $bowl['menu_id'], $bowl['has_soup'] ? 1 : 0, $bowl['soup_id'], $bowl['spice_level_id'], $quantity,
    ]);
    $cart_bowl_id = (int) $pdo->lastInsertId();

    $stmt = $pdo->prepare(
        'INSERT INTO cart_bowl_ingredients (cart_bowl_id, ingredient_id, action, quantity) VALUES (?, ?, ?, ?)'
    );
    foreach ($items as $it) {
        $stmt->execute([$cart_bowl_id, $it['ingredient_id'], $it['action'], $it['quantity']]);
    }
    $pdo->commit();

    $cart = load_cart($pdo);
    send_json(['cart_bowl_id' => $cart_bowl_id, 'price' => $check['price'], 'count' => $cart['count']], 201);
}

// ========== ここから下 (PUT / DELETE) は ?id= が必要 ==========
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($id === false) {
    send_error('id が必要です', 400);
}

// 自分の bowl か確認 (他の人の cart は触れない)
$stmt = $pdo->prepare("SELECT id, menu_id, has_soup, soup_id, spice_level_id FROM cart_bowls WHERE id = ? AND $where");
$stmt->execute([$id, $owner]);
$row = $stmt->fetch();
if (!$row) {
    send_error('カートにありません', 404);
}

// ========== PUT: 杯数を変える ==========
if ($method === 'PUT') {
    $in = read_json();
    $quantity = filter_var($in['quantity'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 20]]);
    if ($quantity === false) {
        send_error('杯数は1〜20で選んでください', 400);
    }

    // 杯数が増えると在庫が足りなくなることがあるので、もう一度チェック
    $stmt = $pdo->prepare('SELECT ingredient_id, action, quantity FROM cart_bowl_ingredients WHERE cart_bowl_id = ?');
    $stmt->execute([$id]);
    $check = bowl_price($pdo, bowl_from_row($row), $stmt->fetchAll(), $quantity);
    if ($check['error'] !== null) {
        send_error($check['error'], 400);
    }

    $stmt = $pdo->prepare('UPDATE cart_bowls SET quantity = ? WHERE id = ?');
    $stmt->execute([$quantity, $id]);
    send_json(['id' => $id, 'quantity' => $quantity, 'price' => $check['price']]);
}

// ========== DELETE: bowl を消す ==========
// cart_bowl_ingredients は ON DELETE CASCADE で一緒に消える
$stmt = $pdo->prepare('DELETE FROM cart_bowls WHERE id = ?');
$stmt->execute([$id]);
send_json(['id' => $id]);
