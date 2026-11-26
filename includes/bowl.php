<?php
// includes/bowl.php — bowl 1つを「今の値段で買えるか」チェック + cart に入れる. Owner: Lin
// api/favorites.php (お気に入り → カート) と api/history.php (再注文) が使う。
// $bowl  = ['menu_id' => 1, 'has_soup' => true, 'soup_id' => 1, 'spice_level_id' => 4 or null]
// $items = [['ingredient_id' => 6, 'action' => 'added', 'quantity' => 2], ...]

// fn_bowl_price の英語 error → お客さん向けの日本語
function bowl_error_message(string $sqlError): string {
    $messages = [
        'Menu not found'                   => 'このメニューは今は販売していません',
        'Menu is sold out'                 => 'このメニューは売り切れです',
        'Soup not found'                   => 'この味は今は選べません',
        'Soup is sold out'                 => 'この味は売り切れです',
        'Spice level not found'            => 'この辛さは今は選べません',
        'Ingredient not found'             => '今は販売していない具材が入っています',
        'Ingredient is sold out'           => '売り切れの具材が入っています',
        'This ingredient cannot be removed' => '抜けない具材が入っています',
    ];
    foreach ($messages as $en => $ja) {
        if (strpos($sqlError, $en) !== false) {
            return $ja;
        }
    }
    return 'この内容では注文できません';
}

// 今の値段 (1杯 × $quantity)。買えないときは price = null, error = 理由
function bowl_price(PDO $pdo, array $bowl, array $items, int $quantity = 1): array {
    if ($bowl['menu_id'] === null) {
        return ['price' => null, 'error' => 'このメニューは今は販売していません'];
    }
    if ($bowl['soup_id'] === null) {
        return ['price' => null, 'error' => 'この味は今は選べません'];
    }

    // fn_bowl_price は辛さの ON/OFF を見ないので、ここでチェック
    if ($bowl['spice_level_id'] !== null) {
        $stmt = $pdo->prepare('SELECT is_available FROM spice_levels WHERE id = ?');
        $stmt->execute([$bowl['spice_level_id']]);
        $ok = $stmt->fetchColumn();
        if ($ok === false || !$ok) {
            return ['price' => null, 'error' => 'この辛さは今は選べません'];
        }
    }

    // 麺をえらぶ (例: 春雨 → トウモロコシ麺) [Myat]
    //   形は「元の麺 removed」+「えらんだ麺 added ×1以上」。元の麺が「外せません」でも、えらべる麺に かえるなら OK。
    //   値段は 差額だけ (高い麺 → +差額、安い麺 → ±0)。
    $swap = noodle_swap($pdo, (int) $bowl['menu_id'], $items);

    $json = [];
    foreach ($items as $it) {
        // 入れかえる元の麺 は fn_bowl_price に渡さない (渡すと「外せない具材」エラーになる)
        if ($swap && $it['action'] === 'removed' && (int) $it['ingredient_id'] === $swap['from_id']) {
            continue;
        }
        $json[] = ['id' => (int) $it['ingredient_id'], 'qty' => (int) $it['quantity'], 'action' => $it['action']];
    }

    try {
        $stmt = $pdo->prepare('SELECT fn_bowl_price(?, ?, ?, ?, ?)');
        $stmt->execute([
            $bowl['menu_id'], $bowl['soup_id'], $bowl['spice_level_id'], $quantity,
            json_encode($json),
        ]);
        $price = (int) $stmt->fetchColumn();
        // fn_bowl_price は えらんだ麺を まるまる「追加」として 足しているので、元の麺の分 (1つ分) を 引く = 差額だけ
        if ($swap) {
            $price -= min($swap['from_price'], $swap['to_price']) * $quantity;
        }

        // 「自分で作る」は 1杯 ○円以上 から注文できる (お店が admin/store.php で決める。0 = 制限なし) [Myat]
        // ここで止めるので、カートに入れる・カートから注文する・再注文 の どれでも 同じルールになる
        $min = custom_min_price($pdo, (int) $bowl['menu_id']);
        $perBowl = intdiv($price, max(1, $quantity));
        if ($min > 0 && $perBowl < $min) {
            return ['price' => $price, 'error' => '「自分で作る」は 1杯 ¥' . number_format($min) . ' 以上から注文できます（あと ¥' . number_format($min - $perBowl) . '）'];
        }
        return ['price' => $price, 'error' => null];
    } catch (PDOException $e) {
        // SIGNAL SQLSTATE '45000' = fn_bowl_price が「買えない」と言った
        if ($e->getCode() !== '45000') {
            throw $e;
        }
        return ['price' => null, 'error' => bowl_error_message($e->errorInfo[2] ?? '')];
    }
}

// えらべる麺 (この中なら どれにでも かえられる)。assets/js/customize.js の NOODLE_CHOICES と 同じにする。
const NOODLE_CHOICES = ['春雨', 'インスタント麺', 'トウモロコシ麺'];

