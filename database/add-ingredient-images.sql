-- database/add-ingredient-images.sql  [Myat] (2026-11)
-- 具材の写真をつなぐ + 写真はあるのに具材がないもの を追加する。
-- 写真は assets/images/ingredients/ にあります (git に入っています)。
-- phpMyAdmin で「Malatang」DB を選んで、1回だけ実行する。何回実行しても同じ結果になります。
-- チーム: Lin / Zan / Khant も一度だけ実行してください。

-- 1. ある具材に 写真をつなぐ (名前で探すので id がちがう DB でも OK)
UPDATE ingredients SET image_url = CASE name
    WHEN 'もやし'       THEN '../assets/images/ingredients/bean-sprouts.jpg'
    WHEN '白菜'         THEN '../assets/images/ingredients/napa-cabbage.jpg'
    WHEN 'チンゲン菜'   THEN '../assets/images/ingredients/bok-choy.jpg'
    WHEN 'ほうれん草'   THEN '../assets/images/ingredients/spinach.jpg'
    WHEN 'ニラ'         THEN '../assets/images/ingredients/nira.jpg'
    WHEN 'レタス'       THEN '../assets/images/ingredients/lettuce.jpg'
    WHEN 'じゃがいも'   THEN '../assets/images/ingredients/potato.jpg'
    WHEN 'さつまいも'   THEN '../assets/images/ingredients/sweet-potato.jpg'
    WHEN 'ブロッコリー' THEN '../assets/images/ingredients/broccoli.jpg'
    WHEN 'きくらげ'     THEN '../assets/images/ingredients/kikurage.jpg'
    WHEN 'えのき'       THEN '../assets/images/ingredients/enoki.jpg'
    WHEN 'しいたけ'     THEN '../assets/images/ingredients/shiitake.jpg'
    WHEN 'エリンギ'     THEN '../assets/images/ingredients/eringi.jpg'
    WHEN 'まいたけ'     THEN '../assets/images/ingredients/maitake.jpg'
    WHEN '豚バラ'       THEN '../assets/images/ingredients/pork-belly.jpg'
    WHEN '牛肉'         THEN '../assets/images/ingredients/beef.jpg'
    WHEN '羊肉'         THEN '../assets/images/ingredients/lamb.jpg'
    WHEN '鶏もも肉'     THEN '../assets/images/ingredients/chicken-breast.jpg'
    WHEN 'ウインナー'   THEN '../assets/images/ingredients/sausage.jpg'
    WHEN 'エビ'         THEN '../assets/images/ingredients/shrimp.jpg'
    WHEN 'イカ'         THEN '../assets/images/ingredients/squid.jpg'
    WHEN 'ホタテ'       THEN '../assets/images/ingredients/scallop.jpg'
    WHEN 'カニカマ'     THEN '../assets/images/ingredients/kanikama.jpg'
    ELSE image_url END
WHERE name IN ('もやし','白菜','チンゲン菜','ほうれん草','ニラ','レタス','じゃがいも','さつまいも',
               'ブロッコリー','きくらげ','えのき','しいたけ','エリンギ','まいたけ','豚バラ','牛肉',
               '羊肉','鶏もも肉','ウインナー','エビ','イカ','ホタテ','カニカマ');

-- 2. 写真はあるのに 具材がないもの を追加 (もうあれば 何もしない)
--    値段はお店が admin の「具材・在庫」で あとから変えられます。
INSERT INTO ingredients (category_id, name, price, stock, weekly_stock, image_url, sort_order)
SELECT c.id, n.name, n.price, 100, 100, n.img,
       (SELECT COALESCE(MAX(i2.sort_order), 0) + n.ord FROM ingredients i2 WHERE i2.category_id = c.id)
