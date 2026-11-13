-- database/add-feature-batch.sql
-- 機能まとめ更新 (2026-11): アレルギー・備考・お客さま住所・受け取り時間なし・ジュース
-- phpMyAdmin で「Malatang」DB を選んで、1回だけ実行する。
-- （新しい DB は database/create-tables.sql に同じ内容が入っています）
-- チーム: Myat / Zan は一度だけ実行してください。

-- 1. 受け取り時間は使わない → NULL を許可 (古い注文はそのまま残る)
ALTER TABLE orders
    MODIFY pickup_time DATETIME NULL;

-- 2. 注文に「アレルギー」と、お客さまの住所 (ゲストも) を残す
--    note (備考・特別なご要望) は すでにあります
ALTER TABLE orders
    ADD COLUMN allergy_note     VARCHAR(255) NULL AFTER note,
    ADD COLUMN customer_postal  VARCHAR(10)  NULL AFTER customer_phone,
    ADD COLUMN customer_address VARCHAR(255) NULL AFTER customer_postal;

-- 3. 会員は マイページ で住所を編集できる
ALTER TABLE users
    ADD COLUMN postal_code VARCHAR(10)  NULL AFTER phone,
    ADD COLUMN address     VARCHAR(255) NULL AFTER postal_code;

-- 4. ジュース (飲み物) — お店が メニューに出す／下げる を管理する
--    （今はメニューに「表示するだけ」。カート・注文にはまだ入れない）
CREATE TABLE IF NOT EXISTS juices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    price INT NOT NULL DEFAULT 0 CHECK (price >= 0),
    image_url VARCHAR(500),
    is_available BOOLEAN NOT NULL DEFAULT TRUE,   -- FALSE = メニューに出さない
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 見本の飲み物 (お店が好きに変えられます)
INSERT INTO juices (name, description, price, is_available, sort_order) VALUES
    ('オレンジジュース', '果汁100%のオレンジ',          250, TRUE, 1),
    ('アップルジュース', 'すっきり甘いりんご',            250, TRUE, 2),
    ('ウーロン茶',       '食事に合うあたたかいお茶',      200, TRUE, 3),
    ('ライチジュース',   '南国のあまい香り',              300, TRUE, 4);
