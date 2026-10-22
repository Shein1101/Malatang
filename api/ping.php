<?php
// api/ping.php — db.php နဲ့ api.php အလုပ်လုပ်လား စစ်တယ်
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/api.php';

require_method('GET');
$count = $pdo->query('SELECT COUNT(*) FROM menus')->fetchColumn();
send_json(['db' => 'ok', 'menus' => (int) $count]);
