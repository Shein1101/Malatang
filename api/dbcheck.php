<?php
// api/dbcheck.php — TEMPORARY. Finds the right MySQL settings on this PC. Delete after use. Never commit.
header('Content-Type: text/plain; charset=utf-8');

$env = @parse_ini_file(__DIR__ . '/../.env');
echo ".env: " . ($env === false ? "NOT FOUND or unreadable\n" : "found\n");
if ($env !== false) {
    foreach (['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER'] as $k) {
        echo "  $k = " . ($env[$k] ?? '(missing)') . "\n";
    }
    echo "  DB_PASS = " . (isset($env['DB_PASS']) ? ($env['DB_PASS'] === '' ? '(empty)' : '(set, ' . strlen($env['DB_PASS']) . ' chars)') : '(missing)') . "\n";
}
echo "\n";

$tries = [];
foreach (['localhost', '127.0.0.1'] as $h) {
    foreach (['3306', '8889'] as $p) {
        foreach (['root', ''] as $pw) {
            $tries[] = [$h, $p, $pw];
        }
    }
}
foreach ($tries as [$h, $p, $pw]) {
    $label = "host=$h port=$p user=root pass=" . ($pw === '' ? '(empty)' : 'root');
    try {
        $pdo = new PDO("mysql:host=$h;port=$p;charset=utf8mb4", 'root', $pw, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 2]);
        $dbs = $pdo->query('SHOW DATABASES')->fetchAll(PDO::FETCH_COLUMN);
        echo "OK    $label\n      databases: " . implode(', ', $dbs) . "\n";
    } catch (PDOException $e) {
        echo "FAIL  $label\n      " . $e->getMessage() . "\n";
    }
}
