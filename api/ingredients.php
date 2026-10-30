<?php
// api/ingredients.php — Ingredients API. Owner: Myat
//   GET   anyone → ingredients list
//   POST  admin  → add an ingredient
//   PUT   admin  → ?id=53 change (price / stock / weekly_stock / is_available)
//                  ?action=restock  all: stock = weekly_stock (new week)
//   DELETE admin → ?id=53 (is_deleted = 1)

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/api.php';
require __DIR__ . '/../includes/auth.php';   // require_admin() → 401 / 403

$method = require_method('GET', 'POST','PUT','DELETE');   // 🆕 POST and put ကိုပါ ခွင့်ပြုမယ်

// ========== GET: စာရင်းပြ ==========
if ($method === 'GET') {                    // 🆕 GET ဆိုရင်ပဲ ဒီအပိုင်း run မယ်
    $sql = 'SELECT i.id, i.name, i.price, i.stock, i.weekly_stock, i.is_available, i.image_url,
            c.id AS category_id, c.name AS category_name
            FROM ingredients i
            JOIN categories c ON c.id = i.category_id
            WHERE i.is_deleted = 0
            ORDER BY c.sort_order, i.sort_order';
    $rows = $pdo->query($sql)->fetchAll();

    $data = [];
    foreach ($rows as $r) {
        $data[] = [
            'id'            => (int) $r['id'],
            'name'          => $r['name'],
            'price'         => (int) $r['price'],
            'stock'         => (int) $r['stock'],
            'weekly_stock'  => (int) $r['weekly_stock'],   // 週の在庫 (売れた数 = weekly_stock - stock)
            'is_available'  => (bool) $r['is_available'],
            'image_url'     => $r['image_url'],
            'category_id'   => (int) $r['category_id'],
            'category_name' => $r['category_name'],
        ];
    }
    send_json($data);                       // send_json က ဒီမှာ ရပ်သွားပါတယ်
}

// ========== 🆕 ဒီအောက်က admin ပဲ လုပ်ခွင့်ရှိ ==========
require_admin();

