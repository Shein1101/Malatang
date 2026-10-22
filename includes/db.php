<?php
// includes/db.php — MySQL နဲ့ တစ်ခါပဲ ချိတ်တယ်။ Owner: Zan (task #10)
// API ဖိုင်တိုင်း: require __DIR__ . '/../includes/db.php';

// ချိတ်လို့မရရင် JSON error ပြန်ပြီး ရပ်တယ်
function db_fail(string $message): void {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'data' => null, 'error' => $message]);
    exit;
}

// 1. Session စတယ် (user_id, role, guest_token)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. .env ကို ဖတ်တယ် (password ကို code ထဲ မရေးရ)
$env = @parse_ini_file(__DIR__ . '/../.env');
if ($env === false) {
    db_fail('.env file not found');
}

// 3. ချိတ်တယ် (DB_PORT မရေးထားရင် 3306)
$port = $env['DB_PORT'] ?? '3306';
try {
    $pdo = new PDO(
        "mysql:host={$env['DB_HOST']};port={$port};dbname={$env['DB_NAME']};charset=utf8mb4",
        $env['DB_USER'],
        $env['DB_PASS'],
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // error ဆို ချက်ချင်းသိအောင်
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // $row['name'] လို ယူလို့ရအောင်
            PDO::ATTR_EMULATE_PREPARES   => false,                  // prepare() ကို အစစ်အတိုင်း
        ]
    );
} catch (PDOException $e) {
    error_log($e->getMessage());          // အသေးစိတ်ကို log ထဲပဲ ရေး (user ကို မပြ)
    db_fail('Database connection failed');
}
