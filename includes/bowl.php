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

    $json = [];
    foreach ($items as $it) {
        $json[] = ['id' => (int) $it['ingredient_id'], 'qty' => (int) $it['quantity'], 'action' => $it['action']];
    }

    try {
        $stmt = $pdo->prepare('SELECT fn_bowl_price(?, ?, ?, ?, ?)');
        $stmt->execute([
            $bowl['menu_id'], $bowl['soup_id'], $bowl['spice_level_id'], $quantity,
            json_encode($json),
        ]);
        return ['price' => (int) $stmt->fetchColumn(), 'error' => null];
    } catch (PDOException $e) {
        // SIGNAL SQLSTATE '45000' = fn_bowl_price が「買えない」と言った
        if ($e->getCode() !== '45000') {
            throw $e;
        }
        return ['price' => null, 'error' => bowl_error_message($e->errorInfo[2] ?? '')];
    }
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
function bowl_from_row(array $r): array {
    return [
        'menu_id'        => $r['menu_id'] === null ? null : (int) $r['menu_id'],
        'has_soup'       => (bool) $r['has_soup'],
        'soup_id'        => $r['soup_id'] === null ? null : (int) $r['soup_id'],
        'spice_level_id' => $r['spice_level_id'] === null ? null : (int) $r['spice_level_id'],
    ];
}
