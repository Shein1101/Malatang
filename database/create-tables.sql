-- =====================================================================
--  malatang_ec  — FINAL DATABASE for all features (MySQL 8.0.16+)
--  Chilli 鍋 / 麻辣湯 EC site
--  Sauce removed. 4 soups. Prices are INT in yen (円).
--
--  NEW in this version (the database now does these automatically):
--   * Customers can ADD ingredients and REMOVE default ingredients
--   * Stock goes down automatically when an order is saved
--   * Ingredient becomes sold out automatically when stock = 0
--   * A wallet is created automatically for every new user
--   * Price changes are saved to price_history automatically
--   * Order status changes are saved to order_status_history automatically
--   * Views for live cart price and the admin order list
--
--  WARNING: The first line deletes the old malatang_ec database.
-- =====================================================================

DROP DATABASE IF EXISTS malatang_ec;
CREATE DATABASE malatang_ec
  DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE malatang_ec;


-- =====================================================================
-- 1. USERS  → Sign up & log in
-- =====================================================================
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,      -- save bcrypt hash, NEVER plain password
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    role ENUM('customer', 'admin') NOT NULL DEFAULT 'customer',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);


-- =====================================================================
-- 2. WALLET  → Wallet
-- =====================================================================
CREATE TABLE wallets (
    user_id INT PRIMARY KEY,
    balance INT NOT NULL DEFAULT 0 CHECK (balance >= 0),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE wallet_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM('topup', 'payment', 'refund') NOT NULL,
    amount INT NOT NULL,                      -- + topup/refund, - payment
    order_id INT NULL,                        -- FK added after orders table
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);


-- =====================================================================
-- 3. STORE PAGE  → Edit store page
-- =====================================================================
CREATE TABLE store_settings (
    id INT PRIMARY KEY DEFAULT 1 CHECK (id = 1),   -- only 1 row
    store_name VARCHAR(100) NOT NULL,
    description TEXT,
    address VARCHAR(255),
    phone VARCHAR(20),
    opening_hours VARCHAR(255),               -- e.g. '11:00〜21:00 (火曜定休)'
    is_open BOOLEAN NOT NULL DEFAULT TRUE,
    banner_url VARCHAR(500),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);


-- =====================================================================
-- 4. CATEGORIES & INGREDIENTS
--    → Add / remove ingredients, Stock & sold-out switch,
--      Menu & ingredient display
-- =====================================================================
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0
);

CREATE TABLE ingredients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    price INT NOT NULL CHECK (price >= 0),
    stock INT NOT NULL DEFAULT 0 CHECK (stock >= 0),
    is_available BOOLEAN NOT NULL DEFAULT TRUE,   -- FALSE = sold out
    is_deleted BOOLEAN NOT NULL DEFAULT FALSE,    -- TRUE = removed from shop
    image_url VARCHAR(500),
    sort_order INT NOT NULL DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id)
);


-- =====================================================================
-- 5. MENUS  → Add / edit / delete menu
-- =====================================================================
CREATE TABLE menus (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    price INT NOT NULL CHECK (price >= 0),
    image_url VARCHAR(500),
    is_available BOOLEAN NOT NULL DEFAULT TRUE,   -- FALSE = sold out
    is_deleted BOOLEAN NOT NULL DEFAULT FALSE,    -- delete = set TRUE (keeps old orders safe)
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Default ingredients of each menu (included in the menu price)
CREATE TABLE menu_ingredients (
    menu_id INT NOT NULL,
    ingredient_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1 CHECK (quantity > 0),
    is_removable BOOLEAN NOT NULL DEFAULT TRUE,   -- can the customer take it out?
    PRIMARY KEY (menu_id, ingredient_id),
    FOREIGN KEY (menu_id) REFERENCES menus(id) ON DELETE CASCADE,
    FOREIGN KEY (ingredient_id) REFERENCES ingredients(id)
);


-- =====================================================================
-- 6. SOUPS & SPICE LEVEL  → Customize bowl (ingredients & spice)
-- =====================================================================
CREATE TABLE soups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    price INT NOT NULL DEFAULT 0 CHECK (price >= 0),
    is_available BOOLEAN NOT NULL DEFAULT TRUE,
    sort_order INT NOT NULL DEFAULT 0
);

CREATE TABLE spice_levels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    level INT NOT NULL UNIQUE,                -- 0,1,2,3,4
    extra_price INT NOT NULL DEFAULT 0 CHECK (extra_price >= 0)
);


