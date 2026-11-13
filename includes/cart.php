<?php
// includes/cart.php — cart の共通処理. Owner: Zan (FEAT-13)
// api/cart.php と api/orders.php の両方が使う。
//   cart_owner()      → この cart は誰のもの? (login: user_id / guest: guest_token)
//   read_bowl_input() → 画面から来た bowl JSON をチェックして形をそろえる
//   load_cart()       → cart の中身を「今の値段」つきで全部読む
//
// 値段の計算は Lin の includes/bowl.php → bowl_price() (DB の fn_bowl_price) を使う。
// 画面 (customize.js) の値段は「見せるため」だけ。本当の値段はいつもここで計算する。

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/bowl.php';

// ── cart の持ち主 ───────────────────────────────────────────
// 戻り値: [SQL の条件, その値]  例: ['user_id = ?', 3] / ['guest_token = ?', 'ab12…']
// login していない人も注文できるように guest_token (Lin の guest_token()) を使う。
// login すると Lin の api/auth.php が guest の cart を user に移してくれる。
function cart_owner(): array {
    $user_id = current_user_id();
    if ($user_id !== null) {
        return ['user_id = ?', (int) $user_id];
    }
    return ['guest_token = ?', guest_token()];
}

// ── 画面から来た bowl をチェック ─────────────────────────────
// $in = { menu_id, has_soup, soup_id, spice_level_id, quantity,
//         ingredients: [{ ingredient_id, action: "added"|"removed", quantity }] }
// おかしいときは 400 error を返して止まる (send_error)。
function read_bowl_input(array $in): array {
    $menu_id = filter_var($in['menu_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $soup_id = filter_var($in['soup_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($menu_id === false) {
        send_error('メニューを選んでください', 400);
    }
    if ($soup_id === false) {
        send_error('味を選んでください', 400);   // cart_bowls.soup_id は必須
    }

    // 辛さは選ばなくてもよい (null)
    $spice = $in['spice_level_id'] ?? null;
    if ($spice !== null) {
        $spice = filter_var($spice, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($spice === false) {
            send_error('辛さが正しくありません', 400);
        }
    }

    $quantity = filter_var($in['quantity'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 20]]);
    if ($quantity === false) {
        send_error('杯数は1〜20で選んでください', 400);
    }

    // 具材: 同じ具材が2回あると DB に入らない (PRIMARY KEY) ので先にチェック
    $items = [];
    $seen  = [];
    foreach ($in['ingredients'] ?? [] as $it) {
        $id  = filter_var($it['ingredient_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $qty = filter_var($it['quantity'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 10]]);
        $action = $it['action'] ?? 'added';
        if ($id === false || $qty === false || !in_array($action, ['added', 'removed'], true)) {
            send_error('具材の内容が正しくありません', 400);
        }
        if (isset($seen[$id])) {
            send_error('同じ具材が2回入っています', 400);
        }
        $seen[$id] = true;
        $items[] = ['ingredient_id' => $id, 'action' => $action, 'quantity' => $action === 'removed' ? 1 : $qty];
    }

    $bowl = [
        'menu_id'        => $menu_id,
        'has_soup'       => !array_key_exists('has_soup', $in) || (bool) $in['has_soup'],   // 省略 = スープあり
        'soup_id'        => $soup_id,
        'spice_level_id' => $spice,
    ];
    return [$bowl, $items, $quantity];
}

// ── cart を全部読む ─────────────────────────────────────────
// 戻り値: [
//   'bowls' => [{ id, menu_id, menu_name, has_soup, soup_id, soup_name, spice_level_id, spice_name,
//                 quantity, ingredients: [{ ingredient_id, name, action, quantity, price }],
//                 price (この bowl の合計 or null), error (買えない理由 or null) }],
//   'total' => 合計 (買える bowl だけ), 'count' => 杯数の合計, 'can_checkout' => true/false ]
// $forUpdate = true → 注文中に他の人が同じ cart を触れないように行をロックする (api/orders.php 用)
function load_cart(PDO $pdo, bool $forUpdate = false): array {
    [$where, $owner] = cart_owner();

    $stmt = $pdo->prepare(
        "SELECT cb.id, cb.menu_id, m.name AS menu_name, cb.has_soup,
                cb.soup_id, s.name AS soup_name,
                cb.spice_level_id, sp.name AS spice_name, cb.shared_recipe_id, cb.quantity
         FROM cart_bowls cb
         JOIN menus m ON m.id = cb.menu_id
         JOIN soups s ON s.id = cb.soup_id
         LEFT JOIN spice_levels sp ON sp.id = cb.spice_level_id
         WHERE cb.$where
         ORDER BY cb.id" . ($forUpdate ? ' FOR UPDATE' : '')
    );
    $stmt->execute([$owner]);
    $rows = $stmt->fetchAll();

    // 具材をまとめて読む (bowl ごとに SQL を投げない)
    $itemsByBowl = [];
    if ($rows) {
        $ids = array_column($rows, 'id');
        $marks = implode(',', array_fill(0, count($ids), '?'));   // "?,?,?"
        $stmt = $pdo->prepare(
            "SELECT ci.cart_bowl_id, ci.ingredient_id, i.name, i.price, ci.action, ci.quantity
             FROM cart_bowl_ingredients ci
             JOIN ingredients i ON i.id = ci.ingredient_id
             WHERE ci.cart_bowl_id IN ($marks)
             ORDER BY ci.action, i.sort_order"
        );
        $stmt->execute($ids);
        foreach ($stmt->fetchAll() as $r) {
            $itemsByBowl[(int) $r['cart_bowl_id']][] = [
                'ingredient_id' => (int) $r['ingredient_id'],
                'name'          => $r['name'],
                'action'        => $r['action'],
                'quantity'      => (int) $r['quantity'],
                'price'         => (int) $r['price'],
            ];
        }
    }

    $bowls = [];
    $total = 0;
    $count = 0;
    $canCheckout = count($rows) > 0;
    foreach ($rows as $r) {
        $id    = (int) $r['id'];
        $items = $itemsByBowl[$id] ?? [];
        $qty   = (int) $r['quantity'];

        // 今の値段で買えるか (売り切れ・値段が変わった などもここで分かる)
        $check = bowl_price($pdo, bowl_from_row($r), $items, $qty);
        if ($check['error'] !== null) {
            $canCheckout = false;
        } else {
            $total += $check['price'];
        }
        $count += $qty;

        $bowls[] = [
            'id'             => $id,
            'menu_id'        => (int) $r['menu_id'],
            'menu_name'      => $r['menu_name'],
            'has_soup'       => (bool) $r['has_soup'],
            'soup_id'        => (int) $r['soup_id'],
            'soup_name'      => $r['soup_name'],
            'spice_level_id' => $r['spice_level_id'] === null ? null : (int) $r['spice_level_id'],
            'spice_name'     => $r['spice_name'],
            'shared_recipe_id' => $r['shared_recipe_id'] === null ? null : (int) $r['shared_recipe_id'],   // 友達のレシピから (Lin, FEAT-20)
            'quantity'       => $qty,
            'ingredients'    => $items,
            'price'          => $check['price'],
            'error'          => $check['error'],
        ];
    }

    return ['bowls' => $bowls, 'total' => $total, 'count' => $count, 'can_checkout' => $canCheckout];
}
