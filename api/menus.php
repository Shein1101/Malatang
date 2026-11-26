<?php
// api/menus.php — Menus API. Owner: Myat
//   GET          anyone → menu list
//   GET ?id=3    anyone → one menu + its default ingredients
//   GET ?custom=1 anyone → 自分で作る base menu (is_custom = 1), same shape as ?id (Lin)
//   POST         admin  → add a menu (春雨 goes in automatically)
//   PUT  ?id=3   admin  → change name / description / price / is_available / sort_order
//                         and/or ingredients: [{ingredient_id, quantity, is_removable}]
//                         (ingredients = the WHOLE new list of default ingredients)
//   DELETE ?id=3 admin  → is_deleted = 1

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/api.php';
require __DIR__ . '/../includes/auth.php';   // require_admin() → 401 / 403

// 写真の URL に「?v=ファイルを変えた時間」をつける (同じファイル名で 写真を入れかえても 古い写真が出ない) [Myat]
// api/ingredients.php の image_with_version と同じ考え方
function menu_image_url(?string $url): ?string {
    if (!$url || !str_starts_with($url, '../assets/')) {
        return $url;                     // 外の URL などは そのまま
    }
    $file = __DIR__ . '/' . $url;
    return is_file($file) ? $url . '?v=' . filemtime($file) : $url;
}

$method = require_method('GET', 'POST', 'PUT', 'DELETE');   // 🆕

// 🆕 最初から入っている具材が 品切れ の menu → { menu_id: ['ホタテ', …] }
//    その menu は お客さんには 売り切れ (DB の fn_bowl_price も注文を止める)
function soldout_ingredients(PDO $pdo): array {
    $rows = $pdo->query(
        'SELECT mi.menu_id, i.name
         FROM menu_ingredients mi
         JOIN ingredients i ON i.id = mi.ingredient_id
         WHERE i.is_deleted = 0 AND i.is_available = 0
         ORDER BY i.sort_order'
    )->fetchAll();
    $out = [];
    foreach ($rows as $r) {
        $out[(int) $r['menu_id']][] = $r['name'];
    }
    return $out;
}

// ========== GET ==========
//   is_on               = owner の 販売 スイッチ (admin/menus.php で使う)
//   soldout_ingredients = 品切れの具材の名前
//   is_available        = お客さんが注文できるか (is_on で、品切れの具材がない)
if ($method === 'GET') {
    $soldout = soldout_ingredients($pdo);

    // ---------- ?custom=1: 自分で作る の base menu → ?id と同じ答え (Lin) ----------
    if (isset($_GET['custom'])) {
        $id = $pdo->query('SELECT id FROM menus WHERE is_custom = 1 AND is_deleted = 0 ORDER BY id LIMIT 1')->fetchColumn();
        if ($id === false) {
            send_error('Menu not found', 404);
        }
        $_GET['id'] = $id;
    }

    // 🆕 ---------- ?id=3 があるとき: menu ひとつ + 具材 ----------
    if (isset($_GET['id'])) {
        $id = (int) $_GET['id'];

        // 1. menu をひとつ探す
        $stmt = $pdo->prepare(
            'SELECT id, name, description, price, image_url, is_available, is_custom, sort_order
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
            'SELECT i.id, i.name, i.price, i.portion, i.is_available,
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
                'portion'       => $r['portion'],   // 1つの量 (例: 50g)
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
            'image_url'    => menu_image_url($menu['image_url']),
            'is_on'        => (bool) $menu['is_available'],
            'soldout_ingredients' => $soldout[$id] ?? [],
            'is_available' => $menu['is_available'] && empty($soldout[$id]),
            'is_custom'    => (bool) $menu['is_custom'],
            'ingredients'  => $ingredients,
        ]);
    }

    // ---------- ?id がないとき: menu の一覧 (前と同じ) ----------
    $sql = 'SELECT id, name, description, price, image_url, is_available, is_custom, sort_order
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
            'image_url'    => menu_image_url($r['image_url']),
            'is_on'        => (bool) $r['is_available'],
            'soldout_ingredients' => $soldout[(int) $r['id']] ?? [],
            'is_available' => $r['is_available'] && empty($soldout[(int) $r['id']]),
            'is_custom'    => (bool) $r['is_custom'],      // TRUE = 自分で作る (menu.php はカードにしない)
            'sort_order'   => (int) $r['sort_order'],
        ];
    }
    send_json($data);
}  

