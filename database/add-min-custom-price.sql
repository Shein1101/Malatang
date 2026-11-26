-- database/add-min-custom-price.sql  [Myat] (2026-11-20)
-- 「自分で作る麻辣湯」の 最低注文金額 (1杯の値段)。これより安い bowl は 注文できない。
-- お店が admin/store.php で変えられる。0 = 制限なし。はじめは 1000円。
-- phpMyAdmin で「Malatang」DB を選んで、1回だけ実行する。何回実行しても同じ結果になります。
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'store_settings'
               AND COLUMN_NAME = 'min_custom_price');
SET @sql := IF(@col = 0,
    'ALTER TABLE store_settings ADD COLUMN min_custom_price INT NOT NULL DEFAULT 1000',
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
