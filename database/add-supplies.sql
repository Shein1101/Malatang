-- =====================================================================
--  備品・消耗品 (supplies) — add to an EXISTING malatang_ec database   [Myat]
--  Run this ONE time in phpMyAdmin (SQL tab). It does not delete anything.
--  (New databases made from create-tables.sql already have this table,
--   but still need the sample data below.)
--
--  Things the shop uses but does NOT sell (塩, 油, ティッシュ, スプーン ...).
--  Separate from ingredients, so customers never see them.
--  quantity <= min_quantity → 残りわずか on admin/supplies.php
-- =====================================================================
USE malatang_ec;
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS supplies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category VARCHAR(20) NOT NULL,                -- 調味料 / 消耗品 / 道具
    name VARCHAR(100) NOT NULL,
    unit VARCHAR(20) NOT NULL DEFAULT '個',       -- 個, 本, 箱, 袋, kg ...
    quantity DECIMAL(10,1) NOT NULL DEFAULT 0 CHECK (quantity >= 0),
    min_quantity DECIMAL(10,1) NOT NULL DEFAULT 0 CHECK (min_quantity >= 0),  -- 0 = no warning (道具など)
    note VARCHAR(255),
    is_deleted BOOLEAN NOT NULL DEFAULT FALSE,    -- TRUE = removed from list
    sort_order INT NOT NULL DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Sample data. 塩 and ティッシュ are low on purpose, 紙ナプキン is 0,
-- so you can test 残りわずか / なくなった on the screen.
INSERT INTO supplies (category, name, unit, quantity, min_quantity, note, sort_order) VALUES
('調味料', '麻辣スープの素', '袋', 12,  4,  NULL, 1),
('調味料', '塩',             'kg', 1,   1,  NULL, 2),
('調味料', '砂糖',           'kg', 3,   1,  NULL, 3),
('調味料', '油',             '本', 4,   2,  '1本 = 1.5L', 4),
('調味料', '花椒',           'kg', 1.5, 0.5, NULL, 5),
('調味料', '醤油',           '本', 3,   1,  NULL, 6),
('消耗品', 'ティッシュ',     '箱', 2,   3,  NULL, 1),
('消耗品', '紙ナプキン',     '袋', 0,   2,  NULL, 2),
('消耗品', '使い捨てスプーン', '本', 200, 50, NULL, 3),
('消耗品', '割り箸',         '膳', 300, 100, NULL, 4),
('消耗品', '持ち帰り容器',   '個', 80,  30, NULL, 5),
('道具',   'お玉',           '個', 4,   0,  NULL, 1),
('道具',   '包丁',           '本', 3,   0,  NULL, 2),
('道具',   'ハンマー',       '個', 1,   0,  '修理用', 3);
