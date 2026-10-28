<?php
// api/orders.php — 注文 API. Owner: Zan (FEAT-13, FEAT-14)
//   GET  ?slots=1      anyone → 今日えらべる受け取り時間  { is_open, opening_hours, slots: ["12:15", …] }
//   POST               anyone → cart から注文を作る      { customer_name, customer_phone, pickup_time: "12:15", note }
//   GET  ?id=12        本人 / admin → 注文1つ (payment.php 用)
//   GET                admin → 注文一覧  (?status=all で終わった注文も)
//   PUT  ?id=12        admin → ステータスを変える { status }
// 注文履歴 (自分の過去の注文) は Lin の api/history.php。

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/api.php';
require __DIR__ . '/../includes/cart.php';

$method = require_method('GET', 'POST', 'PUT');

// 注文ステータス: 次に進めるのはどれか (admin/orders.php のボタンと同じ)
const NEXT_STATUS = [
    'pending'   => ['confirmed', 'cancelled'],   // 受付待ち → 受付済
    'confirmed' => ['preparing', 'cancelled'],   // 受付済   → 調理中
    'preparing' => ['ready', 'cancelled'],       // 調理中   → 受取待ち
    'ready'     => ['completed', 'cancelled'],   // 受取待ち → 受取済
    'completed' => [],
    'cancelled' => [],
];

// ── 受け取り時間の候補 (今から15分後〜閉店まで、15分ごと) ──────────
function pickup_slots(PDO $pdo): array {
    $s = $pdo->query('SELECT opening_hours, is_open FROM store_settings LIMIT 1')->fetch();
    $hours = $s['opening_hours'] ?? '';
    $isOpen = $s ? (bool) $s['is_open'] : false;

    // "11:00〜21:00" から 開店・閉店 を読む。読めなければ 11:00〜21:00
    $open = '11:00';
    $close = '21:00';
    if (preg_match('/(\d{1,2}):(\d{2})\D+(\d{1,2}):(\d{2})/', $hours, $m)) {
        $open  = sprintf('%02d:%02d', $m[1], $m[2]);
        $close = sprintf('%02d:%02d', $m[3], $m[4]);
    }

    $slots = [];
    if ($isOpen) {
        $start = max(strtotime('today ' . $open), time() + 15 * 60);   // 早くても15分後
        $start = (int) ceil($start / 900) * 900;                         // 15分 (900秒) 単位に切り上げ
        $end   = strtotime('today ' . $close) - 15 * 60;                 // 閉店15分前まで
        for ($t = $start; $t <= $end; $t += 900) {
            $slots[] = date('H:i', $t);
        }
    }
    return ['is_open' => $isOpen, 'opening_hours' => $hours, 'slots' => $slots];
}

// ── 注文を見てよい人か (本人 / guest のときは同じブラウザ / admin) ──
function can_see_order(array $order): bool {
    if (is_admin()) {
        return true;
    }
    if ($order['user_id'] !== null) {
        return (int) $order['user_id'] === (int) current_user_id();
    }
    // guest の注文: このブラウザで作った注文だけ (POST のときに session に覚えておく)
    return in_array((int) $order['id'], $_SESSION['guest_order_ids'] ?? [], true);
}

// ── 注文の bowl と具材を読む (一覧でも1つでも使う) ─────────────────
function attach_bowls(PDO $pdo, array $orders): array {
    if (!$orders) {
        return [];
    }
    $ids = array_column($orders, 'id');
    $marks = implode(',', array_fill(0, count($ids), '?'));

    $stmt = $pdo->prepare(
        "SELECT id, order_id, menu_name, has_soup, soup_name, spice_name, quantity, bowl_total
         FROM order_bowls WHERE order_id IN ($marks) ORDER BY id"
    );
    $stmt->execute($ids);
    $bowls = $stmt->fetchAll();

    $items = [];
    if ($bowls) {
        $bids = array_column($bowls, 'id');
        $bmarks = implode(',', array_fill(0, count($bids), '?'));
        $stmt = $pdo->prepare(
            "SELECT order_bowl_id, ingredient_name, action, quantity
             FROM order_items WHERE order_bowl_id IN ($bmarks) ORDER BY action, id"
        );
        $stmt->execute($bids);
        foreach ($stmt->fetchAll() as $it) {
            $items[(int) $it['order_bowl_id']][] = [
                'ingredient_name' => $it['ingredient_name'],
                'action'          => $it['action'],
                'quantity'        => (int) $it['quantity'],
            ];
        }
    }

    $byOrder = [];
    foreach ($bowls as $b) {
        $byOrder[(int) $b['order_id']][] = [
            'menu_name'  => $b['menu_name'],
            'has_soup'   => (bool) $b['has_soup'],
            'soup_name'  => $b['soup_name'],
            'spice_name' => $b['spice_name'],
            'quantity'   => (int) $b['quantity'],
            'bowl_total' => (int) $b['bowl_total'],
            'items'      => $items[(int) $b['id']] ?? [],
        ];
    }

    $out = [];
    foreach ($orders as $o) {
        $out[] = [
            'id'             => (int) $o['id'],
            'order_number'   => $o['order_number'],
            'customer_name'  => $o['customer_name'],
            'customer_phone' => $o['customer_phone'],
            'pickup_time'    => $o['pickup_time'],
            'total_price'    => (int) $o['total_price'],
            'payment_status' => $o['payment_status'],
            'status'         => $o['status'],
            'note'           => $o['note'],
            'is_guest'       => $o['user_id'] === null,
            'created_at'     => $o['created_at'],
            'next_status'    => NEXT_STATUS[$o['status']] ?? [],
            'bowls'          => $byOrder[(int) $o['id']] ?? [],
        ];
    }
    return $out;
}

