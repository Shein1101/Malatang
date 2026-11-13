-- =====================================================================
--  database/sample_ingredients.sql — Owner: Myat
--  Real-shop-style ingredient list (about 50 items) for testing.
--  Run AFTER create-tables.sql. Run it ONE time only.
--
--  Keeps the 11 sample ingredients (id 1–11) so menu_ingredients still works,
--  adds 4 new categories and 41 new ingredients.
--  Prices: most toppings 100–250 yen per portion (like real malatang shops).
--  羊肉 is sold out on purpose, so you can test the 売り切れ display.
-- =====================================================================
SET NAMES utf8mb4;

-- Category order on screen: 野菜 → きのこ → 肉 → 海鮮 → 団子・練り物 → 豆腐・湯葉 → 麺 → 点心 → トッピング
UPDATE categories SET name = '豆腐・湯葉' WHERE id = 5;
UPDATE categories SET sort_order = 1 WHERE id = 1;   -- 野菜
UPDATE categories SET sort_order = 3 WHERE id = 2;   -- 肉
UPDATE categories SET sort_order = 4 WHERE id = 3;   -- 海鮮
UPDATE categories SET sort_order = 7 WHERE id = 4;   -- 麺
UPDATE categories SET sort_order = 6 WHERE id = 5;   -- 豆腐・湯葉

INSERT INTO categories (id, name, sort_order) VALUES
(6, 'きのこ',       2),
(7, '団子・練り物', 5),
(8, '点心',         8),
(9, 'トッピング',   9);

-- category_id, name, price, stock, is_available, sort_order
INSERT INTO ingredients (category_id, name, price, stock, is_available, sort_order) VALUES
-- 1 野菜 (already: チンゲン菜, もやし, きくらげ)
(1, '白菜',         100, 50, TRUE, 4),
(1, 'ほうれん草',   100, 40, TRUE, 5),
(1, '空芯菜',       150, 30, TRUE, 6),
(1, 'ニラ',         100, 40, TRUE, 7),
(1, 'レタス',       100, 40, TRUE, 8),
(1, 'トマト',       120, 30, TRUE, 9),
(1, 'じゃがいも',   100, 40, TRUE, 10),
(1, 'れんこん',     120, 30, TRUE, 11),
(1, 'ブロッコリー', 120, 30, TRUE, 12),
(1, '昆布',         100, 40, TRUE, 13),
-- 6 きのこ
(6, 'えのき',       100, 50, TRUE, 1),
(6, 'しいたけ',     120, 40, TRUE, 2),
(6, 'ふくろたけ',   150, 30, TRUE, 3),
(6, 'エリンギ',     120, 30, TRUE, 4),
-- 2 肉 (already: 豚バラ, 牛肉)
(2, '鶏もも肉',     200, 30, TRUE, 3),
(2, '羊肉',         300, 0,  FALSE, 4),   -- sold out (test)
(2, 'ランチョンミート', 180, 30, TRUE, 5),
(2, 'ウインナー',   150, 40, TRUE, 6),
-- 3 海鮮 (already: エビ, つみれ)
(3, 'イカ',         200, 20, TRUE, 3),
(3, 'ホタテ',       300, 15, TRUE, 4),
(3, 'あさり',       200, 20, TRUE, 5),
-- 7 団子・練り物
(7, '牛肉団子',     150, 40, TRUE, 1),
(7, 'えび団子',     150, 40, TRUE, 2),
(7, 'いか団子',     150, 40, TRUE, 3),
(7, '魚団子',       120, 40, TRUE, 4),
(7, 'カニカマ',     100, 50, TRUE, 5),
(7, 'ちくわ',       100, 50, TRUE, 6),
-- 5 豆腐・湯葉 (already: 豆腐, 湯葉)
(5, '厚揚げ',       120, 30, TRUE, 3),
(5, '凍み豆腐',     120, 30, TRUE, 4),
(5, '腐竹（棒湯葉）', 150, 30, TRUE, 5),
-- 4 麺 (already: 春雨, ラーメン)
(4, '幅広春雨',     120, 40, TRUE, 3),
(4, 'うどん',       120, 40, TRUE, 4),
(4, 'トッポギ',     150, 30, TRUE, 5),
-- 8 点心
(8, '水餃子',       150, 40, TRUE, 1),
(8, '肉しゅうまい', 150, 40, TRUE, 2),
(8, '小籠包',       200, 30, TRUE, 3),
-- 9 トッピング
(9, '味玉',         100, 30, TRUE, 1),
(9, 'うずらの卵',   100, 40, TRUE, 2),
(9, 'パクチー',     100, 30, TRUE, 3),
(9, 'チーズ',       150, 30, TRUE, 4),
(9, '肉味噌',       100, 30, TRUE, 5);
