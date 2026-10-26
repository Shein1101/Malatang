
-- =====================================================================
--  database/sample_menus.sql — Owner: Myat
--  Real-shop-style menus (8 more, 10 in total) with default ingredients.
--  Run AFTER create-tables.sql and sample_ingredients.sql. Run it ONE time only.
--
--  Ingredients are found by NAME, so ingredient ids do not matter.
--  The noodle (春雨 / ラーメン) cannot be removed (is_removable = 0);
--  everything else can be removed by the customer.
--  羊肉麻辣湯 is sold out on purpose, so you can test the 売り切れ card.
-- =====================================================================
SET NAMES utf8mb4;

-- 3 野菜たっぷり
INSERT INTO menus (name, description, price, is_available, sort_order)
VALUES ('野菜たっぷり麻辣湯', '白菜・青梗菜・ほうれん草・きのこ入り。ヘルシーな一杯', 850, TRUE, 3);
SET @m = LAST_INSERT_ID();
INSERT INTO menu_ingredients (menu_id, ingredient_id, quantity, is_removable)
SELECT @m, id, 1, name <> '春雨' FROM ingredients
WHERE is_deleted = 0 AND name IN ('白菜', 'チンゲン菜', 'ほうれん草', 'えのき', 'きくらげ', '春雨');

-- 4 海鮮
INSERT INTO menus (name, description, price, is_available, sort_order)
VALUES ('海鮮麻辣湯', 'エビ・イカ・ホタテ・あさりの旨みたっぷり', 1180, TRUE, 4);
SET @m = LAST_INSERT_ID();
INSERT INTO menu_ingredients (menu_id, ingredient_id, quantity, is_removable)
SELECT @m, id, 1, name <> '春雨' FROM ingredients
WHERE is_deleted = 0 AND name IN ('エビ', 'イカ', 'ホタテ', 'あさり', 'チンゲン菜', '春雨');

-- 5 団子
INSERT INTO menus (name, description, price, is_available, sort_order)
VALUES ('団子いろいろ麻辣湯', '牛肉団子・えび団子・魚団子の食べくらべ', 950, TRUE, 5);
SET @m = LAST_INSERT_ID();
INSERT INTO menu_ingredients (menu_id, ingredient_id, quantity, is_removable)
SELECT @m, id, 1, name <> '春雨' FROM ingredients
WHERE is_deleted = 0 AND name IN ('牛肉団子', 'えび団子', '魚団子', 'もやし', '春雨');

-- 6 豆腐
INSERT INTO menus (name, description, price, is_available, sort_order)
VALUES ('豆腐と湯葉の麻辣湯', '豆腐・湯葉・厚揚げで、やさしいたんぱく質', 880, TRUE, 6);
SET @m = LAST_INSERT_ID();
INSERT INTO menu_ingredients (menu_id, ingredient_id, quantity, is_removable)
SELECT @m, id, 1, name <> '春雨' FROM ingredients
WHERE is_deleted = 0 AND name IN ('豆腐', '湯葉', '厚揚げ', 'もやし', '春雨');

-- 7 ラーメン
INSERT INTO menus (name, description, price, is_available, sort_order)
VALUES ('しびれ麻辣ラーメン', '春雨のかわりにラーメン。豚バラとニラでがっつり', 980, TRUE, 7);
SET @m = LAST_INSERT_ID();
INSERT INTO menu_ingredients (menu_id, ingredient_id, quantity, is_removable)
SELECT @m, id, 1, name <> 'ラーメン' FROM ingredients
WHERE is_deleted = 0 AND name IN ('ラーメン', '豚バラ', 'ニラ', 'もやし', '味玉');

-- 8 チーズ
INSERT INTO menus (name, description, price, is_available, sort_order)
VALUES ('チーズ麻辣湯', 'とろけるチーズとトッポギ。辛さがマイルドに', 980, TRUE, 8);
SET @m = LAST_INSERT_ID();
INSERT INTO menu_ingredients (menu_id, ingredient_id, quantity, is_removable)
SELECT @m, id, 1, name <> '春雨' FROM ingredients
WHERE is_deleted = 0 AND name IN ('チーズ', 'トッポギ', 'ウインナー', 'もやし', '春雨');

-- 9 点心
INSERT INTO menus (name, description, price, is_available, sort_order)
VALUES ('点心のせ麻辣湯', '水餃子・しゅうまい・小籠包をのせた贅沢な一杯', 1080, TRUE, 9);
SET @m = LAST_INSERT_ID();
INSERT INTO menu_ingredients (menu_id, ingredient_id, quantity, is_removable)
SELECT @m, id, 1, name <> '春雨' FROM ingredients
WHERE is_deleted = 0 AND name IN ('水餃子', '肉しゅうまい', '小籠包', 'チンゲン菜', '春雨');

-- 10 羊肉 (期間限定・売り切れ test)
INSERT INTO menus (name, description, price, is_available, sort_order)
VALUES ('【期間限定】羊肉麻辣湯', '羊肉とパクチーの本場の味', 1280, FALSE, 10);
SET @m = LAST_INSERT_ID();
INSERT INTO menu_ingredients (menu_id, ingredient_id, quantity, is_removable)
SELECT @m, id, 1, name <> '春雨' FROM ingredients
WHERE is_deleted = 0 AND name IN ('羊肉', 'パクチー', '白菜', '春雨');
-- =====================================================================
-- (A + B) menu 1 = 「自分で作る麻辣湯」: 春雨だけ。スープと具材はお客さんが選ぶ
-- Decision: Notion → Decisions (Oct 26)
-- =====================================================================
UPDATE menus
SET name = '自分で作る麻辣湯',
    description = '春雨とスープから、好きな具材を自由に選んで作る',
    sort_order = 0
WHERE id = 1;

DELETE mi FROM menu_ingredients mi
JOIN ingredients i ON i.id = mi.ingredient_id
WHERE mi.menu_id = 1 AND i.name <> '春雨';