-- =====================================================================
-- 7. PRICE HISTORY  → Change prices (filled automatically by triggers)
-- =====================================================================
CREATE TABLE price_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    target_type ENUM('menu', 'ingredient', 'soup', 'spice') NOT NULL,
    target_id INT NOT NULL,
    old_price INT NOT NULL,
    new_price INT NOT NULL,
    changed_by INT NULL,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL
);


-- =====================================================================
-- 8. FAMILY & FRIENDS  → Family & Friends ordering
-- =====================================================================
CREATE TABLE group_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    host_user_id INT NOT NULL,
    invite_code VARCHAR(12) NOT NULL UNIQUE,  -- share link: /group/AB12CD
    pickup_time DATETIME,
    deadline DATETIME,                        -- add items before this time
    status ENUM('open', 'locked', 'ordered', 'cancelled') NOT NULL DEFAULT 'open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (host_user_id) REFERENCES users(id)
);

CREATE TABLE group_order_members (
    group_order_id INT NOT NULL,
    user_id INT NOT NULL,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (group_order_id, user_id),
    FOREIGN KEY (group_order_id) REFERENCES group_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);


-- =====================================================================
-- 9. CART  → Cart & checkout
--    1 cart_bowl = 1 bowl (menu + soup + spice + ingredient changes)
-- =====================================================================
CREATE TABLE cart_bowls (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    group_order_id INT NULL,                  -- NULL = my own cart
    menu_id INT NOT NULL,
    soup_id INT NULL,                         -- NULL = no soup (dry)
    spice_level_id INT NULL,
    quantity INT NOT NULL DEFAULT 1 CHECK (quantity > 0),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (group_order_id) REFERENCES group_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (menu_id) REFERENCES menus(id),
    FOREIGN KEY (soup_id) REFERENCES soups(id),
    FOREIGN KEY (spice_level_id) REFERENCES spice_levels(id)
);

-- added   = extra ingredient (customer pays for it)
-- removed = take out a default ingredient (no price change)
CREATE TABLE cart_bowl_ingredients (
    cart_bowl_id INT NOT NULL,
    ingredient_id INT NOT NULL,
    action ENUM('added', 'removed') NOT NULL DEFAULT 'added',
    quantity INT NOT NULL DEFAULT 1 CHECK (quantity > 0),
    PRIMARY KEY (cart_bowl_id, ingredient_id),
    FOREIGN KEY (cart_bowl_id) REFERENCES cart_bowls(id) ON DELETE CASCADE,
    FOREIGN KEY (ingredient_id) REFERENCES ingredients(id)
);


-- =====================================================================
-- 10. ORDERS  → Cart & checkout (pickup time), Order list (admin),
--               Order history, Reorder
--     Names and prices are COPIED so old orders never change.
-- =====================================================================
CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(30) NOT NULL UNIQUE, -- e.g. 'CH-20261019-0001'
    user_id INT NOT NULL,
    group_order_id INT NULL,
    customer_name VARCHAR(100) NOT NULL,
    customer_phone VARCHAR(20),
    pickup_time DATETIME NOT NULL,
    total_price INT NOT NULL DEFAULT 0 CHECK (total_price >= 0),
    payment_method ENUM('wallet', 'mock_card') NOT NULL,
    status ENUM('pending', 'confirmed', 'preparing', 'ready', 'completed', 'cancelled')
        NOT NULL DEFAULT 'pending',
    note TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (group_order_id) REFERENCES group_orders(id)
);

-- Each bowl in the order
CREATE TABLE order_bowls (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    added_by_user_id INT NULL,                -- who added it (group order)
    menu_id INT NULL,
    menu_name VARCHAR(100) NOT NULL,          -- copy
    menu_price INT NOT NULL,                  -- copy
    soup_id INT NULL,
    soup_name VARCHAR(100),                   -- copy
    soup_price INT NOT NULL DEFAULT 0,        -- copy
    spice_level_id INT NULL,
    spice_name VARCHAR(50),                   -- copy
    spice_price INT NOT NULL DEFAULT 0,       -- copy
    quantity INT NOT NULL DEFAULT 1 CHECK (quantity > 0),
    bowl_total INT NOT NULL DEFAULT 0,        -- (menu+soup+spice+added ingredients) × quantity
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (added_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (menu_id) REFERENCES menus(id),
    FOREIGN KEY (soup_id) REFERENCES soups(id),
    FOREIGN KEY (spice_level_id) REFERENCES spice_levels(id)
);

-- Ingredient changes in each bowl (added / removed)
CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_bowl_id INT NOT NULL,
    ingredient_id INT NULL,
    ingredient_name VARCHAR(100) NOT NULL,    -- copy
    action ENUM('added', 'removed') NOT NULL DEFAULT 'added',
    unit_price INT NOT NULL DEFAULT 0,        -- copy (0 for removed)
    quantity INT NOT NULL DEFAULT 1 CHECK (quantity > 0),
    FOREIGN KEY (order_bowl_id) REFERENCES order_bowls(id) ON DELETE CASCADE,
    FOREIGN KEY (ingredient_id) REFERENCES ingredients(id)
);

