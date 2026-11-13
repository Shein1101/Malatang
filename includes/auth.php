<?php
// includes/auth.php — who is logged in? Owner: Lin (FEAT-16)
// Screens:   require_login() / require_admin() → redirect to the login page
// API files: same functions → JSON 401 / 403 (when includes/api.php is loaded)

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function current_user_id(){
    return $_SESSION['user_id'] ?? null;
}

function is_admin(){
    return current_user_id() !== null && ($_SESSION['role'] ?? null) === 'admin';
}

// API ထဲက ခေါ်တာလား (includes/api.php က send_error ကို ပေးတယ်)
function is_api_request(){
    return function_exists('send_error');
}

function require_login(){
    if(current_user_id() === null){
        if(is_api_request()){
            send_error('ログインしてください', 401);
        }
        // login ပြီးရင် ဒီစာမျက်နှာကို ပြန်လာဖို့ ?redirect= (Team Rules hand-off #7)
        $here = basename($_SERVER['SCRIPT_NAME']);
        if(!empty($_SERVER['QUERY_STRING'])){
            $here .= '?' . $_SERVER['QUERY_STRING'];
        }
        // public/ ကနေရော admin/ ကနေရော သွားလို့ရအောင် ../public/
        header('Location: ../public/login.php?redirect=' . urlencode(safe_redirect($here)));
        exit;
    }
}

// login / signup ပြီးရင် သွားမယ့်စာမျက်နှာ: ကိုယ့် site ရဲ့ public/ ထဲက .php နာမည်ပဲ ခွင့်ပြု
// ဥပမာ 'history.php', 'customize.php?favorite_id=3' → OK / 'https://evil.com', '../admin/x.php' → 'menu.php'
function safe_redirect($v){
    $v = (string) $v;
    if(preg_match('/^[a-z_]+\.php(\?[A-Za-z0-9_=&%-]*)?$/', $v) && is_file(__DIR__ . '/../public/' . strtok($v, '?'))){
        return $v;
    }
    return 'menu.php';
}

function guest_token(){
    if(empty($_SESSION['guest_token'])){
        $_SESSION['guest_token'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['guest_token'];
}

function require_admin(){
    if(!is_admin()){
        if(is_api_request()){
            send_error('管理者のみ利用できます', current_user_id() === null ? 401 : 403);
        }
        header('Location: ../admin/login.php');
        exit;
    }
}