FROM (
    SELECT '野菜'   AS cat, 'キャベツ' AS name, 100 AS price, '../assets/images/ingredients/cabbage.jpg'  AS img, 1 AS ord UNION ALL
    SELECT '野菜',          '水菜',            100,          '../assets/images/ingredients/mizuna.jpg',          2 UNION ALL
    SELECT '野菜',          'にんじん',        80,           '../assets/images/ingredients/carrot.jpg',          3 UNION ALL
    SELECT '野菜',          'コーン',          100,          '../assets/images/ingredients/corn.jpg',            4 UNION ALL
    SELECT 'きのこ',        'しめじ',          100,          '../assets/images/ingredients/shimeji.jpg',         1 UNION ALL
    SELECT '肉',            'ベーコン',        200,          '../assets/images/ingredients/bacon.jpg',           1 UNION ALL
    SELECT '海鮮',          'タコ',            250,          '../assets/images/ingredients/octopus.jpg',         1
) AS n
JOIN categories c ON c.name = n.cat
WHERE NOT EXISTS (SELECT 1 FROM ingredients x WHERE x.name = n.name AND x.is_deleted = 0);

-- 3. 写真をつなぐ その2 (Wikimedia Commons の 自由に使える写真。クレジットは assets/images/ingredients/CREDITS.md)
--    水餃子・空芯菜 は 使える写真がなかったので なし (画面では 絵文字 が出ます)
UPDATE ingredients SET image_url = CASE name
    WHEN 'トマト'           THEN '../assets/images/ingredients/tomato.jpg'
    WHEN 'れんこん'         THEN '../assets/images/ingredients/lotus-root.jpg'
    WHEN '昆布'             THEN '../assets/images/ingredients/kombu.jpg'
    WHEN 'ふくろたけ'       THEN '../assets/images/ingredients/straw-mushroom.jpg'
    WHEN 'ランチョンミート' THEN '../assets/images/ingredients/luncheon-meat.jpg'
    WHEN 'つみれ'           THEN '../assets/images/ingredients/tsumire.jpg'
    WHEN 'あさり'           THEN '../assets/images/ingredients/asari.jpg'
    WHEN '牛肉団子'         THEN '../assets/images/ingredients/beef-ball.jpg'
    WHEN 'えび団子'         THEN '../assets/images/ingredients/shrimp-ball.jpg'
    WHEN 'いか団子'         THEN '../assets/images/ingredients/fish-ball.jpg'
    WHEN '魚団子'           THEN '../assets/images/ingredients/fish-ball.jpg'
    WHEN 'ちくわ'           THEN '../assets/images/ingredients/chikuwa.jpg'
    WHEN '豆腐'             THEN '../assets/images/ingredients/tofu.jpg'
    WHEN '湯葉'             THEN '../assets/images/ingredients/yuba.jpg'
    WHEN '厚揚げ'           THEN '../assets/images/ingredients/atsuage.jpg'
    WHEN '凍み豆腐'         THEN '../assets/images/ingredients/koya-dofu.jpg'
    WHEN '腐竹（棒湯葉）'   THEN '../assets/images/ingredients/fuzhu.jpg'
    WHEN '春雨'             THEN '../assets/images/ingredients/harusame.jpg'
    WHEN '幅広春雨'         THEN '../assets/images/ingredients/wide-noodles.jpg'
    WHEN 'ラーメン'         THEN '../assets/images/ingredients/ramen-noodles.jpg'
    WHEN 'うどん'           THEN '../assets/images/ingredients/udon.jpg'
    WHEN 'トッポギ'         THEN '../assets/images/ingredients/tteok.jpg'
    WHEN '肉しゅうまい'     THEN '../assets/images/ingredients/shumai.jpg'
    WHEN '小籠包'           THEN '../assets/images/ingredients/xiaolongbao.jpg'
    WHEN '味玉'             THEN '../assets/images/ingredients/ajitama.jpg'
    WHEN 'うずらの卵'       THEN '../assets/images/ingredients/quail-eggs.jpg'
    WHEN 'パクチー'         THEN '../assets/images/ingredients/coriander.jpg'
    WHEN 'チーズ'           THEN '../assets/images/ingredients/cheese.jpg'
    WHEN '肉味噌'           THEN '../assets/images/ingredients/meat-miso.jpg'
    ELSE image_url END
WHERE image_url IS NULL;