// ======================================================================
// GET
// ======================================================================
if ($method === 'GET') {

    // ---------- ?slots=1 : 受け取り時間の候補 ----------
    if (isset($_GET['slots'])) {
        send_json(pickup_slots($pdo));
    }

    // ---------- ?id=12 : 注文ひとつ ----------
    if (isset($_GET['id'])) {
        $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
        $stmt->execute([(int) $_GET['id']]);
        $order = $stmt->fetch();
        if (!$order || !can_see_order($order)) {
            send_error('注文が見つかりません', 404);   // 他人の注文は「ない」と同じ扱い
        }
        send_json(attach_bowls($pdo, [$order])[0]);
    }

    // ---------- 一覧 (admin だけ) ----------
    require_admin();
    $all = ($_GET['status'] ?? '') === 'all';
    $sql = 'SELECT * FROM orders'
         . ($all ? '' : " WHERE status NOT IN ('completed', 'cancelled')")
         . ($all ? ' ORDER BY created_at DESC LIMIT 100' : ' ORDER BY pickup_time, id');
    send_json(attach_bowls($pdo, $pdo->query($sql)->fetchAll()));
}

// ======================================================================
// POST : cart → 注文
// ======================================================================
if ($method === 'POST') {
    $in = read_json();

    // 1. お客さんの情報 (login している人は空なら会員情報を使う)
    $name  = trim((string) ($in['customer_name'] ?? ''));
    $phone = trim((string) ($in['customer_phone'] ?? ''));
    $note  = trim((string) ($in['note'] ?? ''));
    $user_id = current_user_id();
    if ($user_id !== null && ($name === '' || $phone === '')) {
        $stmt = $pdo->prepare('SELECT name, phone FROM users WHERE id = ?');
        $stmt->execute([$user_id]);
        $u = $stmt->fetch() ?: [];
        $name  = $name  !== '' ? $name  : (string) ($u['name'] ?? '');
        $phone = $phone !== '' ? $phone : (string) ($u['phone'] ?? '');
    }
    if ($name === '' || mb_strlen($name) > 100) {
        send_error('お名前を入力してください', 400);
    }
    if ($phone !== '' && !preg_match('/^[0-9\-+]{10,20}$/', $phone)) {
        send_error('電話番号は数字とハイフンで入力してください', 400);
    }
    if (mb_strlen($note) > 200) {
        send_error('備考は200文字以内で入力してください', 400);
    }

    // 2. 受け取り時間: 候補の中にあるか (過去の時間・閉店後はだめ)
    $slots = pickup_slots($pdo);
    if (!$slots['is_open']) {
        send_error('ただいま注文を受け付けていません', 400);
    }
    $pickup = (string) ($in['pickup_time'] ?? '');
    if (!in_array($pickup, $slots['slots'], true)) {
        send_error('受け取り時間を選び直してください', 400);
    }
    $pickupAt = date('Y-m-d') . ' ' . $pickup . ':00';

    // 3. ここから transaction: 全部成功するか、全部なかったことにする
    $pdo->beginTransaction();
    try {
        $pdo->prepare('SET @current_user_id = ?')->execute([$user_id]);   // order_status_history に記録される

        // cart をロックして読む (2回押しても二重注文にならない)
        $cart = load_cart($pdo, true);
        if (!$cart['bowls']) {
            throw new InvalidArgumentException('カートが空です');
        }
        foreach ($cart['bowls'] as $b) {
            if ($b['error'] !== null) {
                throw new InvalidArgumentException($b['menu_name'] . ': ' . $b['error']);
            }
        }

        // 注文番号: CN + 日付 + ランダム4けた (重なったら作り直す)
        $stmt = $pdo->prepare('SELECT 1 FROM orders WHERE order_number = ?');
        do {
            $number = 'CN' . date('ymd') . '-' . random_int(1000, 9999);
            $stmt->execute([$number]);
        } while ($stmt->fetchColumn());

        $stmt = $pdo->prepare(
            'INSERT INTO orders (order_number, user_id, customer_name, customer_phone, pickup_time, total_price, note)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$number, $user_id, $name, $phone ?: null, $pickupAt, $cart['total'], $note ?: null]);
        $order_id = (int) $pdo->lastInsertId();

        // bowl ごとに「その時の名前と値段」をコピーして残す (あとで値段が変わっても注文は変わらない)
        $getMenu  = $pdo->prepare('SELECT name, price FROM menus WHERE id = ?');
        $getSoup  = $pdo->prepare('SELECT name, price FROM soups WHERE id = ?');
        $getSpice = $pdo->prepare('SELECT name, extra_price FROM spice_levels WHERE id = ?');
        $addBowl  = $pdo->prepare(
            'INSERT INTO order_bowls (order_id, menu_id, menu_name, menu_price, has_soup, soup_id, soup_name, soup_price,
                                      spice_level_id, spice_name, spice_price, quantity, bowl_total)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $addItem  = $pdo->prepare(
            'INSERT INTO order_items (order_bowl_id, ingredient_id, ingredient_name, action, unit_price, quantity)
             VALUES (?, ?, ?, ?, ?, ?)'
        );   // ← INSERT すると trigger (trg_order_items_reduce_stock) が在庫を減らす

        foreach ($cart['bowls'] as $b) {
            $getMenu->execute([$b['menu_id']]);
            $menu = $getMenu->fetch();
            $getSoup->execute([$b['soup_id']]);
            $soup = $getSoup->fetch();
            $spice = ['name' => null, 'extra_price' => 0];
            if ($b['spice_level_id'] !== null) {
                $getSpice->execute([$b['spice_level_id']]);
                $spice = $getSpice->fetch();
            }

            $addBowl->execute([
                $order_id, $b['menu_id'], $menu['name'], $menu['price'], $b['has_soup'] ? 1 : 0,
                $b['soup_id'], $soup['name'], $soup['price'],
                $b['spice_level_id'], $spice['name'], $spice['extra_price'],
                $b['quantity'], $b['price'],
            ]);
            $order_bowl_id = (int) $pdo->lastInsertId();

            foreach ($b['ingredients'] as $it) {
                $addItem->execute([
                    $order_bowl_id, $it['ingredient_id'], $it['name'], $it['action'],
                    $it['action'] === 'added' ? $it['price'] : 0, $it['quantity'],
                ]);
            }
        }

        // 2つの bowl で同じ具材を使ったときなど、在庫がマイナスになったら注文しない
        $used = [];
        foreach ($cart['bowls'] as $b) {
            foreach ($b['ingredients'] as $it) {
                if ($it['action'] === 'added') {
                    $used[] = $it['ingredient_id'];
                }
            }
        }
        $short = false;
        if ($used) {
            $marks = implode(',', array_fill(0, count($used), '?'));
            $stmt = $pdo->prepare("SELECT name FROM ingredients WHERE stock < 0 AND id IN ($marks) LIMIT 1");
            $stmt->execute($used);
            $short = $stmt->fetchColumn();
        }
        if ($short !== false) {
            throw new InvalidArgumentException("{$short} の在庫が足りません。杯数を減らしてください");
        }

        // cart を空にする
        [$where, $owner] = cart_owner();
        $pdo->prepare("DELETE FROM cart_bowls WHERE $where")->execute([$owner]);

        $pdo->commit();
    } catch (InvalidArgumentException $e) {
        $pdo->rollBack();
        send_error($e->getMessage(), 400);
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;   // includes/api.php が 500 にする
    }

    // guest はこのブラウザの session で自分の注文を覚えておく (payment.php で見るため)
    if ($user_id === null) {
        $_SESSION['guest_order_ids'][] = $order_id;
    }

    send_json(['order_id' => $order_id, 'order_number' => $number, 'total_price' => $cart['total']], 201);
}

// ======================================================================
// PUT ?id=12 : ステータスを変える (admin)
// ======================================================================
require_admin();
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($id === false) {
    send_error('id が必要です', 400);
}
$in = read_json();
$new = (string) ($in['status'] ?? '');

$pdo->beginTransaction();
$stmt = $pdo->prepare('SELECT status FROM orders WHERE id = ? FOR UPDATE');
$stmt->execute([$id]);
$old = $stmt->fetchColumn();
if ($old === false) {
    $pdo->rollBack();
    send_error('注文が見つかりません', 404);
}
if (!in_array($new, NEXT_STATUS[$old] ?? [], true)) {
    $pdo->rollBack();
    send_error('このステータスには変えられません', 400);
}

$pdo->prepare('SET @current_user_id = ?')->execute([current_user_id()]);   // 誰が変えたか (trigger が記録)
$pdo->prepare('UPDATE orders SET status = ? WHERE id = ?')->execute([$new, $id]);

// キャンセル → 使うはずだった在庫を戻す
if ($new === 'cancelled') {
    $pdo->prepare(
        "UPDATE ingredients i
         JOIN (SELECT oi.ingredient_id, SUM(oi.quantity * ob.quantity) AS qty
               FROM order_items oi JOIN order_bowls ob ON ob.id = oi.order_bowl_id
               WHERE ob.order_id = ? AND oi.action = 'added' AND oi.ingredient_id IS NOT NULL
               GROUP BY oi.ingredient_id) used ON used.ingredient_id = i.id
         SET i.stock = i.stock + used.qty"
    )->execute([$id]);
}
$pdo->commit();

send_json(['id' => $id, 'status' => $new, 'next_status' => NEXT_STATUS[$new]]);
