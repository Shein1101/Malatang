<?php
// api/favorites.php — お気に入り. Owner: Lin
// ログインしている人だけ。自分のお気に入りしか見えない・変えられない。
//
// ---------- お気に入りの具材 (?type=ingredient) ----------
// カスタマイズ画面で、よく入れる具材を上に出すため。
//   GET    ?type=ingredient                    → 一覧 (名前・値段・カテゴリ・売り切れ)
//   POST   ?type=ingredient { ingredient_id }  → 追加 (もう入っていても OK)
//   DELETE ?type=ingredient&ingredient_id=3    → 外す
//
// ---------- マイレシピ (type なし) = カスタムした bowl をまるごと保存 ----------
//   GET                         → 一覧 (今の値段つき。買えないときは unavailable に理由)
//   POST  { order_bowl_id, nickname? }                      → 注文履歴の bowl を保存
//   POST  { menu_id, has_soup, soup_id, spice_level_id,
//           items: [{ingredient_id, action, quantity}], nickname? } → カスタマイズ画面から保存
//   POST  ?action=cart&id=3     → お気に入りをカートに入れる
//   PUT   ?id=3 { nickname }    → 名前を変える
//   DELETE ?id=3                → 削除

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/api.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/bowl.php';

$method = require_method('GET', 'POST', 'PUT', 'DELETE');
require_login();
$user_id = (int) current_user_id();
$id      = (int) ($_GET['id'] ?? 0);

