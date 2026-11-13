<?php
// api/friends.php — ファミリー&フレンズ: 友達リスト. Owner: Lin (FEAT-20)
// ログインしている人だけ。自分のリストしか見えない・変えられない。
// レシピを送れるのは、このリストにいる人だけ (DB の trigger trg_recipe_shares_only_friends もチェック)。
//   GET                                          → 友達リスト
//   POST   { email, relation?, nickname? }        → メールアドレスで追加 (relation = friend | family)
//   PUT    ?friend_id=3 { relation, nickname }    → 呼び名・関係を変える
//   DELETE ?friend_id=3                           → リストから外す

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/api.php';
require __DIR__ . '/../includes/auth.php';

$method = require_method('GET', 'POST', 'PUT', 'DELETE');
require_login();
$user_id   = (int) current_user_id();
$friend_id = (int) ($_GET['friend_id'] ?? 0);

// 呼び名: 空なら null、50文字まで
function clean_friend_nickname($v) {
    $v = trim((string) ($v ?? ''));
    if ($v === '') {
        return null;
    }
    if (mb_strlen($v) > 50) {
        send_error('呼び名は50文字以内で入力してください', 400);
    }
    return $v;
}

function clean_relation($v) {
    $v = $v ?? 'friend';
    if (!in_array($v, ['friend', 'family'], true)) {
        send_error('関係は「友達」か「家族」を選んでください', 400);
    }
    return $v;
}

// 自分のリストにいるか (いなければ 404)
function find_friend(PDO $pdo, int $user_id, int $friend_id): void {
    $stmt = $pdo->prepare('SELECT 1 FROM friends WHERE user_id = ? AND friend_id = ?');
    $stmt->execute([$user_id, $friend_id]);
    if (!$stmt->fetch()) {
        send_error('友達が見つかりません', 404);
    }
}

// ========== GET: 一覧 ==========
if ($method === 'GET') {
    $stmt = $pdo->prepare(
        'SELECT f.friend_id, u.name, f.nickname, f.relation, f.created_at
        FROM friends f
        JOIN users u ON u.id = f.friend_id
        WHERE f.user_id = ?
        ORDER BY f.relation DESC, COALESCE(f.nickname, u.name)'   // 家族 → 友達
    );
    $stmt->execute([$user_id]);
    $data = [];
    foreach ($stmt->fetchAll() as $r) {
        $data[] = [
            'friend_id'  => (int) $r['friend_id'],
            'name'       => $r['name'],               // 相手が登録した名前
            'nickname'   => $r['nickname'],           // 自分が付けた呼び名 (null あり)
            'relation'   => $r['relation'],
            'created_at' => $r['created_at'],
        ];
    }
    send_json($data);
}

$in = read_json();

// ========== POST: メールアドレスで追加 ==========
if ($method === 'POST') {
    require_fields($in, ['email']);
    $email    = trim((string) $in['email']);
    $relation = clean_relation($in['relation'] ?? null);
    $nickname = clean_friend_nickname($in['nickname'] ?? null);

    $stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = ? AND role = 'customer'");
    $stmt->execute([$email]);
    $friend = $stmt->fetch();
    if (!$friend) {
        send_error('このメールアドレスの会員は見つかりません', 404);
    }
    $fid = (int) $friend['id'];
    if ($fid === $user_id) {
        send_error('自分は追加できません', 400);
    }

    $stmt = $pdo->prepare('SELECT 1 FROM friends WHERE user_id = ? AND friend_id = ?');
    $stmt->execute([$user_id, $fid]);
    if ($stmt->fetch()) {
        send_error('もう友達リストにいます', 409);
    }

    $stmt = $pdo->prepare('INSERT INTO friends (user_id, friend_id, relation, nickname) VALUES (?, ?, ?, ?)');
    $stmt->execute([$user_id, $fid, $relation, $nickname]);
    send_json(['friend_id' => $fid, 'name' => $friend['name'], 'relation' => $relation, 'nickname' => $nickname], 201);
}

// ========== PUT: 呼び名・関係を変える ==========
if ($method === 'PUT') {
    find_friend($pdo, $user_id, $friend_id);
    $stmt = $pdo->prepare('UPDATE friends SET relation = ?, nickname = ? WHERE user_id = ? AND friend_id = ?');
    $stmt->execute([
        clean_relation($in['relation'] ?? null), clean_friend_nickname($in['nickname'] ?? null),
        $user_id, $friend_id,
    ]);
    send_json(['friend_id' => $friend_id, 'updated' => true]);
}

// ========== DELETE ==========
// 送ったレシピ・届いたレシピは消さない (相手の受信箱から勝手に消えないように)
find_friend($pdo, $user_id, $friend_id);
$stmt = $pdo->prepare('DELETE FROM friends WHERE user_id = ? AND friend_id = ?');
$stmt->execute([$user_id, $friend_id]);
send_json(['friend_id' => $friend_id, 'deleted' => true]);
