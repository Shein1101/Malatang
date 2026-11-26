-- =====================================================================
--  database/add-menu-price-sync.sql — Owner: Zan
--  Make a ready-made menu's price equal what the SAME bowl costs when you
--  build it in カスタマイズ:  price = (自分で作る base) + Σ(default ingredient price × qty).
--  Fixes the "menu 950 but customize 900" mismatch.
--  Run AFTER create-tables.sql (and the usual seed). Safe to run again.
--  Going forward api/menus.php keeps this in sync whenever a menu's
--  default ingredients (or price) are edited (sync_menu_price()).
-- =====================================================================
SET NAMES utf8mb4;

-- base = the price of the 自分で作る (is_custom) menu. Fallback 0 if none.
SET @base := COALESCE((SELECT price FROM menus WHERE is_custom = 1 AND is_deleted = 0 ORDER BY id LIMIT 1), 0);

-- ingredients already inside the 自分で作る base (春雨 など) must NOT be counted twice
UPDATE menus m
SET m.price = @base + COALESCE((
        SELECT SUM(i.price * mi.quantity)
        FROM menu_ingredients mi
        JOIN ingredients i ON i.id = mi.ingredient_id
        WHERE mi.menu_id = m.id AND i.is_deleted = 0
          -- パクチーは メニューでは無料 (値段に入れない)。api/menus.php の FREE_GARNISH_SQL と同じ [Myat]
          AND i.name NOT IN ('パクチー')
          AND mi.ingredient_id NOT IN (
              -- derived table (SELECT * FROM (...)) so MySQL allows updating `menus` here
              SELECT x.ingredient_id FROM (
                  SELECT cmi.ingredient_id FROM menu_ingredients cmi
                  JOIN menus cm ON cm.id = cmi.menu_id
                  WHERE cm.is_custom = 1 AND cm.is_deleted = 0
              ) x)
    ), 0)
WHERE m.is_custom = 0 AND m.is_deleted = 0;
