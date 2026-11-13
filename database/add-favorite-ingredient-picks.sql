-- =====================================================================
--  お気に入りの具材 — add to an EXISTING malatang_ec database   [Lin]
--  Run this once in phpMyAdmin (SQL tab). It does not delete anything.
--  (New databases made from create-tables.sql already have this table.)
-- =====================================================================
USE malatang_ec;

CREATE TABLE IF NOT EXISTS favorite_ingredient_picks (
    user_id INT NOT NULL,
    ingredient_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, ingredient_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (ingredient_id) REFERENCES ingredients(id) ON DELETE CASCADE
);