-- 4. 写真を新しくする + トウモロコシ麺 を追加 (2026-11-17)
--    あさり・つみれ・いか団子・豆腐・うどん・トッポギ・じゃがいも は 同じファイル名で 写真を入れかえたので SQL はいらない。
--    空芯菜・水餃子 は 写真が見つかったので つなぐ。いか団子 は 魚団子 と別の写真にした。
UPDATE ingredients SET image_url = '../assets/images/ingredients/water-spinach.jpg' WHERE name = '空芯菜';
UPDATE ingredients SET image_url = '../assets/images/ingredients/jiaozi.jpg'        WHERE name = '水餃子';
UPDATE ingredients SET image_url = '../assets/images/ingredients/squid-ball.jpg'    WHERE name = 'いか団子';

INSERT INTO ingredients (category_id, name, price, stock, weekly_stock, image_url, sort_order)
SELECT c.id, 'トウモロコシ麺', 120, 100, 100, '../assets/images/ingredients/corn-noodles.jpg',
       (SELECT COALESCE(MAX(i2.sort_order), 0) + 1 FROM ingredients i2 WHERE i2.category_id = c.id)
FROM categories c
WHERE c.name = '麺'
  AND NOT EXISTS (SELECT 1 FROM ingredients x WHERE x.name = 'トウモロコシ麺' AND x.is_deleted = 0);
-- 幅広春雨 は 春雨 と別の写真にした
UPDATE ingredients SET image_url = '../assets/images/ingredients/wide-noodles.jpg' WHERE name = '幅広春雨';

-- 5. 豚肉 を追加 (豚バラ は もうあるので 写真だけ入れかえ: 同じファイル名 pork-belly.jpg)
INSERT INTO ingredients (category_id, name, price, stock, weekly_stock, image_url, sort_order)
SELECT c.id, '豚肉', 200, 100, 100, '../assets/images/ingredients/pork.jpg',
       (SELECT COALESCE(MAX(i2.sort_order), 0) + 1 FROM ingredients i2 WHERE i2.category_id = c.id)
FROM categories c
WHERE c.name = '肉'
  AND NOT EXISTS (SELECT 1 FROM ingredients x WHERE x.name = '豚肉' AND x.is_deleted = 0);

-- 6. 鶏レバー を追加
INSERT INTO ingredients (category_id, name, price, stock, weekly_stock, image_url, sort_order)
SELECT c.id, '鶏レバー', 150, 100, 100, '../assets/images/ingredients/chicken-liver.jpg',
       (SELECT COALESCE(MAX(i2.sort_order), 0) + 1 FROM ingredients i2 WHERE i2.category_id = c.id)
FROM categories c
WHERE c.name = '肉'
  AND NOT EXISTS (SELECT 1 FROM ingredients x WHERE x.name = '鶏レバー' AND x.is_deleted = 0);

-- 7. タケノコ を追加
INSERT INTO ingredients (category_id, name, price, stock, weekly_stock, image_url, sort_order)
SELECT c.id, 'タケノコ', 120, 100, 100, '../assets/images/ingredients/bamboo-shoot.jpg',
       (SELECT COALESCE(MAX(i2.sort_order), 0) + 1 FROM ingredients i2 WHERE i2.category_id = c.id)
FROM categories c
WHERE c.name = '野菜'
  AND NOT EXISTS (SELECT 1 FROM ingredients x WHERE BINARY x.name = BINARY 'タケノコ' AND x.is_deleted = 0);

-- 8. たけのこ (ひらがな) を追加。タケノコ (カタカナ) とは別の具材 (写真もちがう)
--    BINARY = ひらがな と カタカナ を 別の文字として くらべる
INSERT INTO ingredients (category_id, name, price, stock, weekly_stock, image_url, sort_order)
SELECT c.id, 'たけのこ', 120, 100, 100, '../assets/images/ingredients/takenoko.jpg',
       (SELECT COALESCE(MAX(i2.sort_order), 0) + 1 FROM ingredients i2 WHERE i2.category_id = c.id)
FROM categories c
WHERE c.name = '野菜'
  AND NOT EXISTS (SELECT 1 FROM ingredients x WHERE BINARY x.name = BINARY 'たけのこ' AND x.is_deleted = 0);

