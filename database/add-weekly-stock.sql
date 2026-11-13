-- =====================================================================
--  週の在庫 (weekly_stock) — add to an EXISTING malatang_ec database   [Myat]
--  Run this ONE time in phpMyAdmin (SQL tab). It does not delete anything.
--  (New databases made from create-tables.sql already have this column.)
--
--  weekly_stock = how many we prepare for one week (default 100)
--  stock        = how many are left now (goes down when customers order,
--                 by trigger trg_order_items_reduce_stock)
--  売れた数     = weekly_stock - stock   (shown on admin/stock.php)
--  New week → the owner types the new 残り on admin/stock.php
-- =====================================================================
USE malatang_ec;

ALTER TABLE ingredients
    ADD COLUMN weekly_stock INT NOT NULL DEFAULT 100 CHECK (weekly_stock >= 0) AFTER stock;
