-- =====================================================================
--  ホームページの編集 (home_blocks) — update an EXISTING malatang_ec   [Lin]
--  Run this ONE time in phpMyAdmin (SQL tab). It does not delete any data.
--  (New databases made from create-tables.sql already have this.)
--
--  1 row = 1 block on public/store.php. sort_order = position on the page.
--  The base design is NOT in this file: api/home.php adds it the first
--  time the table is empty (same code as the 「基本デザインに戻す」 button).
-- =====================================================================
USE malatang_ec;
SET NAMES utf8mb4;

CREATE TABLE home_blocks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type ENUM('hero', 'notices', 'promo', 'ad', 'custom') NOT NULL,
    title VARCHAR(100),
    body TEXT,
    badge VARCHAR(30),                        -- promo: e.g. '20% OFF'
    image_url VARCHAR(500),
    link_label VARCHAR(30),
    link_url VARCHAR(500),
    sort_order INT NOT NULL DEFAULT 0,
    is_visible BOOLEAN NOT NULL DEFAULT TRUE,
    start_date DATE NULL,                     -- NULL = show from now
    end_date DATE NULL,                       -- NULL = show until turned off
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
