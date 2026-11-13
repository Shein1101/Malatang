-- =====================================================================
--  パスワード再設定 (password_resets) — update an EXISTING malatang_ec   [Lin]
--  Run this ONE time in phpMyAdmin (SQL tab). It does not delete any data.
--  (New databases made from create-tables.sql already have this.)
--
--  「パスワードをお忘れですか？」の仕組み (public/forgot.php → public/reset.php):
--    1 row = 1 つの再設定リンク。token そのものは保存せず、SHA-256 だけ保存する
--    (パスワードと同じで、元に戻せない形で持つ)。
--    expires_at を過ぎたリンク・一度使ったリンク (used_at) はもう使えない。
-- =====================================================================
USE malatang_ec;
SET NAMES utf8mb4;

CREATE TABLE password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,      -- SHA-256 (hex) of the token in the link
    expires_at DATETIME NOT NULL,             -- the link stops working after this
    used_at DATETIME NULL,                    -- set once the link has been used (one time only)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