-- Status changes (filled automatically by a trigger)
CREATE TABLE order_status_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    status ENUM('pending', 'confirmed', 'preparing', 'ready', 'completed', 'cancelled') NOT NULL,
    changed_by INT NULL,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL
);

ALTER TABLE wallet_transactions
    ADD FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL;


-- =====================================================================
-- 11. PAYMENTS  → Mock payment
-- =====================================================================
CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    method ENUM('wallet', 'mock_card') NOT NULL,
    amount INT NOT NULL CHECK (amount >= 0),
    status ENUM('pending', 'succeeded', 'failed', 'refunded') NOT NULL DEFAULT 'pending',
    mock_transaction_id VARCHAR(50),          -- fake ID, e.g. 'MOCK-8F3K2'
    card_last4 CHAR(4),                       -- ONLY last 4 digits
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);


-- =====================================================================
-- 12. FAVORITES  → Favorites & reorder
--     Saves a whole custom bowl
-- =====================================================================
CREATE TABLE favorites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    nickname VARCHAR(50),                     -- e.g. 'いつもの'
    menu_id INT NOT NULL,
    soup_id INT NULL,
    spice_level_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (menu_id) REFERENCES menus(id),
    FOREIGN KEY (soup_id) REFERENCES soups(id),
    FOREIGN KEY (spice_level_id) REFERENCES spice_levels(id)
);

CREATE TABLE favorite_ingredients (
    favorite_id INT NOT NULL,
    ingredient_id INT NOT NULL,
    action ENUM('added', 'removed') NOT NULL DEFAULT 'added',
    quantity INT NOT NULL DEFAULT 1 CHECK (quantity > 0),
    PRIMARY KEY (favorite_id, ingredient_id),
    FOREIGN KEY (favorite_id) REFERENCES favorites(id) ON DELETE CASCADE,
    FOREIGN KEY (ingredient_id) REFERENCES ingredients(id)
);


-- =====================================================================
-- INDEXES (make pages faster)
-- =====================================================================
CREATE INDEX idx_orders_user    ON orders(user_id, created_at);
CREATE INDEX idx_orders_status  ON orders(status, pickup_time);
CREATE INDEX idx_cart_user      ON cart_bowls(user_id);
CREATE INDEX idx_wallet_tx_user ON wallet_transactions(user_id, created_at);


-- =====================================================================
-- TRIGGERS (the database does these jobs automatically)
--
-- Who made the change? Before an admin update, your API can run:
--     SET @current_user_id = 2;
-- Then price_history and order_status_history save that user.
-- =====================================================================
DELIMITER $$

-- New user → create a wallet with 0円
CREATE TRIGGER trg_users_create_wallet
AFTER INSERT ON users FOR EACH ROW
BEGIN
    INSERT INTO wallets (user_id, balance) VALUES (NEW.id, 0);
END$$

-- Ingredient stock: 0 → sold out, refilled → available again
CREATE TRIGGER trg_ingredients_stock_soldout
BEFORE UPDATE ON ingredients FOR EACH ROW
BEGIN
    IF NEW.stock = 0 AND OLD.stock > 0 THEN
        SET NEW.is_available = FALSE;
    ELSEIF NEW.stock > 0 AND OLD.stock = 0 THEN
        SET NEW.is_available = TRUE;
    END IF;
END$$

-- Price changes → price_history
CREATE TRIGGER trg_menus_price_history
AFTER UPDATE ON menus FOR EACH ROW
BEGIN
    IF NEW.price <> OLD.price THEN
        INSERT INTO price_history (target_type, target_id, old_price, new_price, changed_by)
        VALUES ('menu', NEW.id, OLD.price, NEW.price, @current_user_id);
    END IF;
END$$

