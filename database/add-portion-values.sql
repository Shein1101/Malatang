-- =====================================================================
--  1つの量 (portion) のサンプル値 — update an EXISTING malatang_ec   [Lin]
--  Run after add-portion.sql (and sample_ingredients.sql).
--  Only fills EMPTY values, so it never overwrites what the owner wrote,
--  and running it twice is safe.
--
--  These are typical amounts, not measured ones: the owner should check
--  them and change them on admin/stock.php (具材・在庫).
-- =====================================================================
USE malatang_ec;
SET NAMES utf8mb4;

UPDATE ingredients
SET portion = CASE name
    -- 野菜
    WHEN 'チンゲン菜' THEN '60g'
    WHEN 'もやし' THEN '80g'
    WHEN 'きくらげ' THEN '20g'
    WHEN '白菜' THEN '80g'
    WHEN 'ほうれん草' THEN '50g'
    WHEN '空芯菜' THEN '60g'
    WHEN 'ニラ' THEN '40g'
    WHEN 'レタス' THEN '50g'
    WHEN 'トマト' THEN '80g'
    WHEN 'じゃがいも' THEN '80g'
    WHEN 'れんこん' THEN '60g'
    WHEN 'ブロッコリー' THEN '60g'
    WHEN '昆布' THEN '20g'
    -- きのこ
    WHEN 'まいたけ' THEN '50g'
    WHEN 'えのき' THEN '50g'
    WHEN 'しいたけ' THEN '2個'
    WHEN 'ふくろたけ' THEN '3個'
    WHEN 'エリンギ' THEN '50g'
    -- 肉
    WHEN '豚バラ' THEN '50g'
    WHEN '牛肉' THEN '50g'
    WHEN '鶏もも肉' THEN '60g'
    WHEN '羊肉' THEN '50g'
    WHEN 'ランチョンミート' THEN '2枚'
    WHEN 'ウインナー' THEN '2本'
    -- 海鮮
    WHEN 'エビ' THEN '3尾'
    WHEN 'つみれ' THEN '3個'
    WHEN 'イカ' THEN '50g'
    WHEN 'ホタテ' THEN '2個'
    WHEN 'あさり' THEN '5個'
    -- 団子・練り物
    WHEN '牛肉団子' THEN '3個'
    WHEN 'えび団子' THEN '3個'
    WHEN 'いか団子' THEN '3個'
    WHEN '魚団子' THEN '3個'
    WHEN 'カニカマ' THEN '3本'
    WHEN 'ちくわ' THEN '2本'
    -- 豆腐・湯葉
    WHEN '豆腐' THEN '100g'
    WHEN '湯葉' THEN '30g'
    WHEN '厚揚げ' THEN '2個'
    WHEN '凍み豆腐' THEN '2個'
    WHEN '腐竹（棒湯葉）' THEN '30g'
    -- 麺
    WHEN '春雨' THEN '50g'
    WHEN 'ラーメン' THEN '1玉'
    WHEN '幅広春雨' THEN '50g'
    WHEN 'うどん' THEN '1玉'
    WHEN 'トッポギ' THEN '4個'
    -- 点心
    WHEN '水餃子' THEN '3個'
    WHEN '肉しゅうまい' THEN '3個'
    WHEN '小籠包' THEN '2個'
    -- トッピング
    WHEN '味玉' THEN '1個'
    WHEN 'うずらの卵' THEN '3個'
    WHEN 'パクチー' THEN '10g'
    WHEN 'チーズ' THEN '20g'
    WHEN '肉味噌' THEN '30g'
    ELSE portion
END
WHERE portion IS NULL OR portion = '';
