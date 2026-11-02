<?php
// api/shares.php — ファミリー&フレンズ: レシピをシェア. Owner: Lin (FEAT-20)
// 友達にシェアしよう → 友達に届く！ → 同じ内容で注文
// ログインしている人だけ。送った人・受け取った人しか見えない。
//   GET                         → 届いたレシピ (新しい順、今の値段つき)。読んだら new → seen
//   GET    ?box=sent            → 送ったレシピ (相手が見たか・注文したか分かる)
//   POST   { friend_id, favorite_id | order_bowl_id, message? }  → 友達にシェア
//   POST   ?action=cart&id=5    → 届いたレシピをカートに入れる (同じ内容で注文)
//                                  注文すると order_bowls.shared_recipe_id が入り、trigger で status = ordered
//   DELETE ?id=5                → 削除 (送った人・受け取った人どちらでも)

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/api.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/bowl.php';

$method = require_method('GET', 'POST', 'DELETE');
require_login();
$user_id = (int) current_user_id();
$id      = (int) ($_GET['id'] ?? 0);

// シェアの具材 (key = share_id)
function share_items(PDO $pdo, array $share_ids): array {
    if (!$share_ids) {
        return [];
    }
    $marks = implode(',', array_fill(0, count($share_ids), '?'));
    $stmt = $pdo->prepare(
        "SELECT ri.share_id, ri.ingredient_id, i.name, ri.action, ri.quantity
        FROM recipe_share_ingredients ri
        JOIN ingredients i ON i.id = ri.ingredient_id
        WHERE ri.share_id IN ($marks)
        ORDER BY ri.action, i.sort_order, i.id"
    );
    $stmt->execute($share_ids);
    $byShare = [];
    foreach ($stmt->fetchAll() as $r) {
        $byShare[(int) $r['share_id']][] = [
            'ingredient_id' => (int) $r['ingredient_id'],
            'name'          => $r['name'],
            'action'        => $r['action'],
            'quantity'      => (int) $r['quantity'],
        ];
    }
    return $byShare;
}