-- 9. オクラ を追加
INSERT INTO ingredients (category_id, name, price, stock, weekly_stock, image_url, sort_order)
SELECT c.id, 'オクラ', 100, 100, 100, '../assets/images/ingredients/okra.jpg',
       (SELECT COALESCE(MAX(i2.sort_order), 0) + 1 FROM ingredients i2 WHERE i2.category_id = c.id)
FROM categories c
WHERE c.name = '野菜'
  AND NOT EXISTS (SELECT 1 FROM ingredients x WHERE BINARY x.name = BINARY 'オクラ' AND x.is_deleted = 0);

-- 10. 牛ハチノス (牛の胃) を追加
INSERT INTO ingredients (category_id, name, price, stock, weekly_stock, image_url, sort_order)
SELECT c.id, '牛ハチノス', 200, 100, 100, '../assets/images/ingredients/beef-tripe.jpg',
       (SELECT COALESCE(MAX(i2.sort_order), 0) + 1 FROM ingredients i2 WHERE i2.category_id = c.id)
FROM categories c
WHERE c.name = '肉'
  AND NOT EXISTS (SELECT 1 FROM ingredients x WHERE BINARY x.name = BINARY '牛ハチノス' AND x.is_deleted = 0);

-- 11. エビ 2種類目: 生エビ (頭つき・生)。今までの エビ は ゆでた 赤いエビ。
INSERT INTO ingredients (category_id, name, price, stock, weekly_stock, image_url, sort_order)
SELECT c.id, '生エビ', 300, 100, 100, '../assets/images/ingredients/raw-shrimp.jpg',
       (SELECT COALESCE(MAX(i2.sort_order), 0) + 1 FROM ingredients i2 WHERE i2.category_id = c.id)
FROM categories c
WHERE c.name = '海鮮'
  AND NOT EXISTS (SELECT 1 FROM ingredients x WHERE BINARY x.name = BINARY '生エビ' AND x.is_deleted = 0);

-- 12. スパム牛 を追加 (ランチョンミート とは別の具材)
INSERT INTO ingredients (category_id, name, price, stock, weekly_stock, image_url, sort_order)
SELECT c.id, 'スパム牛', 180, 100, 100, '../assets/images/ingredients/spam-beef.jpg',
       (SELECT COALESCE(MAX(i2.sort_order), 0) + 1 FROM ingredients i2 WHERE i2.category_id = c.id)
FROM categories c
WHERE c.name = '肉'
  AND NOT EXISTS (SELECT 1 FROM ingredients x WHERE BINARY x.name = BINARY 'スパム牛' AND x.is_deleted = 0);

-- 13. インスタント麺 を追加
INSERT INTO ingredients (category_id, name, price, stock, weekly_stock, image_url, sort_order)
SELECT c.id, 'インスタント麺', 100, 100, 100, '../assets/images/ingredients/instant-noodles.jpg',
       (SELECT COALESCE(MAX(i2.sort_order), 0) + 1 FROM ingredients i2 WHERE i2.category_id = c.id)
FROM categories c
WHERE c.name = '麺'
  AND NOT EXISTS (SELECT 1 FROM ingredients x WHERE BINARY x.name = BINARY 'インスタント麺' AND x.is_deleted = 0);

-- 14. 海老餃子 (蝦餃・ハーガウ) を追加。写真は Wikimedia Commons (CREDITS.md)
INSERT INTO ingredients (category_id, name, price, stock, weekly_stock, image_url, sort_order)
SELECT c.id, '海老餃子', 200, 100, 100, '../assets/images/ingredients/har-gow.jpg',
       (SELECT COALESCE(MAX(i2.sort_order), 0) + 1 FROM ingredients i2 WHERE i2.category_id = c.id)
FROM categories c
WHERE c.name = '点心'
  AND NOT EXISTS (SELECT 1 FROM ingredients x WHERE BINARY x.name = BINARY '海老餃子' AND x.is_deleted = 0);

