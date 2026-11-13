<?php
// api/reviews.php — お客さまの声 (reviews). Owner: Lin
//   GET                 anyone → { summary:{avg,count}, reviews:[...] }  (表示中のものだけ)
//   GET   ?all=1        admin  → 隠したものも全部 + user名
//   GET   ?mine=1       本人   → 自分のレビュー (なければ null)  ※フォームの初期値に使う
//   POST                会員   → { rating(1-5), comment } 1人1件 (あれば書き換え)
//   PUT   ?id=12        admin  → { is_visible }  隠す / 見せる
//   DELETE ?id=12       admin  → 消す
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/api.php';
require __DIR__ . '/../includes/auth.php';

$method = require_method('GET', 'POST', 'PUT', 'DELETE');

// 1件を画面用の形にする
function review_row(array $r, bool $admin = false): array {
    $out = [
        'id'         => (int) $r['id'],
        'name'       => $r['name'] ?: 'お客さま',
        'rating'     => (int) $r['rating'],
        'comment'    => $r['comment'],
        'created_at' => $r['created_at'],
    ];
    if ($admin) {
        $out['is_visible'] = (bool) $r['is_visible'];
        $out['user_id']    = (int) $r['user_id'];
    }
    return $out;
}

// ========== GET ==========
if ($method === 'GET') {
    // 自分のレビュー (フォームの初期値)
    if (isset($_GET['mine'])) {
        require_login();
        $stmt = $pdo->prepare('SELECT id, rating, comment, is_visible FROM reviews WHERE user_id = ?');
        $stmt->execute([current_user_id()]);
        $r = $stmt->fetch();
        send_json($r ? ['id' => (int) $r['id'], 'rating' => (int) $r['rating'], 'comment' => $r['comment'], 'is_visible' => (bool) $r['is_visible']] : null);
    }

    $all = isset($_GET['all']);
    if ($all) {
        require_admin();
    }
    $where = $all ? '' : 'WHERE r.is_visible = 1';
    $sql = "SELECT r.id, r.user_id, r.rating, r.comment, r.is_visible, r.created_at, u.name
            FROM reviews r JOIN users u ON u.id = r.user_id
            $where ORDER BY r.created_at DESC" . ($all ? '' : ' LIMIT 24');
    $rows = $pdo->query($sql)->fetchAll();
    $reviews = array_map(fn($r) => review_row($r, $all), $rows);

    // 平均と件数 (表示中だけ)
    $sum = $pdo->query('SELECT COUNT(*) c, COALESCE(AVG(rating),0) a FROM reviews WHERE is_visible = 1')->fetch();
    send_json([
        'summary' => ['count' => (int) $sum['c'], 'avg' => round((float) $sum['a'], 1)],
        'reviews' => $reviews,
    ]);
}

// ========== POST (会員) ==========
if ($method === 'POST') {
    require_login();
    $in = read_json();
    $rating = filter_var($in['rating'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 5]]);
    if ($rating === false) {
        send_error('評価は1〜5の星で選んでください', 400);
    }
    $comment = trim((string) ($in['comment'] ?? ''));
    if (mb_strlen($comment) > 300) {
        send_error('コメントは300文字以内で入力してください', 400);
    }
    $uid = current_user_id();
    // 1人1件: あれば書き換え (書き直すと もう一度 表示される)
    $stmt = $pdo->prepare('SELECT id FROM reviews WHERE user_id = ?');
    $stmt->execute([$uid]);
    $existing = $stmt->fetchColumn();
    if ($existing) {
        $pdo->prepare('UPDATE reviews SET rating = ?, comment = ?, is_visible = 1, created_at = NOW() WHERE id = ?')
            ->execute([$rating, $comment ?: null, $existing]);
        send_json(['id' => (int) $existing, 'updated' => true]);
    }
    $pdo->prepare('INSERT INTO reviews (user_id, rating, comment) VALUES (?, ?, ?)')
        ->execute([$uid, $rating, $comment ?: null]);
    send_json(['id' => (int) $pdo->lastInsertId(), 'created' => true], 201);
}

// ========== ここから admin ==========
require_admin();
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($id === false) {
    send_error('id が必要です', 400);
}

if ($method === 'PUT') {
    $in = read_json();
    if (!array_key_exists('is_visible', $in)) {
        send_error('is_visible が必要です', 400);
    }
    $stmt = $pdo->prepare('UPDATE reviews SET is_visible = ? WHERE id = ?');
    $stmt->execute([$in['is_visible'] ? 1 : 0, $id]);
    if ($stmt->rowCount() === 0) {
        // 値が同じでも「見つからない」とは限らないので存在確認
        $chk = $pdo->prepare('SELECT 1 FROM reviews WHERE id = ?');
        $chk->execute([$id]);
        if (!$chk->fetchColumn()) {
            send_error('レビューが見つかりません', 404);
        }
    }
    send_json(['id' => $id, 'is_visible' => (bool) $in['is_visible']]);
}

// DELETE
$stmt = $pdo->prepare('DELETE FROM reviews WHERE id = ?');
$stmt->execute([$id]);
if ($stmt->rowCount() === 0) {
    send_error('レビューが見つかりません', 404);
}
send_json(['id' => $id, 'deleted' => true]);