CREATE TRIGGER trg_ingredients_price_history
AFTER UPDATE ON ingredients FOR EACH ROW
BEGIN
    IF NEW.price <> OLD.price THEN
        INSERT INTO price_history (target_type, target_id, old_price, new_price, changed_by)
        VALUES ('ingredient', NEW.id, OLD.price, NEW.price, @current_user_id);
    END IF;
END$$

CREATE TRIGGER trg_soups_price_history
AFTER UPDATE ON soups FOR EACH ROW
BEGIN
    IF NEW.price <> OLD.price THEN
        INSERT INTO price_history (target_type, target_id, old_price, new_price, changed_by)
        VALUES ('soup', NEW.id, OLD.price, NEW.price, @current_user_id);
    END IF;
END$$

CREATE TRIGGER trg_spice_price_history
AFTER UPDATE ON spice_levels FOR EACH ROW
BEGIN
    IF NEW.extra_price <> OLD.extra_price THEN
        INSERT INTO price_history (target_type, target_id, old_price, new_price, changed_by)
        VALUES ('spice', NEW.id, OLD.extra_price, NEW.extra_price, @current_user_id);
    END IF;
END$$

-- New order → first status in history
CREATE TRIGGER trg_orders_status_first
AFTER INSERT ON orders FOR EACH ROW
BEGIN
    INSERT INTO order_status_history (order_id, status, changed_by)
    VALUES (NEW.id, NEW.status, @current_user_id);
END$$

-- Status changed → save in history
CREATE TRIGGER trg_orders_status_change
AFTER UPDATE ON orders FOR EACH ROW
BEGIN
    IF NEW.status <> OLD.status THEN
        INSERT INTO order_status_history (order_id, status, changed_by)
        VALUES (NEW.id, NEW.status, @current_user_id);
    END IF;
END$$

