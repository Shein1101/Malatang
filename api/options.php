<?php
// api/options.php — スープと辛さの選択肢. Owner: Myat
//   GET   anyone → { soups: [...], spice_levels: [...] }
// Zan の customize.php で「スープを選ぶ」「辛さを選ぶ」に使う

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/api.php';

$method = require_method('GET');

// ========== 1. スープ (売っているものだけ) ==========
$rows = $pdo->query(
    'SELECT id, name, description, price
     FROM soups
     WHERE is_available = 1
     ORDER BY sort_order, id'
)->fetchAll();

$soups = [];
foreach ($rows as $r) {
    $soups[] = [
        'id'          => (int) $r['id'],
        'name'        => $r['name'],
        'description' => $r['description'],
        'price'       => (int) $r['price'],        // 0 = 追加料金なし
    ];
}

// ========== 2. 辛さ (0 → 4 の順) ==========
$rows = $pdo->query(
    'SELECT id, name, level, extra_price
     FROM spice_levels
     ORDER BY level'
)->fetchAll();

$spice_levels = [];
foreach ($rows as $r) {
    $spice_levels[] = [
        'id'          => (int) $r['id'],
        'name'        => $r['name'],
        'level'       => (int) $r['level'],
        'extra_price' => (int) $r['extra_price'],  // 大辛・激辛は +円
    ];
}

// ========== 3. 2つをまとめて返す ==========
send_json([
    'soups'        => $soups,
    'spice_levels' => $spice_levels,
]);