<?php
// api/payments.php — 支払い (モック = 本物のお金は動かない). Owner: Zan (FEAT-15)
//   POST  本人 → { order_id, card_last4? }  → { payment_id, payment_status: "paid" }
//
// 支払い方法は注文に保存ずみ (orders.payment_method):
//   mock_card … カード。下4けただけ送る。テスト: 0002 は失敗。
//   paypay    … PayPay (モック)。いつも成功。
//   cod       … 代金引換。オンライン決済なし → この API は使わない (400)。
// カード番号は全部送らない・保存しない (本物のサービスでも、カード番号は自分で持たないのが基本)。

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/api.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/cart.php';   // cart_owner(): 支払い成功でカートを空にする

require_method('POST');
$in = read_json();

$order_id = filter_var($in['order_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$last4 = (string) ($in['card_last4'] ?? '');
if ($order_id === false) {
    send_error('order_id が必要です', 400);
}

$pdo->beginTransaction();

// 1. 注文を確認 (ロックして二重払いを防ぐ)
$stmt = $pdo->prepare('SELECT id, user_id, total_price, payment_status, status, payment_method FROM orders WHERE id = ? FOR UPDATE');
$stmt->execute([$order_id]);
$order = $stmt->fetch();

// 本人の注文か (guest は同じブラウザで作った注文だけ)
$mine = $order && (
    $order['user_id'] !== null
        ? (int) $order['user_id'] === (int) current_user_id()
        : in_array((int) $order['id'], $_SESSION['guest_order_ids'] ?? [], true)
);
if (!$mine) {
    $pdo->rollBack();
    send_error('注文が見つかりません', 404);
}
if ($order['payment_status'] === 'paid') {
    $pdo->rollBack();
    send_error('この注文はもう支払い済みです', 400);
}
if ($order['status'] === 'cancelled') {
    $pdo->rollBack();
    send_error('キャンセルされた注文です', 400);
}

$method = $order['payment_method'];
if ($method === 'cod') {
    $pdo->rollBack();
    send_error('代金引換の注文です。お受け取り時にお支払いください', 400);
}

// 2. モックの決済
$save_last4 = null;
if ($method === 'mock_card') {
    if (!preg_match('/^\d{4}$/', $last4)) {
        $pdo->rollBack();
        send_error('カード番号を確認してください', 400);
    }
    $ok = $last4 !== '0002';   // テスト用: 0002 だけ失敗
    $save_last4 = $last4;
} else {   // paypay
    $ok = true;                // PayPay (モック) はいつも成功
}

$stmt = $pdo->prepare(
    'INSERT INTO payments (order_id, method, amount, status, mock_transaction_id, card_last4)
     VALUES (?, ?, ?, ?, ?, ?)'
);
$stmt->execute([
    $order_id, $method, $order['total_price'], $ok ? 'succeeded' : 'failed',
    'MOCK-' . strtoupper(bin2hex(random_bytes(6))), $save_last4,
]);   // ← succeeded だと trigger (trg_payments_mark_order_insert) が orders.payment_status = 'paid' にする
$payment_id = (int) $pdo->lastInsertId();

// 支払いが成功したら カートを空にする (注文に移したので)
if ($ok) {
    [$cwhere, $cowner] = cart_owner();
    $pdo->prepare("DELETE FROM cart_bowls WHERE $cwhere")->execute([$cowner]);
    $pdo->prepare("DELETE FROM cart_drinks WHERE $cwhere")->execute([$cowner]);
}
$pdo->commit();

if (!$ok) {
    send_error('カードが使えませんでした。別のカードでお試しください', 402);
}
send_json(['payment_id' => $payment_id, 'payment_status' => 'paid'], 201);