// ==================== お気に入りの具材 ====================
if (($_GET['type'] ?? '') === 'ingredient') {
    if ($method === 'GET') {
        $stmt = $pdo->prepare(
            'SELECT i.id, i.name, i.price, i.is_available, i.image_url,
                    c.id AS category_id, c.name AS category_name
             FROM favorite_ingredient_picks p
             JOIN ingredients i ON i.id = p.ingredient_id
             JOIN categories  c ON c.id = i.category_id
             WHERE p.user_id = ? AND i.is_deleted = 0
             ORDER BY c.sort_order, i.sort_order, i.id'
        );
        $stmt->execute([$user_id]);
        $data = [];
        foreach ($stmt->fetchAll() as $r) {
            $data[] = [
                'ingredient_id' => (int) $r['id'],
                'name'          => $r['name'],
                'price'         => (int) $r['price'],
                'is_available'  => (bool) $r['is_available'],
                'image_url'     => $r['image_url'],
                'category_id'   => (int) $r['category_id'],
                'category_name' => $r['category_name'],
            ];
        }
        send_json($data);
    }

    if ($method === 'POST') {
        $in = read_json();
        require_fields($in, ['ingredient_id']);
        $iid = (int) $in['ingredient_id'];

        $stmt = $pdo->prepare('SELECT id FROM ingredients WHERE id = ? AND is_deleted = 0');
        $stmt->execute([$iid]);
        if (!$stmt->fetch()) {
            send_error('具材が見つかりません', 404);
        }
        // 2回押しても1行だけ
        $stmt = $pdo->prepare('INSERT IGNORE INTO favorite_ingredient_picks (user_id, ingredient_id) VALUES (?, ?)');
        $stmt->execute([$user_id, $iid]);
        send_json(['ingredient_id' => $iid, 'added' => true], 201);
    }

    if ($method === 'DELETE') {
        $iid  = (int) ($_GET['ingredient_id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM favorite_ingredient_picks WHERE user_id = ? AND ingredient_id = ?');
        $stmt->execute([$user_id, $iid]);
        if ($stmt->rowCount() === 0) {
            send_error('お気に入りの具材が見つかりません', 404);
        }
        send_json(['ingredient_id' => $iid, 'deleted' => true]);
    }

    send_error('Method not allowed', 405);
}

// ==================== マイレシピ ====================

// 名前: 空なら null、50文字まで
function clean_nickname($v) {
    $v = trim((string) ($v ?? ''));
    if ($v === '') {
        return null;
    }
    if (mb_strlen($v) > 50) {
        send_error('名前は50文字以内で入力してください', 400);
    }
    return $v;
}

// お気に入りの具材 (key = favorite_id)
function favorite_items(PDO $pdo, int $user_id, ?int $favorite_id = null): array {
    $sql = 'SELECT fi.favorite_id, fi.ingredient_id, i.name, fi.action, fi.quantity
            FROM favorite_ingredients fi
            JOIN favorites f   ON f.id = fi.favorite_id
            JOIN ingredients i ON i.id = fi.ingredient_id
            WHERE f.user_id = ?' . ($favorite_id ? ' AND f.id = ?' : '') . '
            ORDER BY fi.action, i.sort_order, i.id';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($favorite_id ? [$user_id, $favorite_id] : [$user_id]);

    $byFavorite = [];
    foreach ($stmt->fetchAll() as $r) {
        $byFavorite[(int) $r['favorite_id']][] = [
            'ingredient_id' => (int) $r['ingredient_id'],
            'name'          => $r['name'],
            'action'        => $r['action'],
            'quantity'      => (int) $r['quantity'],
        ];
    }
    return $byFavorite;
}

// 自分のお気に入りを1つ (なければ 404)
function find_favorite(PDO $pdo, int $user_id, int $id): array {
    $stmt = $pdo->prepare('SELECT * FROM favorites WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, $user_id]);
    $row = $stmt->fetch();
    if (!$row) {
        send_error('お気に入りが見つかりません', 404);
    }
    return $row;
}

// ========== GET: 一覧 ==========
if ($method === 'GET') {
    $stmt = $pdo->prepare(
        'SELECT f.id, f.nickname, f.menu_id, m.name AS menu_name, m.image_url,
                f.has_soup, f.soup_id, s.name AS soup_name,
                f.spice_level_id, sp.level AS spice_level, sp.name AS spice_name,
                f.created_at
         FROM favorites f
         JOIN menus m              ON m.id = f.menu_id
         JOIN soups s              ON s.id = f.soup_id
         LEFT JOIN spice_levels sp ON sp.id = f.spice_level_id
         WHERE f.user_id = ?
         ORDER BY f.id DESC'
    );
    $stmt->execute([$user_id]);
    $itemsByFavorite = favorite_items($pdo, $user_id);

    $data = [];
    foreach ($stmt->fetchAll() as $r) {
        $fid   = (int) $r['id'];
        $items = $itemsByFavorite[$fid] ?? [];
        $check = bowl_price($pdo, bowl_from_row($r), $items);   // 今の値段

        $data[] = [
            'id'             => $fid,
            'nickname'       => $r['nickname'],
            'menu_id'        => (int) $r['menu_id'],
            'menu_name'      => $r['menu_name'],
            'image_url'      => $r['image_url'],
            'has_soup'       => (bool) $r['has_soup'],
            'soup_id'        => (int) $r['soup_id'],
            'soup_name'      => $r['soup_name'],
            'spice_level_id' => $r['spice_level_id'] === null ? null : (int) $r['spice_level_id'],
            'spice_level'    => $r['spice_level'] === null ? null : (int) $r['spice_level'],
            'spice_name'     => $r['spice_name'],
            'items'          => $items,
            'price'          => $check['price'],    // null = 今は買えない
            'unavailable'    => $check['error'],
            'created_at'     => $r['created_at'],
        ];
    }
    send_json($data);
}

$in = read_json();

// ========== POST ?action=cart: お気に入り → カート ==========
if ($method === 'POST' && ($_GET['action'] ?? '') === 'cart') {
    $fav   = find_favorite($pdo, $user_id, $id);
    $bowl  = bowl_from_row($fav);
    $items = favorite_items($pdo, $user_id, $id)[$id] ?? [];

    $check = bowl_price($pdo, $bowl, $items);
    if ($check['error'] !== null) {
        send_error($check['error'], 409);
    }
    $pdo->beginTransaction();
    $cart_bowl_id = add_bowl_to_cart($pdo, $user_id, $bowl, $items);
    $pdo->commit();
    send_json(['cart_bowl_id' => $cart_bowl_id, 'price' => $check['price']], 201);
}

// ========== POST: 保存 ==========
if ($method === 'POST') {
    $nickname = clean_nickname($in['nickname'] ?? null);

    if (isset($in['order_bowl_id'])) {
        // --- 注文履歴の bowl から (自分の注文だけ) ---
        $stmt = $pdo->prepare(
            'SELECT ob.menu_id, ob.has_soup, ob.soup_id, ob.spice_level_id
             FROM order_bowls ob
             JOIN orders o ON o.id = ob.order_id
             WHERE ob.id = ? AND o.user_id = ?'
        );
        $stmt->execute([(int) $in['order_bowl_id'], $user_id]);
        $row = $stmt->fetch();
        if (!$row) {
            send_error('注文が見つかりません', 404);
        }
        $bowl = bowl_from_row($row);
        if ($bowl['menu_id'] === null || $bowl['soup_id'] === null) {
            send_error('このメニューは今は販売していないため保存できません', 409);
        }

        $stmt = $pdo->prepare(
            'SELECT ingredient_id, action, SUM(quantity) AS quantity
             FROM order_items
             WHERE order_bowl_id = ? AND ingredient_id IS NOT NULL
             GROUP BY ingredient_id, action'
        );
        $stmt->execute([(int) $in['order_bowl_id']]);
        $items = $stmt->fetchAll();
    } else {
        // --- カスタマイズ画面から ---
        require_fields($in, ['menu_id', 'soup_id']);
        $bowl = [
            'menu_id'        => (int) $in['menu_id'],
            'has_soup'       => !array_key_exists('has_soup', $in) || (bool) $in['has_soup'],
            'soup_id'        => (int) $in['soup_id'],
            'spice_level_id' => empty($in['spice_level_id']) ? null : (int) $in['spice_level_id'],
        ];

        $items = [];
        $seen  = [];
        foreach ($in['items'] ?? [] as $it) {
            $iid    = (int) ($it['ingredient_id'] ?? 0);
            $action = $it['action'] ?? 'added';
            $qty    = (int) ($it['quantity'] ?? 1);
            if ($iid < 1 || !in_array($action, ['added', 'removed'], true) || $qty < 1 || isset($seen[$iid])) {
                send_error('Invalid items', 400);
            }
            $seen[$iid] = true;
            $items[] = ['ingredient_id' => $iid, 'action' => $action, 'quantity' => $qty];
        }

        // 存在しない menu・具材などはここで止める
        $check = bowl_price($pdo, $bowl, $items);
        if ($check['error'] !== null) {
            send_error($check['error'], 409);
        }
    }

    $pdo->beginTransaction();
    $stmt = $pdo->prepare(
        'INSERT INTO favorites (user_id, nickname, menu_id, has_soup, soup_id, spice_level_id)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $user_id, $nickname, $bowl['menu_id'], $bowl['has_soup'] ? 1 : 0,
        $bowl['soup_id'], $bowl['spice_level_id'],
    ]);
    $favorite_id = (int) $pdo->lastInsertId();

    $stmt = $pdo->prepare(
        'INSERT INTO favorite_ingredients (favorite_id, ingredient_id, action, quantity)
         VALUES (?, ?, ?, ?)'
    );
    foreach ($items as $it) {
        $stmt->execute([$favorite_id, $it['ingredient_id'], $it['action'], $it['quantity']]);
    }
    $pdo->commit();

    send_json(['id' => $favorite_id], 201);
}

// ========== PUT: 名前を変える ==========
if ($method === 'PUT') {
    find_favorite($pdo, $user_id, $id);
    $stmt = $pdo->prepare('UPDATE favorites SET nickname = ? WHERE id = ? AND user_id = ?');
    $stmt->execute([clean_nickname($in['nickname'] ?? null), $id, $user_id]);
    send_json(['id' => $id, 'updated' => true]);
}

// ========== DELETE ==========
find_favorite($pdo, $user_id, $id);
$stmt = $pdo->prepare('DELETE FROM favorites WHERE id = ? AND user_id = ?');   // 具材は CASCADE で消える
$stmt->execute([$id, $user_id]);
send_json(['id' => $id, 'deleted' => true]);
