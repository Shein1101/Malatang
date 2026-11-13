<?php
// api/orders.php — 注文 API. Owner: Zan (FEAT-13, FEAT-14)
//   GET  ?slots=1      anyone → 今日えらべる受け取り時間  { is_open, opening_hours, slots: ["12:15", …] }
//   POST               anyone → cart から注文を作る      { customer_name, customer_phone(必須), customer_postal, customer_address, note, allergy_note }
//   GET  ?id=12        本人 / admin → 注文1つ (payment.php 用)
//   GET                admin → 注文一覧  (?status=all で終わった注文も)
//   PUT  ?id=12        admin → ステータスを変える { status }
// 注文履歴 (自分の過去の注文) は Lin の api/history.php。

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/api.php';
require __DIR__ . '/../includes/cart.php';

$method = require_method('GET', 'POST', 'PUT', 'DELETE');

// 注文ステータス: 次に進めるのはどれか (admin/orders.php のボタンと同じ)
// お店の主な操作: 「出来上がり」(ready) → 「受け渡し完了」(completed)。
// それに加えて、渡す前ならキャンセルもできる (キャンセル一覧に入る / 在庫は戻る)。
// confirmed / preparing は昔のデータ用 (あっても ready に進められる)。
const NEXT_STATUS = [
    'pending'   => ['ready', 'cancelled'],      // 調理待ち → 出来上がり / キャンセル
    'confirmed' => ['ready', 'cancelled'],      // (旧データ)
    'preparing' => ['ready', 'cancelled'],      // (旧データ)
    'ready'     => ['completed', 'cancelled'],  // 出来上がり → 受け渡し完了 / キャンセル
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
            'order_bowl_id' => (int) $b['id'],   // 支払い完了画面の「友達にシェア」で使う
            'menu_name'  => $b['menu_name'],
            'has_soup'   => (bool) $b['has_soup'],
            'soup_name'  => $b['soup_name'],
            'spice_name' => $b['spice_name'],
            'quantity'   => (int) $b['quantity'],
            'bowl_total' => (int) $b['bowl_total'],
            'items'      => $items[(int) $b['id']] ?? [],
        ];
    }

    // ドリンクを注文ごとに読む
    $drinksByOrder = [];
    $oid_list = array_column($orders, 'id');
    if ($oid_list) {
        $dmarks = implode(',', array_fill(0, count($oid_list), '?'));
        $dstmt = $pdo->prepare(
            "SELECT order_id, juice_name, juice_price, quantity FROM order_drinks WHERE order_id IN ($dmarks) ORDER BY id"
        );
        $dstmt->execute($oid_list);
        foreach ($dstmt->fetchAll() as $d) {
            $drinksByOrder[(int) $d['order_id']][] = [
                'name'       => $d['juice_name'],
                'price'      => (int) $d['juice_price'],
                'quantity'   => (int) $d['quantity'],
                'line_total' => (int) $d['juice_price'] * (int) $d['quantity'],
            ];
        }
    }

    $out = [];
    foreach ($orders as $o) {
        $out[] = [
            'id'             => (int) $o['id'],
            'order_number'   => $o['order_number'],
            'customer_name'  => $o['customer_name'],
            'customer_phone' => $o['customer_phone'],
            'customer_postal'  => $o['customer_postal'] ?? null,
            'customer_address' => $o['customer_address'] ?? null,
            'pickup_time'    => $o['pickup_time'],
            'total_price'    => (int) $o['total_price'],
            'payment_status' => $o['payment_status'],
            'payment_method' => $o['payment_method'] ?? 'mock_card',
            'status'         => $o['status'],
            'order_type'     => $o['order_type'] ?? 'takeout',
            'note'           => $o['note'],
            'allergy_note'   => $o['allergy_note'] ?? null,
            'is_guest'       => $o['user_id'] === null,
            'created_at'     => $o['created_at'],
            'next_status'    => NEXT_STATUS[$o['status']] ?? [],
            'bowls'          => $byOrder[(int) $o['id']] ?? [],
            'drinks'         => $drinksByOrder[(int) $o['id']] ?? [],
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
    $st = $_GET['status'] ?? '';
    if ($st === 'all') {
        // 管理画面でカテゴリ分け (要対応 / テイクアウト / 配達 / 支払済 / キャンセル / 完了) するため全部返す
        $sql = 'SELECT * FROM orders ORDER BY created_at DESC LIMIT 200';
    } elseif ($st === 'done') {
        // 受け渡し完了の注文 (完了タブ)
        $sql = "SELECT * FROM orders WHERE status = 'completed' ORDER BY created_at DESC LIMIT 100";
    } else {
        // 対応中 (まだ渡していない注文): 調理待ち + 出来上がり
        $sql = "SELECT * FROM orders WHERE status NOT IN ('completed','cancelled') ORDER BY created_at, id";
    }
    send_json(attach_bowls($pdo, $pdo->query($sql)->fetchAll()));
}

// ======================================================================
// POST : cart → 注文
// ======================================================================
if ($method === 'POST') {
    $in = read_json();

    // 1. お客さんの情報 (login している人は空なら会員情報を使う)
    $name    = trim((string) ($in['customer_name'] ?? ''));
    $phone   = trim((string) ($in['customer_phone'] ?? ''));
    $note    = trim((string) ($in['note'] ?? ''));
    $allergy = trim((string) ($in['allergy_note'] ?? ''));
    $postal  = trim((string) ($in['customer_postal'] ?? ''));
    $address = trim((string) ($in['customer_address'] ?? ''));
    $user_id = current_user_id();
    if ($user_id !== null) {
        // 空のところは会員情報でおぎなう (マイページに入れた住所など)
        $stmt = $pdo->prepare('SELECT name, phone, postal_code, address FROM users WHERE id = ?');
        $stmt->execute([$user_id]);
        $u = $stmt->fetch() ?: [];
        $name    = $name    !== '' ? $name    : (string) ($u['name'] ?? '');
        $phone   = $phone   !== '' ? $phone   : (string) ($u['phone'] ?? '');
        $postal  = $postal  !== '' ? $postal  : (string) ($u['postal_code'] ?? '');
        $address = $address !== '' ? $address : (string) ($u['address'] ?? '');
    }
    if ($name === '' || mb_strlen($name) > 100) {
        send_error('お名前を入力してください', 400);
    }
    if ($phone === '') {                         // 電話番号は必須
        send_error('電話番号を入力してください', 400);
    }
    if (!preg_match('/^[0-9\-+]{10,20}$/', $phone)) {
        send_error('電話番号は数字とハイフンで入力してください', 400);
    }
    if (mb_strlen($note) > 200) {
        send_error('備考は200文字以内で入力してください', 400);
    }
    if (mb_strlen($allergy) > 255) {
        send_error('アレルギーは255文字以内で入力してください', 400);
    }
    if (mb_strlen($postal) > 10) {
        send_error('郵便番号を確認してください', 400);
    }
    if (mb_strlen($address) > 255) {
        send_error('住所は255文字以内で入力してください', 400);
    }

    // 受け取り方法: テイクアウト（店頭）か 配達。配達は住所が必須。
    $order_type = (($in['order_type'] ?? 'takeout') === 'delivery') ? 'delivery' : 'takeout';
    if ($order_type === 'delivery' && $address === '') {
        send_error('配達をえらんだ場合は、ご住所を入力してください', 400);
    }

    // 支払い方法: カード / PayPay / 代金引換(COD)。画面は 'card' を送る → DB は 'mock_card'。
    $pm_map = ['card' => 'mock_card', 'mock_card' => 'mock_card', 'paypay' => 'paypay', 'cod' => 'cod'];
    $payment_method = $pm_map[(string) ($in['payment_method'] ?? 'card')] ?? 'mock_card';

    // 2. 受け取り時間は使いません。でも お店が閉まっているときは注文を受けない
    $store = $pdo->query('SELECT is_open FROM store_settings WHERE id = 1')->fetch();
    if (!$store || !$store['is_open']) {
        send_error('ただいま注文を受け付けていません', 400);
    }
    $pickupAt = null;

    // 3. ここから transaction: 全部成功するか、全部なかったことにする
    $pdo->beginTransaction();
    try {
        $pdo->prepare('SET @current_user_id = ?')->execute([$user_id]);   // order_status_history に記録される

        // cart をロックして読む (2回押しても二重注文にならない)
        $cart = load_cart($pdo, true);
        if (!$cart['bowls'] && !$cart['drinks']) {
            throw new InvalidArgumentException('カートが空です');
        }
        foreach ($cart['bowls'] as $b) {
            if ($b['error'] !== null) {
                throw new InvalidArgumentException($b['menu_name'] . ': ' . $b['error']);
            }
        }

        // 前に作って まだ支払っていない注文（下書き）を消す。
        // 支払い前に情報入力ページへ「もどる」→ もう一度「確定」しても、注文が二重にならない。
        // カートは支払いが終わるまで消さないので、もどってもカートの中身は残る。
        $priorIds = [];
        if ($user_id !== null) {
            // 代金引換(COD) は注文した時点で確定なので 下書きではない → 消さない
            $q = $pdo->prepare(
                "SELECT id FROM orders
                 WHERE user_id = ? AND payment_status = 'unpaid' AND status = 'pending' AND payment_method <> 'cod'
                 FOR UPDATE"
            );
            $q->execute([$user_id]);
            $priorIds = array_map('intval', array_column($q->fetchAll(), 'id'));
        } else {
            $sess = array_values(array_unique(array_map('intval', $_SESSION['guest_order_ids'] ?? [])));
            if ($sess) {
                $marks = implode(',', array_fill(0, count($sess), '?'));
                $q = $pdo->prepare(
                    "SELECT id FROM orders
                     WHERE id IN ($marks) AND payment_status = 'unpaid' AND status = 'pending' AND payment_method <> 'cod'
                     FOR UPDATE"
                );
                $q->execute($sess);
                $priorIds = array_map('intval', array_column($q->fetchAll(), 'id'));
            }
        }
        if ($priorIds) {
            // 下書きを作ったときに減らした在庫を戻してから消す (order_bowls/items/drinks は CASCADE)
            $restore = $pdo->prepare(
                "UPDATE ingredients i
                 JOIN (SELECT oi.ingredient_id, SUM(oi.quantity * ob.quantity) AS qty
                       FROM order_items oi JOIN order_bowls ob ON ob.id = oi.order_bowl_id
                       WHERE ob.order_id = ? AND oi.action = 'added' AND oi.ingredient_id IS NOT NULL
                       GROUP BY oi.ingredient_id) used ON used.ingredient_id = i.id
                 SET i.stock = i.stock + used.qty"
            );
            $del = $pdo->prepare('DELETE FROM orders WHERE id = ?');
            foreach ($priorIds as $pid) {
                $restore->execute([$pid]);
                $del->execute([$pid]);
            }
            if ($user_id === null) {
                $_SESSION['guest_order_ids'] = array_values(array_diff($_SESSION['guest_order_ids'] ?? [], $priorIds));
            }
        }

        // 注文番号: CN + 日付 + ランダム4けた (重なったら作り直す)
        $stmt = $pdo->prepare('SELECT 1 FROM orders WHERE order_number = ?');
        do {
            $number = 'CN' . date('ymd') . '-' . random_int(1000, 9999);
            $stmt->execute([$number]);
        } while ($stmt->fetchColumn());

        $stmt = $pdo->prepare(
            'INSERT INTO orders (order_number, user_id, customer_name, customer_phone, customer_postal, customer_address,
                                 pickup_time, total_price, note, allergy_note, order_type, payment_method)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $number, $user_id, $name, $phone, $postal ?: null, $address ?: null,
            $pickupAt, $cart['total'], $note ?: null, $allergy ?: null, $order_type, $payment_method,
        ]);
        $order_id = (int) $pdo->lastInsertId();

        // bowl ごとに「その時の名前と値段」をコピーして残す (あとで値段が変わっても注文は変わらない)
        $getMenu  = $pdo->prepare('SELECT name, price FROM menus WHERE id = ?');
        $getSoup  = $pdo->prepare('SELECT name, price FROM soups WHERE id = ?');
        $getSpice = $pdo->prepare('SELECT name, extra_price FROM spice_levels WHERE id = ?');
        $addBowl  = $pdo->prepare(
            'INSERT INTO order_bowls (order_id, shared_recipe_id, menu_id, menu_name, menu_price, has_soup, soup_id, soup_name, soup_price,
                                      spice_level_id, spice_name, spice_price, quantity, bowl_total)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );   // shared_recipe_id があると trigger (trg_order_bowls_share_ordered) がシェアを「注文済み」にする
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
                $order_id, $b['shared_recipe_id'], $b['menu_id'], $menu['name'], $menu['price'], $b['has_soup'] ? 1 : 0,
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

        // ドリンク (ジュース) を「その時の名前と値段」でコピーして残す
        $addDrink = $pdo->prepare(
            'INSERT INTO order_drinks (order_id, juice_id, juice_name, juice_price, quantity) VALUES (?, ?, ?, ?, ?)'
        );
        foreach ($cart['drinks'] as $d) {
            if ($d['error'] !== null) {
                throw new InvalidArgumentException('ドリンク（' . $d['name'] . '）: ' . $d['error']);
            }
            $addDrink->execute([$order_id, $d['juice_id'], $d['name'], $d['price'], $d['quantity']]);
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

        // カードや PayPay はここではカートを消さない。支払いが成功したときに api/payments.php で消す。
        // → 支払い前に「もどる」でカートや注文情報を見直せる (注文が消えたように見えない)。
        // 代金引換(COD) は支払い画面がない（注文した時点で確定）ので、ここで空にする。
        if ($payment_method === 'cod') {
            [$cw, $co] = cart_owner();
            $pdo->prepare("DELETE FROM cart_bowls WHERE $cw")->execute([$co]);
            $pdo->prepare("DELETE FROM cart_drinks WHERE $cw")->execute([$co]);
        }

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

    send_json(['order_id' => $order_id, 'order_number' => $number, 'total_price' => $cart['total'], 'payment_method' => $payment_method], 201);
}