// 麺の入れかえ か しらべる。入れかえなら [from_id, from_price, to_id, to_price]、ちがえば null。
//   入れかえ = メニューに最初から入っている「えらべる麺」が removed で、ほかの「えらべる麺」が added
function noodle_swap(PDO $pdo, int $menu_id, array $items): ?array {
    $removed = [];
    $added   = [];
    foreach ($items as $it) {
        if ($it['action'] === 'removed') $removed[] = (int) $it['ingredient_id'];
        else                             $added[]   = (int) $it['ingredient_id'];
    }
    if (!$removed || !$added) return null;

    $marks = implode(',', array_fill(0, count(NOODLE_CHOICES), '?'));
    // 元の麺: このメニューに 最初から入っている えらべる麺 で、removed になっているもの
    $stmt = $pdo->prepare(
        "SELECT i.id, i.price FROM menu_ingredients mi JOIN ingredients i ON i.id = mi.ingredient_id
         WHERE mi.menu_id = ? AND i.is_deleted = 0 AND i.name IN ($marks)"
    );
    $stmt->execute(array_merge([$menu_id], NOODLE_CHOICES));
    $from = null;
    foreach ($stmt->fetchAll() as $r) {
        if (in_array((int) $r['id'], $removed, true)) { $from = $r; break; }
    }
    if (!$from) return null;

    // かえる先: added の中の えらべる麺 (元の麺 いがい)
    $stmt = $pdo->prepare("SELECT id, price FROM ingredients WHERE is_deleted = 0 AND name IN ($marks)");
    $stmt->execute(NOODLE_CHOICES);
    foreach ($stmt->fetchAll() as $r) {
        if ((int) $r['id'] !== (int) $from['id'] && in_array((int) $r['id'], $added, true)) {
            return ['from_id' => (int) $from['id'], 'from_price' => (int) $from['price'],
                    'to_id' => (int) $r['id'], 'to_price' => (int) $r['price']];
        }
    }
    return null;
}

// 「自分で作る」メニューの 最低注文金額。自分で作る でなければ 0 (制限なし)。
// 1回のリクエストで 何回も呼ばれるので、DB は 1回だけ読む。
function custom_min_price(PDO $pdo, int $menu_id): int {
    static $min = null, $customIds = null;
    if ($min === null) {
        try {
            $min = (int) $pdo->query('SELECT min_custom_price FROM store_settings WHERE id = 1')->fetchColumn();
        } catch (PDOException $e) {
            $min = 0;   // まだ add-min-custom-price.sql を実行していない DB → 制限なし (エラーにしない)
        }
        $customIds = array_map('intval', $pdo->query('SELECT id FROM menus WHERE is_custom = 1')->fetchAll(PDO::FETCH_COLUMN));
    }
    return in_array($menu_id, $customIds, true) ? $min : 0;
}

// cart に bowl を1つ入れる (チェックは先に bowl_price でしておく)
// transaction は呼ぶ側でする (再注文で何杯もまとめて入れるため)
// $shared_recipe_id = 友達から届いたレシピ (api/shares.php) のとき、その id
function add_bowl_to_cart(PDO $pdo, int $user_id, array $bowl, array $items, int $quantity = 1, ?int $shared_recipe_id = null): int {
    $stmt = $pdo->prepare(
        'INSERT INTO cart_bowls (user_id, menu_id, has_soup, soup_id, spice_level_id, shared_recipe_id, quantity)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $user_id, $bowl['menu_id'], $bowl['has_soup'] ? 1 : 0,
        $bowl['soup_id'], $bowl['spice_level_id'], $shared_recipe_id, $quantity,
    ]);
    $cart_bowl_id = (int) $pdo->lastInsertId();

    $stmt = $pdo->prepare(
        'INSERT INTO cart_bowl_ingredients (cart_bowl_id, ingredient_id, action, quantity)
         VALUES (?, ?, ?, ?)'
    );
    foreach ($items as $it) {
        $stmt->execute([$cart_bowl_id, $it['ingredient_id'], $it['action'], $it['quantity']]);
    }
    return $cart_bowl_id;
}

// DB の行 → $bowl の形 (int / bool / null をそろえる)
// カスタマイズ画面から送られた bowl をチェックする (保存・シェアで同じ)
// 返す値: [$bowl, $items]。買えるかどうかは bowl_price で見る
function bowl_from_input(array $in): array {
    require_fields($in, ['menu_id', 'soup_id']);
    $bowl = [
        'menu_id'        => (int) $in['menu_id'],
        'has_soup'       => !array_key_exists('has_soup', $in) || (bool) $in['has_soup'],
        'soup_id'        => (int) $in['soup_id'],
        'spice_level_id' => empty($in['spice_level_id']) ? null : (int) $in['spice_level_id'],
    ];

    $list = $in['items'] ?? [];
    if (!is_array($list)) {
        send_error('Invalid items', 400);
    }
    $items = [];
    $seen  = [];
    foreach ($list as $it) {
        if (!is_array($it)) {
            send_error('Invalid items', 400);
        }
        $iid    = (int) ($it['ingredient_id'] ?? 0);
        $action = $it['action'] ?? 'added';
        $qty    = (int) ($it['quantity'] ?? 1);
        if ($iid < 1 || !in_array($action, ['added', 'removed'], true) || $qty < 1 || isset($seen[$iid])) {
            send_error('Invalid items', 400);
        }
        $seen[$iid] = true;
        $items[] = ['ingredient_id' => $iid, 'action' => $action, 'quantity' => $qty];
    }
    return [$bowl, $items];
}

function bowl_from_row(array $r): array {
    return [
        'menu_id'        => $r['menu_id'] === null ? null : (int) $r['menu_id'],
        'has_soup'       => (bool) $r['has_soup'],
        'soup_id'        => $r['soup_id'] === null ? null : (int) $r['soup_id'],
        'spice_level_id' => $r['spice_level_id'] === null ? null : (int) $r['spice_level_id'],
    ];
}
