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

    $stmt = $pdo->prepare('SELECT id, name, email, phone, postal_code, address, role FROM users WHERE id = ?');
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

case 'profile':
    // マイページ: 自分の名前・電話・住所を変える (ログインしている人だけ)
    require_method('PUT');
    require_login();
    $in = read_json();

    $sets = [];
    $values = [];
    if (array_key_exists('name', $in)) {
        $name = trim((string) $in['name']);
        if ($name === '' || mb_strlen($name) > 100) {
            send_error('名前は1〜100文字で入力してください', 400);
        }
        $sets[] = 'name = ?'; $values[] = $name;
    }
    if (array_key_exists('phone', $in)) {
        $phone = trim((string) $in['phone']);
        // 電話は必須 (空にはできない)
        if ($phone === '' || !preg_match('/^[0-9\-+]{10,20}$/', $phone)) {
            send_error('電話番号は数字とハイフンで入力してください', 400);
        }
        $sets[] = 'phone = ?'; $values[] = $phone;
    }
    if (array_key_exists('postal_code', $in)) {
        $postal = trim((string) $in['postal_code']);
        if (mb_strlen($postal) > 10) {
            send_error('郵便番号を確認してください', 400);
        }
        $sets[] = 'postal_code = ?'; $values[] = $postal === '' ? null : $postal;
    }
    if (array_key_exists('address', $in)) {
        $address = trim((string) $in['address']);
        if (mb_strlen($address) > 255) {
            send_error('住所は255文字以内で入力してください', 400);
        }
        $sets[] = 'address = ?'; $values[] = $address === '' ? null : $address;
    }
    if (count($sets) === 0) {
        send_error('変更するところがありません', 400);
    }
    $values[] = (int) current_user_id();
    $pdo->prepare('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($values);
    send_json(['updated' => true]);
    break;

case 'forgot':
    // パスワードをお忘れですか: メールアドレス → 再設定リンクを作る
    // option B: メールは送らず、画面にリンクを返す (ローカル・テスト用)
    require_method('POST');
    $in = read_json();
    require_fields($in, ['email']);
    $email = trim($in['email']);

    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // 本物のメール送信なら「登録があるか」は隠すが、画面に出す方式なので
    // 登録がなければ reset_path = null を返して画面でそれを伝える
    if (!$user) {
        send_json(['reset_path' => null]);
    }

    // 新しいリンクを作る前に、その人の未使用リンクを無効にする (有効なのは最新の1本だけ)
    $stmt = $pdo->prepare('DELETE FROM password_resets WHERE user_id = ? AND used_at IS NULL');
    $stmt->execute([(int)$user['id']]);

    $token      = bin2hex(random_bytes(32));            // リンクに入る秘密 (64文字)
    $token_hash = hash('sha256', $token);               // DB にはハッシュだけ保存 (元に戻せない)
    $expires    = date('Y-m-d H:i:s', time() + 3600);   // 1時間で期限切れ

    $stmt = $pdo->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, ?)');
    $stmt->execute([(int)$user['id'], $token_hash, $expires]);

    send_json(['reset_path' => 'reset.php?token=' . $token]);
    break;

case 'reset':
    // GET  ?token=   リンクがまだ有効か確かめる (reset.php がフォームを出す前)
    // POST {token, password}   新しいパスワードに変える
    $method = require_method('GET', 'POST');
    $in = ($method === 'POST') ? read_json() : [];
    $token = (string)($in['token'] ?? ($_GET['token'] ?? ''));
    if ($token === '') {
        send_error('リンクが正しくありません', 400);
    }
    $token_hash = hash('sha256', $token);

    $stmt = $pdo->prepare(
        'SELECT id, user_id FROM password_resets
         WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW()'
    );
    $stmt->execute([$token_hash]);
    $row = $stmt->fetch();

    if (!$row) {
        send_error('リンクが無効か、期限が切れています。もう一度やり直してください。', 400);
    }

    if ($method === 'GET') {
        send_json(['valid' => true]);
    }

    // POST: 新しいパスワード
    require_fields($in, ['password']);
    if (strlen($in['password']) < 8) {
        send_error('パスワードは8文字以上で入力してください', 400);
    }
    $new_hash = password_hash($in['password'], PASSWORD_DEFAULT);

    $pdo->beginTransaction();
    $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
    $stmt->execute([$new_hash, (int)$row['user_id']]);
    // このリンクは使用済みにする (二度は使えない)
    $stmt = $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = ?');
    $stmt->execute([(int)$row['id']]);
    $pdo->commit();

    send_json(['reset' => true]);
    break;

default:
    send_error('Invalid action', 400);
}