-- 15. タコ団子 を追加
INSERT INTO ingredients (category_id, name, price, stock, weekly_stock, image_url, sort_order)
SELECT c.id, 'タコ団子', 150, 100, 100, '../assets/images/ingredients/octopus-ball.jpg',
       (SELECT COALESCE(MAX(i2.sort_order), 0) + 1 FROM ingredients i2 WHERE i2.category_id = c.id)
FROM categories c
WHERE c.name = '団子・練り物'
  AND NOT EXISTS (SELECT 1 FROM ingredients x WHERE BINARY x.name = BINARY 'タコ団子' AND x.is_deleted = 0);

-- 16. 鶏肉団子 を追加
INSERT INTO ingredients (category_id, name, price, stock, weekly_stock, image_url, sort_order)
SELECT c.id, '鶏肉団子', 150, 100, 100, '../assets/images/ingredients/chicken-ball.jpg',
       (SELECT COALESCE(MAX(i2.sort_order), 0) + 1 FROM ingredients i2 WHERE i2.category_id = c.id)
FROM categories c
WHERE c.name = '団子・練り物'
  AND NOT EXISTS (SELECT 1 FROM ingredients x WHERE BINARY x.name = BINARY '鶏肉団子' AND x.is_deleted = 0);

-- 17. 透明エビ団子 を追加 (えび団子 とは別の具材)
INSERT INTO ingredients (category_id, name, price, stock, weekly_stock, image_url, sort_order)
SELECT c.id, '透明エビ団子', 180, 100, 100, '../assets/images/ingredients/clear-shrimp-ball.jpg',
       (SELECT COALESCE(MAX(i2.sort_order), 0) + 1 FROM ingredients i2 WHERE i2.category_id = c.id)
FROM categories c
WHERE c.name = '団子・練り物'
  AND NOT EXISTS (SELECT 1 FROM ingredients x WHERE BINARY x.name = BINARY '透明エビ団子' AND x.is_deleted = 0);

-- 18. ミニ魚団子 を追加 (魚団子 とは別の具材)
INSERT INTO ingredients (category_id, name, price, stock, weekly_stock, image_url, sort_order)
SELECT c.id, 'ミニ魚団子', 100, 100, 100, '../assets/images/ingredients/mini-fish-ball.jpg',
       (SELECT COALESCE(MAX(i2.sort_order), 0) + 1 FROM ingredients i2 WHERE i2.category_id = c.id)
FROM categories c
WHERE c.name = '団子・練り物'
  AND NOT EXISTS (SELECT 1 FROM ingredients x WHERE BINARY x.name = BINARY 'ミニ魚団子' AND x.is_deleted = 0);

-- 19. 魚すり身ロール を追加
INSERT INTO ingredients (category_id, name, price, stock, weekly_stock, image_url, sort_order)
SELECT c.id, '魚すり身ロール', 150, 100, 100, '../assets/images/ingredients/fish-paste-roll.jpg',
       (SELECT COALESCE(MAX(i2.sort_order), 0) + 1 FROM ingredients i2 WHERE i2.category_id = c.id)
FROM categories c
WHERE c.name = '団子・練り物'
  AND NOT EXISTS (SELECT 1 FROM ingredients x WHERE BINARY x.name = BINARY '魚すり身ロール' AND x.is_deleted = 0);

-- 20. 豆腐魚肉ロール を追加 (湯葉で 魚のすり身を まいたもの)
INSERT INTO ingredients (category_id, name, price, stock, weekly_stock, image_url, sort_order)
SELECT c.id, '豆腐魚肉ロール', 150, 100, 100, '../assets/images/ingredients/tofu-fish-roll.jpg',
       (SELECT COALESCE(MAX(i2.sort_order), 0) + 1 FROM ingredients i2 WHERE i2.category_id = c.id)
FROM categories c
WHERE c.name = '団子・練り物'
  AND NOT EXISTS (SELECT 1 FROM ingredients x WHERE BINARY x.name = BINARY '豆腐魚肉ロール' AND x.is_deleted = 0);

