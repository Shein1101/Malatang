-- =====================================================================
--  database/add-max-custom-ingredients.sql — Owner: Zan
--  How many EXTRA ingredients (total count) a customer may add when
--  customizing a bowl. Edited by the shop in admin/store.php (お店の情報).
--  0 = no limit. Safe to run again.
-- =====================================================================
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'store_settings'
               AND COLUMN_NAME = 'max_custom_ingredients');
SET @sql := IF(@col = 0,
    'ALTER TABLE store_settings ADD COLUMN max_custom_ingredients INT NOT NULL DEFAULT 15',
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

UPDATE store_settings SET max_custom_ingredients = 15 WHERE id = 1 AND max_custom_ingredients = 0;