// ========== 🆕 ここから下は admin だけ ==========
require_admin();

// 値段は 0 以上の整数か
function check_price($value): int {
    $p = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    if ($p === false) {
        send_error('Price must be a number 0 or more', 400);
    }
    return $p;
}

// ready-made menu の値段を「自分で作るで同じ中身を作ったときの値段」に合わせる:
//   price = (自分で作る base) + Σ(最初から入っている具材の値段 × 個数)
// menu.php のカードと カスタマイズ画面の値段が ずれないようにする (具材を編集したら呼ぶ)。
// メニューに入れても 値段に足さない「無料のトッピング」(自分で作る で 追加するときは ふつうに お金がかかる) [Myat]
// database/add-menu-price-sync.sql の リストと 同じにする
const FREE_GARNISH_SQL = "'パクチー'";

function sync_menu_price(PDO $pdo, int $menu_id): void {
    $base = (int) ($pdo->query('SELECT price FROM menus WHERE is_custom = 1 AND is_deleted = 0 ORDER BY id LIMIT 1')->fetchColumn() ?: 0);
    // 自分で作る base にもともと入っている具材 (春雨 など) は base に含まれているので 二重に数えない
    $stmt = $pdo->prepare(
        'SELECT COALESCE(SUM(i.price * mi.quantity), 0)
         FROM menu_ingredients mi JOIN ingredients i ON i.id = mi.ingredient_id
         WHERE mi.menu_id = ? AND i.is_deleted = 0
           AND i.name NOT IN (' . FREE_GARNISH_SQL . ')   -- パクチーは メニューでは無料 (値段に入れない) [Myat]
           AND mi.ingredient_id NOT IN (
               SELECT cmi.ingredient_id FROM menu_ingredients cmi
               JOIN menus cm ON cm.id = cmi.menu_id
               WHERE cm.is_custom = 1 AND cm.is_deleted = 0)'
    );
    $stmt->execute([$menu_id]);
    $sum = (int) $stmt->fetchColumn();
    $pdo->prepare('UPDATE menus SET price = ? WHERE id = ? AND is_custom = 0')->execute([$base + $sum, $menu_id]);
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

    // 🆕 最初から入っている具材 (ingredients があるときだけ。新しいリスト全部を送る)
    $items = null;
    if (array_key_exists('ingredients', $in)) {
        if (!is_array($in['ingredients'])) {
            send_error('ingredients must be a list', 400);
        }
        $items = [];
        $check = $pdo->prepare('SELECT id FROM ingredients WHERE id = ? AND is_deleted = 0');
        foreach ($in['ingredients'] as $it) {
            $ingId = filter_var($it['ingredient_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $qty   = filter_var($it['quantity'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($ingId === false || $qty === false) {
                send_error('Each ingredient needs an id and a quantity of 1 or more', 400);
            }
            if (isset($items[$ingId])) {
                send_error('The same ingredient is in the list twice', 400);
            }
            $check->execute([$ingId]);
            if (!$check->fetch()) {
                send_error('Ingredient not found', 400);
            }
            $items[$ingId] = [$qty, !empty($it['is_removable']) ? 1 : 0];
        }
    }

    if (count($sets) === 0 && $items === null) {
        send_error('Nothing to change', 400);
    }

    // menu と具材を一緒に変える: 全部成功するか、全部なかったことにする
    $pdo->beginTransaction();
    try {
        if (count($sets) > 0) {
            // 値段を変えたら price_history に残る (DB の trigger)。だれが変えたかを渡す
            $pdo->prepare('SET @current_user_id = ?')->execute([$_SESSION['user_id'] ?? null]);

            $values[] = $id;
            $pdo->prepare('UPDATE menus SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($values);
        }

        if ($items !== null) {
            // 前の具材を消して、新しいリストを入れる
            $pdo->prepare('DELETE FROM menu_ingredients WHERE menu_id = ?')->execute([$id]);
            $add = $pdo->prepare(
                'INSERT INTO menu_ingredients (menu_id, ingredient_id, quantity, is_removable) VALUES (?, ?, ?, ?)'
            );
            foreach ($items as $ingId => [$qty, $removable]) {
                $add->execute([$id, $ingId, $qty, $removable]);
            }
            // 具材が変わったので 値段を 作り方と同じに合わせる
            sync_menu_price($pdo, $id);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;   // includes/api.php が 500 にする
    }

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