// ========== GET: 届いたレシピ / 送ったレシピ ==========
if ($method === 'GET') {
    $sent = ($_GET['box'] ?? '') === 'sent';

    // 相手の名前: 自分のリストで付けた呼び名があればそれ
    $sql = 'SELECT rs.id, rs.sender_id, rs.receiver_id, COALESCE(f.nickname, u.name) AS person_name,
                   rs.menu_id, m.name AS menu_name, m.image_url, rs.has_soup, rs.soup_id, s.name AS soup_name,
                   rs.spice_level_id, sp.level AS spice_level, sp.name AS spice_name,
                   rs.message, rs.status, rs.created_at
            FROM recipe_shares rs
            JOIN menus m              ON m.id = rs.menu_id
            JOIN soups s              ON s.id = rs.soup_id
            LEFT JOIN spice_levels sp ON sp.id = rs.spice_level_id '
         . ($sent
            ? 'JOIN users u        ON u.id = rs.receiver_id
               LEFT JOIN friends f ON f.user_id = rs.sender_id AND f.friend_id = rs.receiver_id
               WHERE rs.sender_id = ?'
            : 'JOIN users u        ON u.id = rs.sender_id
               LEFT JOIN friends f ON f.user_id = rs.receiver_id AND f.friend_id = rs.sender_id
               WHERE rs.receiver_id = ?')
         . ' ORDER BY rs.created_at DESC, rs.id DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    $rows = $stmt->fetchAll();
    $itemsByShare = share_items($pdo, array_map('intval', array_column($rows, 'id')));

    $data = [];
    foreach ($rows as $r) {
        $sid   = (int) $r['id'];
        $items = $itemsByShare[$sid] ?? [];
        $check = bowl_price($pdo, bowl_from_row($r), $items);   // 今の値段
        $data[] = [
            'id'             => $sid,
            'person_id'      => (int) ($sent ? $r['receiver_id'] : $r['sender_id']),
            'person_name'    => $r['person_name'],       // 届いた: 送った人 / 送った: 受け取った人
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
            'message'        => $r['message'],
            'status'         => $r['status'],            // new / seen / ordered
            'price'          => $check['price'],         // null = 今は買えない
            'unavailable'    => $check['error'],
            'created_at'     => $r['created_at'],
        ];
    }

    // 受け取った人が開いた → 既読 (返すデータは new のままなので、画面で NEW を1回出せる)
    if (!$sent) {
        $stmt = $pdo->prepare("UPDATE recipe_shares SET status = 'seen' WHERE receiver_id = ? AND status = 'new'");
        $stmt->execute([$user_id]);
    }
    send_json($data);
}

// ========== DELETE ==========
if ($method === 'DELETE') {
    $stmt = $pdo->prepare('DELETE FROM recipe_shares WHERE id = ? AND (sender_id = ? OR receiver_id = ?)');
    $stmt->execute([$id, $user_id, $user_id]);   // 具材は CASCADE、注文の shared_recipe_id は NULL になる
    if ($stmt->rowCount() === 0) {
        send_error('レシピが見つかりません', 404);
    }
    send_json(['id' => $id, 'deleted' => true]);
}

// ========== POST ?action=cart: 同じ内容で注文 (届いたレシピ → カート) ==========
if (($_GET['action'] ?? '') === 'cart') {
    $stmt = $pdo->prepare('SELECT * FROM recipe_shares WHERE id = ? AND receiver_id = ?');
    $stmt->execute([$id, $user_id]);
    $share = $stmt->fetch();
    if (!$share) {
        send_error('レシピが見つかりません', 404);
    }
    $bowl  = bowl_from_row($share);
    $items = share_items($pdo, [$id])[$id] ?? [];

    $check = bowl_price($pdo, $bowl, $items);
    if ($check['error'] !== null) {
        send_error($check['error'], 409);
    }
    $pdo->beginTransaction();
    $cart_bowl_id = add_bowl_to_cart($pdo, $user_id, $bowl, $items, 1, $id);
    $pdo->commit();
    send_json(['cart_bowl_id' => $cart_bowl_id, 'price' => $check['price']], 201);
}

// ========== POST: 友達にシェア ==========
$in = read_json();
require_fields($in, ['friend_id']);
$friend_id = (int) $in['friend_id'];

$message = trim((string) ($in['message'] ?? ''));
if (mb_strlen($message) > 200) {
    send_error('メッセージは200文字以内で入力してください', 400);
}

$stmt = $pdo->prepare('SELECT 1 FROM friends WHERE user_id = ? AND friend_id = ?');
$stmt->execute([$user_id, $friend_id]);
if (!$stmt->fetch()) {
    send_error('友達リストにいる人にだけシェアできます', 403);
}

if (isset($in['favorite_id'])) {
    // --- マイレシピから (自分のだけ) ---
    $stmt = $pdo->prepare('SELECT menu_id, has_soup, soup_id, spice_level_id FROM favorites WHERE id = ? AND user_id = ?');
    $stmt->execute([(int) $in['favorite_id'], $user_id]);
    $row = $stmt->fetch();
    if (!$row) {
        send_error('お気に入りが見つかりません', 404);
    }
    $stmt = $pdo->prepare('SELECT ingredient_id, action, quantity FROM favorite_ingredients WHERE favorite_id = ?');
    $stmt->execute([(int) $in['favorite_id']]);
} elseif (isset($in['order_bowl_id'])) {
    // --- 注文履歴から (自分の注文だけ) ---
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
    $stmt = $pdo->prepare(
        'SELECT ingredient_id, action, SUM(quantity) AS quantity
        FROM order_items
        WHERE order_bowl_id = ? AND ingredient_id IS NOT NULL
        GROUP BY ingredient_id, action'
    );
    $stmt->execute([(int) $in['order_bowl_id']]);
} else {
    send_error('Missing field: favorite_id or order_bowl_id', 400);
}
$items = $stmt->fetchAll();
$bowl  = bowl_from_row($row);

// 相手が注文できない内容 (販売終了・売り切れなど) は送らない
$check = bowl_price($pdo, $bowl, $items);
if ($check['error'] !== null) {
    send_error('今は注文できない内容なのでシェアできません（' . $check['error'] . '）', 409);
}

$pdo->beginTransaction();
$stmt = $pdo->prepare(
    'INSERT INTO recipe_shares (sender_id, receiver_id, menu_id, has_soup, soup_id, spice_level_id, message)
    VALUES (?, ?, ?, ?, ?, ?, ?)'
);
$stmt->execute([
    $user_id, $friend_id, $bowl['menu_id'], $bowl['has_soup'] ? 1 : 0,
    $bowl['soup_id'], $bowl['spice_level_id'], $message === '' ? null : $message,
]);
$share_id = (int) $pdo->lastInsertId();

$stmt = $pdo->prepare(
    'INSERT INTO recipe_share_ingredients (share_id, ingredient_id, action, quantity) VALUES (?, ?, ?, ?)'
);
foreach ($items as $it) {
    $stmt->execute([$share_id, $it['ingredient_id'], $it['action'], $it['quantity']]);
}
$pdo->commit();

send_json(['id' => $share_id, 'price' => $check['price']], 201);
