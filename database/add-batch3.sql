-- ============================================================
-- Batch 3 migration — run once in phpMyAdmin (Chilli 鍋)
--   1. reviews       : お客さまの声 (customers post, admin hides)
--   2. payment method : PayPay と 代金引換(COD) を追加
-- Mirrored into create-tables.sql + er-diagram.md.
-- ============================================================

-- 1. Reviews -------------------------------------------------
CREATE TABLE IF NOT EXISTS reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,                       -- だれが書いたか (会員のみ)
    rating TINYINT NOT NULL,                    -- 1〜5 (PHP でチェック)
    comment VARCHAR(300),
    is_visible BOOLEAN NOT NULL DEFAULT TRUE,   -- admin が隠せる
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 2. Payment methods: カード(mock) に PayPay と 代金引換(COD) を足す ----
ALTER TABLE orders
    MODIFY payment_method ENUM('mock_card','paypay','cod') NOT NULL DEFAULT 'mock_card';
ALTER TABLE payments
    MODIFY method         ENUM('mock_card','paypay','cod') NOT NULL DEFAULT 'mock_card';