// ========== 🆕 POST: ingredient အသစ်ထည့် ==========
if ($method === 'POST') {
    $in = read_json();                                  // ပို့လိုက်တဲ့ JSON ကို ဖတ်မယ်
    require_fields($in, ['name', 'price', 'category_id']);  // မပါရင် error

    // စျေးက 0 နဲ့အထက် ကိန်းပြည့် ဖြစ်ရမယ်
    $price = filter_var($in['price'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    if ($price === false) {
        send_error('Price must be a number 0 or more', 400);
    }

    // category တကယ်ရှိလား စစ်မယ်
    $check = $pdo->prepare('SELECT id FROM categories WHERE id = ?');
    $check->execute([$in['category_id']]);
    if (!$check->fetch()) {
        send_error('Category not found', 400);
    }

    // 週の在庫 (မပို့ရင် 100) — 0 နဲ့အထက် ကိန်းပြည့်
    $weekly = filter_var($in['weekly_stock'] ?? 100, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    if ($weekly === false) {
        send_error('Weekly stock must be a number 0 or more', 400);
    }

    // DB ထဲ ထည့်မယ် — ? နေရာမှာ တန်ဖိုးတွေ လုံခြုံစွာ ဝင်သွားမယ်
    $stmt = $pdo->prepare(
        'INSERT INTO ingredients (category_id, name, price, stock, weekly_stock, image_url, sort_order)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        (int) $in['category_id'],
        trim($in['name']),
        $price,
        (int) ($in['stock'] ?? 0),
        $weekly,
        $in['image_url'] ?? null,
        (int) ($in['sort_order'] ?? 0),
    ]);

    send_json(['id' => (int) $pdo->lastInsertId()], 201);   // အသစ်ရဲ့ id ကို ပြန်ပေးမယ်
}

// ========== 🆕 PUT: ingredient ပြင် (?id=53) ==========
if ($method === 'PUT') {
    // 🆕 ?action=restock — အပတ်သစ်: ingredient အားလုံးရဲ့ 残り (stock) ကို 週の在庫 (weekly_stock) အတိုင်း ပြန်ထား
    //    (stock 0 → 品切れ ဖြစ်နေတာတွေကို DB trigger က 販売中 ပြန်လုပ်ပေးမယ်)
    if (($_GET['action'] ?? '') === 'restock') {
        $stmt = $pdo->prepare('UPDATE ingredients SET stock = weekly_stock WHERE is_deleted = ?');
        $stmt->execute([0]);
        send_json(['restocked' => $stmt->rowCount()]);
    }

    // 1. URL ထဲက id ကို ယူမယ်  (ingredients.php?id=53 → 53)
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        send_error('Missing id', 400);
    }

    // 2. အဲဒီ ingredient ရှိလား စစ်မယ်
    $find = $pdo->prepare('SELECT id FROM ingredients WHERE id = ? AND is_deleted = 0');
    $find->execute([$id]);
    if (!$find->fetch()) {
        send_error('Ingredient not found', 404);
    }

    // 3. ပို့လိုက်တဲ့ field တွေကိုပဲ ပြင်မယ်
    $in = read_json();
    $sets = [];     // SQL ရဲ့ "price = ?" လို အပိုင်းတွေ
    $values = [];   // ? နေရာမှာ ဝင်မယ့် တန်ဖိုးတွေ

    if (isset($in['name'])) {
        $sets[] = 'name = ?';
        $values[] = trim($in['name']);
    }
    if (isset($in['price'])) {
        $price = filter_var($in['price'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if ($price === false) {
            send_error('Price must be a number 0 or more', 400);
        }
        $sets[] = 'price = ?';
        $values[] = $price;
    }
    if (isset($in['stock'])) {
        $stock = filter_var($in['stock'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if ($stock === false) {
            send_error('Stock must be a number 0 or more', 400);
        }
        $sets[] = 'stock = ?';
        $values[] = $stock;
    }
    if (isset($in['weekly_stock'])) {      // 🆕 週の在庫
        $weekly = filter_var($in['weekly_stock'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if ($weekly === false) {
            send_error('Weekly stock must be a number 0 or more', 400);
        }
        $sets[] = 'weekly_stock = ?';
        $values[] = $weekly;
    }
    if (isset($in['is_available'])) {
        $sets[] = 'is_available = ?';
        $values[] = $in['is_available'] ? 1 : 0;
    }

    if (count($sets) === 0) {
        send_error('Nothing to change', 400);
    }

    // 4. စျေးပြောင်းရင် price_history ကို DB trigger က အလိုအလျောက် ရေးပေးမယ်
    $pdo->prepare('SET @current_user_id = ?')->execute([$_SESSION['user_id'] ?? null]);

    // 5. UPDATE run မယ်  →  UPDATE ingredients SET price = ? WHERE id = ?
    $values[] = $id;
    $sql = 'UPDATE ingredients SET ' . implode(', ', $sets) . ' WHERE id = ?';
    $pdo->prepare($sql)->execute($values);

    send_json(['id' => $id, 'updated' => true]);
}

// ========== 🆕 DELETE: ingredient ဖျက် (?id=53) ==========
if ($method === 'DELETE') {
    // 1. URL ထဲက id ကို ယူမယ်
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        send_error('Missing id', 400);
    }

    // 2. တကယ်မဖျက်ဘဲ is_deleted = 1 လို့ပဲ မှတ်မယ် (အရင် order တွေ မပျက်အောင်)
    $stmt = $pdo->prepare('UPDATE ingredients SET is_deleted = 1 WHERE id = ? AND is_deleted = 0');
    $stmt->execute([$id]);

    // 3. ပြောင်းသွားတဲ့ row မရှိရင် = မရှိတဲ့ id (ဒါမှမဟုတ် ဖျက်ပြီးသား)
    if ($stmt->rowCount() === 0) {
        send_error('Ingredient not found', 404);
    }

    send_json(['id' => $id, 'deleted' => true]);
}