<?php
// API: /api/auth.php?action=signup|login|logout|me — Owner: Lin (FEAT-16)

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/auth.php';

$action = $_GET['action'] ?? '';

// login / signup အောင်မြင်ရင် session ထဲ user ကို သိမ်း
function start_user_session(PDO $pdo, int $user_id, string $role): void {
    $guest = $_SESSION['guest_token'] ?? null;

    session_regenerate_id(true);   // session fixation ကာကွယ်
    $_SESSION['user_id'] = $user_id;
    $_SESSION['role']    = $role;

    // guest ဘဝက cart ကို user ဆီ ရွှေ့
    if ($guest !== null) {
        $stmt = $pdo->prepare('UPDATE cart_bowls SET user_id = ?, guest_token = NULL WHERE guest_token = ?');
        $stmt->execute([$user_id, $guest]);
        unset($_SESSION['guest_token']);
    }
}

switch ($action) {
case 'login':
    require_method('POST');

    $in = read_json();
    require_fields($in, ['email', 'password']);

    $stmt = $pdo->prepare(
        'SELECT id, name, email, password_hash, role
        FROM users
        WHERE email = ?'
    );
    $stmt->execute([trim($in['email'])]);
    $user = $stmt->fetch();

    // email မရှိ / password မှား — ဘယ်ဟာမှားလဲ မပြော
    if (!$user || !password_verify($in['password'], $user['password_hash'])) {
        send_error('メールアドレスまたはパスワードが正しくありません', 401);
    }

    start_user_session($pdo, (int)$user['id'], $user['role']);

    send_json([
        'id'   => (int)$user['id'],
        'name' => $user['name'],
        'role' => $user['role']
    ]);
    break;

case 'signup':
    require_method('POST');

    $in = read_json();
    require_fields($in, ['name', 'email', 'phone', 'password']);

    $name     = trim($in['name']);
    $email    = trim($in['email']);
    $phone    = trim($in['phone']);
    $password = $in['password'];

    // input စစ် (table column အရွယ်အစားနဲ့ ကိုက်အောင်)
    if ($name === '' || mb_strlen($name) > 100) {
        send_error('名前は1〜100文字で入力してください', 400);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
        send_error('メールアドレスを正しく入力してください', 400);
    }
    if (!preg_match('/^[0-9+\-() ]{6,20}$/', $phone)) {
        send_error('電話番号を正しく入力してください', 400);
    }
    if (strlen($password) < 8) {
        send_error('パスワードは8文字以上で入力してください', 400);
    }

    //userရှိပြီးသားလားစစ်/user ရှိရင် error ပြ
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        send_error('このメールアドレスは既に登録されています', 409);
    }

    //password ကိုhashပြောင်းသိမ်း
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    //မရှိရင် user အသစ်ထည့်
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO users (email, password_hash, name, phone, role)
            VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$email, $password_hash, $name, $phone, 'customer']);
    } catch (PDOException $e) {
        // တစ်ပြိုင်နက် signup နှစ်ခု — UNIQUE(email) က ဖမ်းတယ်
        if ($e->getCode() === '23000') {
            send_error('このメールアドレスは既に登録されています', 409);
        }
        throw $e;
    }

    //user id ပြန်တောင်း
    $user_id = (int)$pdo->lastInsertId();

    //account done --> log the user in
    start_user_session($pdo, $user_id, 'customer');

    send_json([
        'id'   => $user_id,
        'name' => $name,
        'role' => 'customer'
    ], 201);
    break;

case 'logout':
    require_method('POST');

    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();

    send_json(null);
    break;

case 'me':
    require_method('GET');

    // guest ဆို null ပြန်
    if (current_user_id() === null) {
        send_json(null);
    }

    $stmt = $pdo->prepare('SELECT id, name, email, phone, role FROM users WHERE id = ?');
    $stmt->execute([current_user_id()]);
    $user = $stmt->fetch();

    // user ဖျက်ခံရပြီးသား — session ကို ရှင်း
    if (!$user) {
        $_SESSION = [];
        send_json(null);
    }

    $user['id'] = (int)$user['id'];
    send_json($user);
    break;

default:
    send_error('Invalid action', 400);
}
