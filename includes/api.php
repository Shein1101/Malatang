<?php
// includes/api.php — API အားလုံးအတွက် JSON helper (Team Rules format). Owner: Zan
// API ဖိုင်တိုင်း: require __DIR__ . '/../includes/api.php';

header('Content-Type: application/json; charset=utf-8');

// အောင်မြင်ရင်: { "success": true, "data": ..., "error": null }
function send_json($data = null, int $status = 200): void {
    http_response_code($status);
    echo json_encode(['success' => true, 'data' => $data, 'error' => null], JSON_UNESCAPED_UNICODE);
    exit;
}

// မှားရင်: { "success": false, "data": null, "error": "..." }
function send_error(string $message, int $status = 400): void {
    http_response_code($status);
    echo json_encode(['success' => false, 'data' => null, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

// callApi() က ပို့တဲ့ JSON body ကို array အဖြစ် ဖတ်တယ်
function read_json(): array {
    $raw = file_get_contents('php://input');
    if ($raw === '' || $raw === false) {
        return [];
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        send_error('Invalid JSON', 400);
    }
    return $data;
}

// လိုအပ်တဲ့ field မပါရင် 400 error
// ဥပမာ: require_fields($in, ['email', 'password']);
function require_fields(array $in, array $fields): void {
    foreach ($fields as $f) {
        if (!isset($in[$f]) || $in[$f] === '') {
            send_error("Missing field: $f", 400);
        }
    }
}

// ခွင့်ပြုထားတဲ့ method မဟုတ်ရင် 405 error
// ဥပမာ: $method = require_method('GET', 'POST');
function require_method(string ...$methods): string {
    $m = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (!in_array($m, $methods, true)) {
        send_error('Method not allowed', 405);
    }
    return $m;
}

// မမျှော်လင့်တဲ့ error တက်ရင် HTML error အစား JSON 500 ပြန်တယ်
set_exception_handler(function (Throwable $e) {
    error_log($e->getMessage());
    send_error('Server error', 500);
});