-- Added ingredient saved in an order → stock goes down
-- (If there is not enough stock, the INSERT fails, so you can't oversell.)
CREATE TRIGGER trg_order_items_reduce_stock
AFTER INSERT ON order_items FOR EACH ROW
BEGIN
    IF NEW.action = 'added' AND NEW.ingredient_id IS NOT NULL THEN
        UPDATE ingredients
        SET stock = stock - NEW.quantity *
            (SELECT quantity FROM order_bowls WHERE id = NEW.order_bowl_id)
        WHERE id = NEW.ingredient_id;
    END IF;
END$$

DELIMITER ;


-- =====================================================================
-- VIEWS (ready-made queries — use them like tables)
-- =====================================================================

-- Live price of every bowl in the cart  → Live price calculation
CREATE VIEW v_cart_bowl_prices AS
SELECT
    cb.id AS cart_bowl_id,
    cb.user_id,
    cb.group_order_id,
    m.name AS menu_name,
    s.name AS soup_name,
    sp.name AS spice_name,
    cb.quantity,
    m.price AS menu_price,
    COALESCE(s.price, 0) AS soup_price,
    COALESCE(sp.extra_price, 0) AS spice_price,
    COALESCE((SELECT SUM(i.price * ci.quantity)
              FROM cart_bowl_ingredients ci
              JOIN ingredients i ON i.id = ci.ingredient_id
              WHERE ci.cart_bowl_id = cb.id AND ci.action = 'added'), 0) AS added_price,
    (m.price + COALESCE(s.price, 0) + COALESCE(sp.extra_price, 0)
      + COALESCE((SELECT SUM(i.price * ci.quantity)
                  FROM cart_bowl_ingredients ci
                  JOIN ingredients i ON i.id = ci.ingredient_id
                  WHERE ci.cart_bowl_id = cb.id AND ci.action = 'added'), 0)
    ) * cb.quantity AS bowl_total
FROM cart_bowls cb
JOIN menus m              ON m.id = cb.menu_id
LEFT JOIN soups s         ON s.id = cb.soup_id
LEFT JOIN spice_levels sp ON sp.id = cb.spice_level_id;

-- Admin order list (orders not finished yet)  → Order list (admin)
CREATE VIEW v_admin_orders AS
SELECT
    o.id,
    o.order_number,
    o.customer_name,
    o.customer_phone,
    o.pickup_time,
    o.total_price,
    o.payment_method,
    o.status,
    (SELECT SUM(ob.quantity) FROM order_bowls ob WHERE ob.order_id = o.id) AS bowl_count,
    o.created_at
FROM orders o
WHERE o.status NOT IN ('completed', 'cancelled');

-- Customer menu page (only what can be shown)  → Menu & ingredient display
CREATE VIEW v_menu_display AS
SELECT id, name, description, price, image_url, is_available
FROM menus
WHERE is_deleted = FALSE;

CREATE VIEW v_ingredient_display AS
SELECT c.id AS category_id, c.name AS category_name, c.sort_order AS category_order,
       i.id, i.name, i.price, i.image_url, i.is_available
FROM ingredients i
JOIN categories c ON c.id = i.category_id
WHERE i.is_deleted = FALSE;


-- =====================================================================
-- SAMPLE DATA (for testing — change to your real shop data)
-- =====================================================================
INSERT INTO store_settings (store_name, description, opening_hours, is_open)
VALUES ('Chilli 鍋', '自分だけの麻辣湯を作ろう', '11:00〜21:00', TRUE);

INSERT INTO categories (name, sort_order) VALUES
('野菜', 1), ('肉', 2), ('海鮮', 3), ('麺', 4), ('豆腐', 5);

INSERT INTO ingredients (category_id, name, price, stock, sort_order) VALUES
(1, 'チンゲン菜', 100, 50, 1),
(1, 'もやし',      80, 50, 2),
(1, 'きくらげ',   100, 40, 3),
(2, '豚バラ',     200, 30, 1),
(2, '牛肉',       250, 30, 2),
(3, 'エビ',       250, 20, 1),
(3, 'つみれ',     150, 30, 2),
(4, '春雨',       100, 50, 1),
(4, 'ラーメン',   120, 50, 2),
(5, '豆腐',       100, 40, 1),
(5, '湯葉',       150, 30, 2);

INSERT INTO menus (name, description, price, sort_order) VALUES
('基本の麻辣湯',       'スープと具材を自由に選べる', 500, 1),
('お肉たっぷり麻辣湯', '豚バラと牛肉入り',           800, 2);

-- Default ingredients (春雨 cannot be removed: it's the noodle base)
INSERT INTO menu_ingredients (menu_id, ingredient_id, quantity, is_removable) VALUES
(1, 2, 1, TRUE), (1, 8, 1, FALSE),
(2, 4, 1, TRUE), (2, 5, 1, TRUE), (2, 8, 1, FALSE);

INSERT INTO soups (name, description, price, sort_order) VALUES
('麻辣スープ',   'しびれる辛さの四川風スープ',       0,  1),
('白湯スープ',   'まろやかな豚骨スープ（辛くない）', 0,  2),
('トマトスープ', '甘酸っぱいトマトスープ',           50, 3),
('きのこスープ', 'あっさりきのこスープ',             50, 4);

INSERT INTO spice_levels (name, level, extra_price) VALUES
('辛くない', 0, 0),
('小辛',     1, 0),
('中辛',     2, 0),
('大辛',     3, 50),
('激辛',     4, 100);


-- =====================================================================
-- USEFUL QUERIES FOR YOUR API (remove the -- to use)
-- =====================================================================

-- [Menu page]
-- SELECT * FROM v_menu_display ORDER BY id;
-- SELECT * FROM v_ingredient_display ORDER BY category_order, name;

-- [Default ingredients of menu 1]
-- SELECT i.id, i.name, mi.quantity, mi.is_removable
-- FROM menu_ingredients mi JOIN ingredients i ON i.id = mi.ingredient_id
-- WHERE mi.menu_id = 1;

-- [Live price of my cart] (user 1)
-- SELECT * FROM v_cart_bowl_prices WHERE user_id = 1;
-- SELECT SUM(bowl_total) AS cart_total FROM v_cart_bowl_prices WHERE user_id = 1;

-- [Sold-out switch] (ingredient 3)
-- UPDATE ingredients SET is_available = FALSE WHERE id = 3;

-- [Change price] (admin 2 changes menu 1 to 550円 — history is saved automatically)
-- SET @current_user_id = 2;
-- UPDATE menus SET price = 550 WHERE id = 1;

-- [Change order status] (admin 2 — history is saved automatically)
-- SET @current_user_id = 2;
-- UPDATE orders SET status = 'preparing' WHERE id = 1;

-- [Admin order list]
-- SELECT * FROM v_admin_orders ORDER BY pickup_time;

-- [Order history] (user 1)
-- SELECT * FROM orders WHERE user_id = 1 ORDER BY created_at DESC;

-- [Wallet top-up] (user 1 adds 1000円) — run both lines together
-- UPDATE wallets SET balance = balance + 1000 WHERE user_id = 1;
-- INSERT INTO wallet_transactions (user_id, type, amount) VALUES (1, 'topup', 1000);

-- Check: show all tables and views
SHOW FULL TABLES;
