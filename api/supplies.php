<?php
// api/supplies.php — 備品・消耗品 (塩・油・ティッシュ・スプーンなど). Owner: Myat
// 売り物ではないので、ぜんぶ admin だけ (お客さんは GET もできない)
//   GET              admin → 一覧 (削除したもの以外)
//   POST             admin → 追加  { category, name, unit, quantity, min_quantity, note }
//   PUT    ?id=3     admin → 変更  (送った項目だけ)
//   DELETE ?id=3     admin → 削除  (is_deleted = 1)

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/api.php';
require __DIR__ . '/../includes/auth.php';   // require_admin() → 401 / 403

$method = require_method('GET', 'POST', 'PUT', 'DELETE');
require_admin();

// カテゴリはこの 3 つだけ (画面の並び順もこの順)
const SUPPLY_CATEGORIES = ['調味料', '消耗品', '道具'];

// ========== GET: 一覧 ==========
if ($method === 'GET') {
    $sql = "SELECT id, category, name, unit, quantity, min_quantity, note, sort_order
            FROM supplies
            WHERE is_deleted = 0
            ORDER BY FIELD(category, '調味料', '消耗品', '道具'), sort_order, id";
    $data = [];
    foreach ($pdo->query($sql)->fetchAll() as $r) {
        $data[] = [
            'id'           => (int) $r['id'],
            'category'     => $r['category'],
            'name'         => $r['name'],
            'unit'         => $r['unit'],
            'quantity'     => (float) $r['quantity'],
            'min_quantity' => (float) $r['min_quantity'],
            'note'         => $r['note'],
            'sort_order'   => (int) $r['sort_order'],
        ];
    }
    send_json($data);
}

// 0 以上の数か (小数 OK: 1.5 kg など。小数点以下 1 けたまで)
function check_amount($value, string $label): float {
    $n = filter_var($value, FILTER_VALIDATE_FLOAT);
    if ($n === false || $n < 0 || $n > 999999999) {
        send_error("$label must be a number 0 or more", 400);
    }
    return round($n, 1);
}

// カテゴリが 3 つのどれかか
function check_category($value): string {
    if (!in_array($value, SUPPLY_CATEGORIES, true)) {
        send_error('Category must be 調味料 / 消耗品 / 道具', 400);
    }
    return $value;
}

// 空の文字は NULL にする (メモ用)
function text_or_null($value) {
    $v = trim((string) ($value ?? ''));
    return $v === '' ? null : $v;
}

// ========== POST: 追加 ==========
if ($method === 'POST') {
    $in = read_json();
    require_fields($in, ['category', 'name', 'unit']);
    if (trim($in['name']) === '' || trim($in['unit']) === '') {
        send_error('Name and unit are required', 400);
    }

    $stmt = $pdo->prepare(
        'INSERT INTO supplies (category, name, unit, quantity, min_quantity, note, sort_order)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        check_category($in['category']),
        trim($in['name']),
        trim($in['unit']),
        check_amount($in['quantity'] ?? 0, 'quantity'),
        check_amount($in['min_quantity'] ?? 0, 'min_quantity'),
        text_or_null($in['note'] ?? null),
        (int) ($in['sort_order'] ?? 0),
    ]);
    send_json(['id' => (int) $pdo->lastInsertId()], 201);
}

// ========== ここから下は ?id がいる ==========
$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    send_error('Missing id', 400);
}

// ========== PUT: 変更 (?id=3) ==========
if ($method === 'PUT') {
    $find = $pdo->prepare('SELECT id FROM supplies WHERE id = ? AND is_deleted = 0');
    $find->execute([$id]);
    if (!$find->fetch()) {
        send_error('Supply not found', 404);
    }

    $in = read_json();
    $sets = [];
    $values = [];
    if (isset($in['category']))     { $sets[] = 'category = ?';     $values[] = check_category($in['category']); }
    if (isset($in['name']))         { $sets[] = 'name = ?';         $values[] = trim($in['name']); }
    if (isset($in['unit']))         { $sets[] = 'unit = ?';         $values[] = trim($in['unit']); }
    if (isset($in['quantity']))     { $sets[] = 'quantity = ?';     $values[] = check_amount($in['quantity'], 'quantity'); }
    if (isset($in['min_quantity'])) { $sets[] = 'min_quantity = ?'; $values[] = check_amount($in['min_quantity'], 'min_quantity'); }
    if (array_key_exists('note', $in)) { $sets[] = 'note = ?';      $values[] = text_or_null($in['note']); }
    if (count($sets) === 0) {
        send_error('Nothing to change', 400);
    }
    if ((isset($in['name']) && trim($in['name']) === '') || (isset($in['unit']) && trim($in['unit']) === '')) {
        send_error('Name and unit are required', 400);
    }

    $values[] = $id;
    $pdo->prepare('UPDATE supplies SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($values);
    send_json(['id' => $id, 'updated' => true]);
}

// ========== DELETE: 削除 (?id=3) ==========
// ほかの表とつながっていないけど、まちがえて消しても戻せるように is_deleted = 1 にする
if ($method === 'DELETE') {
    $stmt = $pdo->prepare('UPDATE supplies SET is_deleted = 1 WHERE id = ? AND is_deleted = 0');
    $stmt->execute([$id]);
    if ($stmt->rowCount() === 0) {
        send_error('Supply not found', 404);
    }
    send_json(['id' => $id, 'deleted' => true]);
}