-- 21. 魚豆腐皮ロール を追加 (揚げた 魚の皮ロール)
INSERT INTO ingredients (category_id, name, price, stock, weekly_stock, image_url, sort_order)
SELECT c.id, '魚豆腐皮ロール', 150, 100, 100, '../assets/images/ingredients/fish-tofu-skin-roll.jpg',
       (SELECT COALESCE(MAX(i2.sort_order), 0) + 1 FROM ingredients i2 WHERE i2.category_id = c.id)
FROM categories c
WHERE c.name = '団子・練り物'
  AND NOT EXISTS (SELECT 1 FROM ingredients x WHERE BINARY x.name = BINARY '魚豆腐皮ロール' AND x.is_deleted = 0);

-- 22. 魚肉ソーセージ を追加
INSERT INTO ingredients (category_id, name, price, stock, weekly_stock, image_url, sort_order)
SELECT c.id, '魚肉ソーセージ', 120, 100, 100, '../assets/images/ingredients/fish-sausage.jpg',
       (SELECT COALESCE(MAX(i2.sort_order), 0) + 1 FROM ingredients i2 WHERE i2.category_id = c.id)
FROM categories c
WHERE c.name = '団子・練り物'
  AND NOT EXISTS (SELECT 1 FROM ingredients x WHERE BINARY x.name = BINARY '魚肉ソーセージ' AND x.is_deleted = 0);

-- 23. エビソーセージ を追加
INSERT INTO ingredients (category_id, name, price, stock, weekly_stock, image_url, sort_order)
SELECT c.id, 'エビソーセージ', 150, 100, 100, '../assets/images/ingredients/shrimp-sausage.jpg',
       (SELECT COALESCE(MAX(i2.sort_order), 0) + 1 FROM ingredients i2 WHERE i2.category_id = c.id)
FROM categories c
WHERE c.name = '団子・練り物'
  AND NOT EXISTS (SELECT 1 FROM ingredients x WHERE BINARY x.name = BINARY 'エビソーセージ' AND x.is_deleted = 0);

-- 24. 福袋餃子 を追加 (点心)
INSERT INTO ingredients (category_id, name, price, stock, weekly_stock, image_url, sort_order)
SELECT c.id, '福袋餃子', 150, 100, 100, '../assets/images/ingredients/fukubukuro-dumpling.jpg',
       (SELECT COALESCE(MAX(i2.sort_order), 0) + 1 FROM ingredients i2 WHERE i2.category_id = c.id)
FROM categories c
WHERE c.name = '点心'
  AND NOT EXISTS (SELECT 1 FROM ingredients x WHERE BINARY x.name = BINARY '福袋餃子' AND x.is_deleted = 0);

-- 25. メニューの写真: お肉たっぷり麻辣湯 (前の /images/menus/malatang.jpg は ファイルがなかった)
UPDATE menus SET image_url = '../assets/images/menus/oniku-tappuri.jpg' WHERE name = 'お肉たっぷり麻辣湯' AND is_deleted = 0;
UPDATE menus SET image_url = '../assets/images/menus/yasai-tappuri.jpg' WHERE name = '野菜たっぷり麻辣湯' AND is_deleted = 0;
UPDATE menus SET image_url = '../assets/images/menus/kaisen.jpg' WHERE name = '海鮮麻辣湯' AND is_deleted = 0;
UPDATE menus SET image_url = '../assets/images/menus/dango-iroiro.jpg' WHERE name = '団子いろいろ麻辣湯' AND is_deleted = 0;
UPDATE menus SET image_url = '../assets/images/menus/tofu-yuba.jpg' WHERE name = '豆腐と湯葉の麻辣湯' AND is_deleted = 0;
UPDATE menus SET image_url = '../assets/images/menus/shibire-ramen.jpg' WHERE name = 'しびれ麻辣ラーメン' AND is_deleted = 0;
UPDATE menus SET image_url = '../assets/images/menus/cheese.jpg' WHERE name = 'チーズ麻辣湯' AND is_deleted = 0;
UPDATE menus SET image_url = '../assets/images/menus/tenshin.jpg' WHERE name = '点心のせ麻辣湯' AND is_deleted = 0;
UPDATE menus SET image_url = '../assets/images/menus/lamb-limited.jpg' WHERE name = '【期間限定】羊肉麻辣湯' AND is_deleted = 0;
