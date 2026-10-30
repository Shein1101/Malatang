-- =====================================================================
--  自分で作る麻辣湯 (build your own bowl) — update an EXISTING malatang_ec   [Lin]
--  Run this ONE time in phpMyAdmin (SQL tab). It does not delete any data.
--  (New databases made from create-tables.sql already have this.)
--
--  menus.is_custom = TRUE → the base for 「自分で作る」 on customize.php:
--    price = base price (春雨 + soup), customer picks every ingredient.
--    menu.php does NOT show it as a card (ready-made dishes only).
--    The owner can still change its price / ON-OFF on admin/menus.php.
--  If '自分で作る麻辣湯' or '基本の麻辣湯' already exists, that one becomes the base.
--  Otherwise a new one is added (¥500, 春雨 that cannot be removed).
-- =====================================================================
USE malatang_ec;
SET NAMES utf8mb4;

ALTER TABLE menus
    ADD COLUMN is_custom BOOLEAN NOT NULL DEFAULT FALSE AFTER is_deleted;

UPDATE menus SET is_custom = TRUE, name = '自分で作る麻辣湯',
       description = '味・辛さ・具材をぜんぶ自分で選ぶ一杯'
WHERE is_deleted = 0 AND name IN ('自分で作る麻辣湯', '基本の麻辣湯')
ORDER BY id LIMIT 1;

INSERT INTO menus (name, description, price, is_available, sort_order, is_custom)
SELECT '自分で作る麻辣湯', '味・辛さ・具材をぜんぶ自分で選ぶ一杯', 500, TRUE, 0, TRUE
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM menus WHERE is_custom = TRUE AND is_deleted = 0);

-- 春雨 (noodle base, cannot be removed) — only if the base has no default ingredients yet
INSERT INTO menu_ingredients (menu_id, ingredient_id, quantity, is_removable)
SELECT m.id, i.id, 1, FALSE
FROM menus m
JOIN ingredients i ON i.name = '春雨' AND i.is_deleted = 0
WHERE m.is_custom = TRUE AND m.is_deleted = 0
  AND NOT EXISTS (SELECT 1 FROM menu_ingredients mi WHERE mi.menu_id = m.id)
LIMIT 1;
