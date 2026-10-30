-- =====================================================================
--  1つの量 (portion) — add to an EXISTING malatang_ec database   [Myat]
--  Run this ONE time in phpMyAdmin (SQL tab). It does not delete anything.
--  (New databases made from create-tables.sql already have this column.)
--
--  portion = how much is in ONE of this ingredient, e.g. 豚バラ "50g", 水餃子 "3個"
--  The owner writes it on admin/stock.php (具材・在庫). Empty = not shown.
-- =====================================================================
USE malatang_ec;

ALTER TABLE ingredients
    ADD COLUMN portion VARCHAR(20) NULL AFTER price;
