-- database/add-batch2.sql
-- 2回目の機能まとめ (2026-11): テイクアウト／配達・SNSリンク・カートにドリンク
-- phpMyAdmin で「Malatang」DB を選んで、1回だけ実行する。
-- （新しい DB は database/create-tables.sql に同じ内容が入っています）
-- チーム: Myat / Zan も一度だけ実行してください。

-- 1. 受け取り方法: テイクアウト（店頭）か 配達。配達のときは住所が必須。
ALTER TABLE orders
    ADD COLUMN order_type ENUM('takeout', 'delivery') NOT NULL DEFAULT 'takeout' AFTER status;

-- 2. お店の SNS リンク (ホームのフッターに出す。お店が編集できる)
ALTER TABLE store_settings
    ADD COLUMN line_url      VARCHAR(255) NULL AFTER phone,
    ADD COLUMN instagram_url VARCHAR(255) NULL AFTER line_url,
    ADD COLUMN x_url         VARCHAR(255) NULL AFTER instagram_url;

-- 3. カートのドリンク (ジュースをカートに入れられるように)
CREATE TABLE IF NOT EXISTS cart_drinks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,                         -- login している人
    guest_token VARCHAR(64) NULL,             -- guest のカート
    juice_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1 CHECK (quantity >= 1),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (juice_id) REFERENCES juices(id) ON DELETE CASCADE
);

-- 4. 注文のドリンク (注文したときの名前・値段をコピーして残す)
CREATE TABLE IF NOT EXISTS order_drinks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    juice_id INT NULL,                         -- あとで消えても注文は残る
    juice_name VARCHAR(100) NOT NULL,          -- copy
    juice_price INT NOT NULL,                  -- copy
    quantity INT NOT NULL DEFAULT 1,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (juice_id) REFERENCES juices(id) ON DELETE SET NULL
);