// ======================================================================
// DELETE ?id=12 : 注文を消す (admin)。完了・キャンセルの注文だけ (記録の整理用)
// ======================================================================
if ($method === 'DELETE') {
    require_admin();
    $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id === false) {
        send_error('id が必要です', 400);
    }
    $stmt = $pdo->prepare('SELECT status FROM orders WHERE id = ?');
    $stmt->execute([$id]);
    $st = $stmt->fetchColumn();
    if ($st === false) {
        send_error('注文が見つかりません', 404);
    }
    if (!in_array($st, ['completed', 'cancelled'], true)) {
        send_error('完了・キャンセルの注文だけ削除できます', 400);
    }
    // order_bowls / order_items / order_drinks / payments / status_history は CASCADE で消える
    $pdo->prepare('DELETE FROM orders WHERE id = ?')->execute([$id]);
    send_json(['id' => $id, 'deleted' => true]);
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
$stmt = $pdo->prepare('SELECT status, payment_method, payment_status FROM orders WHERE id = ? FOR UPDATE');
$stmt->execute([$id]);
$cur = $stmt->fetch();
if ($cur === false) {
    $pdo->rollBack();
    send_error('注文が見つかりません', 404);
}
$old = $cur['status'];
if (!in_array($new, NEXT_STATUS[$old] ?? [], true)) {
    $pdo->rollBack();
    send_error('このステータスには変えられません', 400);
}

$pdo->prepare('SET @current_user_id = ?')->execute([current_user_id()]);   // 誰が変えたか (trigger が記録)
$pdo->prepare('UPDATE orders SET status = ? WHERE id = ?')->execute([$new, $id]);

// 代金引換(COD): 受け渡し完了のときに その場で支払い → 支払済にする
if ($new === 'completed' && $cur['payment_method'] === 'cod' && $cur['payment_status'] !== 'paid') {
    $pdo->prepare("UPDATE orders SET payment_status = 'paid' WHERE id = ?")->execute([$id]);
}

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
