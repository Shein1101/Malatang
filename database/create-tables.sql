
-- =====================================================================
--  malatang_ec  — FINAL DATABASE for all features
--  Works on MySQL 8.0.16+ and MariaDB 10.4+ (XAMPP)
--  Chilli 鍋 / 麻辣湯 EC site
--  Sauce removed. 4 soups. Prices are INT in yen (円).
--
--  Version 4 (Oct 20): 24 tables, 39 relationships. Payment = mock card only.
--   * Wallet removed (can come back in M4)
--   * Family & Friends = recipe sharing (as in the demo, slide 9):
--     a customer shares a custom bowl with a friend or family member,
--     the friend opens it and taps 同じ内容で注文 to order the same bowl.
--     Tables: friends, recipe_shares, recipe_share_ingredients
--   * Each bowl has スープ (has_soup: あり/なし) and 味 (soup_id: 麻辣味…)
--   * Guests can order without an account (guest_token / customer_name)
--   * Edit store page: store_photos + announcements tables
--   * Live price: fn_bowl_price() — the server checks the same total
--   * Orders marked paid / unpaid automatically from payments
--
--  The database does these automatically:
--   * Customers can ADD ingredients and REMOVE default ingredients
--   * Stock goes down automatically when an order is saved
--   * Ingredient becomes sold out automatically when stock = 0
--   * Price changes are saved to price_history automatically
--   * Order status changes are saved to order_status_history automatically
--   * A successful payment marks the order as paid
--   * Views for live cart price, the admin order list and order details,
--     the customer's order history (v_order_history, v_order_history_bowls)
--     and the Family & Friends inbox
--
--  WARNING: The first line deletes the old malatang_ec database.
-- =====================================================================
 
DROP DATABASE IF EXISTS malatang_ec;
CREATE DATABASE malatang_ec
  DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE malatang_ec;
 
 
-- =====================================================================
-- 1. USERS  → Sign up & log in                         [Lin]
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
-- 2. STORE PAGE  → Edit store page                     [Myat]
-- =====================================================================
CREATE TABLE store_settings (
    id INT PRIMARY KEY DEFAULT 1 CHECK (id = 1),   -- only 1 row
    store_name VARCHAR(100) NOT NULL,
    description TEXT,
    address VARCHAR(255),
    phone VARCHAR(20),
    opening_hours VARCHAR(255),               -- e.g. '11:00〜21:00 (火曜定休)'
    is_open BOOLEAN NOT NULL DEFAULT TRUE,
    banner_url VARCHAR(500),                  -- main photo at the top of the store page
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
 
-- Photos on the store page (shop, food, staff…)
CREATE TABLE store_photos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    image_url VARCHAR(500) NOT NULL,
    caption VARCHAR(200),
    sort_order INT NOT NULL DEFAULT 0,
    is_visible BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
 
-- Announcements on the store page (holiday, new menu, campaign…)
CREATE TABLE announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(100) NOT NULL,
    body TEXT,
    is_published BOOLEAN NOT NULL DEFAULT FALSE,
    start_date DATE NULL,                     -- NULL = show from now
    end_date DATE NULL,                       -- NULL = show until turned off
    created_by INT NULL,                      -- admin who wrote it
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);
 
 
-- =====================================================================
-- 3. CATEGORIES & INGREDIENTS                           [Myat]
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
    weekly_stock INT NOT NULL DEFAULT 100 CHECK (weekly_stock >= 0),  -- 週の在庫. 売れた数 = weekly_stock - stock
    is_available BOOLEAN NOT NULL DEFAULT TRUE,   -- FALSE = sold out
    is_deleted BOOLEAN NOT NULL DEFAULT FALSE,    -- TRUE = removed from shop
    image_url VARCHAR(500),
    sort_order INT NOT NULL DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id)
);
 
 
-- =====================================================================
-- 4. MENUS  → Add / edit / delete menu                 [Myat]
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
-- 5. SOUPS (味) & SPICE LEVEL  → Customize bowl        [Myat tables, used by Zan]
--    soups = the 4 flavors (味: 麻辣味, 白湯…). Whether the bowl has
--    soup or is dry (スープあり/なし) is has_soup on each bowl.
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
    name VARCHAR(50) NOT NULL,                -- 説明 (例: 辛くない)
    level INT NOT NULL UNIQUE,                -- 1,2,3,4,5
    extra_price INT NOT NULL DEFAULT 0 CHECK (extra_price >= 0),
    is_available BOOLEAN NOT NULL DEFAULT TRUE   -- FALSE = owner が一時的に止める
);
 
-- =====================================================================
-- 6. PRICE HISTORY  → Change prices (automatic)        [Myat]
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
-- 7. CART  → Cart & checkout                            [Zan]
--    1 cart_bowl = 1 bowl (menu + soup + flavor + spice + ingredients)
--    Logged in → user_id. Guest → guest_token (a random id kept in the
--    browser cookie/session). One of them must be set (trigger below).
-- =====================================================================
CREATE TABLE cart_bowls (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,                         -- NULL = guest
    guest_token VARCHAR(64) NULL,             -- guest's cart id
    menu_id INT NOT NULL,
    has_soup BOOLEAN NOT NULL DEFAULT TRUE,   -- スープ: TRUE = あり, FALSE = なし (dry)
    soup_id INT NOT NULL,                     -- 味: flavor (麻辣味…)
    spice_level_id INT NULL,
    quantity INT NOT NULL DEFAULT 1 CHECK (quantity > 0),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
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
-- 8. FAMILY & FRIENDS  → Family & Friends ordering       [Lin]
--    友達にシェアしよう → 友達に届く！ → 同じ内容で注文
--    1) friends: who is in my list (友達 / 家族)
--    2) recipe_shares: a custom bowl I sent to one friend
--    3) recipe_share_ingredients: the ingredients of that bowl
--    The friend orders it with 同じ内容で注文 → the new order_bowls row
--    keeps shared_recipe_id, and the share becomes 'ordered' (trigger).
-- =====================================================================
CREATE TABLE friends (
    user_id INT NOT NULL,                     -- me
    friend_id INT NOT NULL,                   -- my friend (also a user)
    relation ENUM('friend', 'family') NOT NULL DEFAULT 'friend',  -- 友達 / 家族
    nickname VARCHAR(50) NULL,                -- name I see, e.g. 'お母さん'
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, friend_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (friend_id) REFERENCES users(id) ON DELETE CASCADE
);
 
CREATE TABLE recipe_shares (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,                   -- 山田さん
    receiver_id INT NOT NULL,                 -- さくら
    menu_id INT NOT NULL,
    has_soup BOOLEAN NOT NULL DEFAULT TRUE,   -- スープあり / なし
    soup_id INT NOT NULL,                     -- 味
    spice_level_id INT NULL,                  -- 辛さ
    message VARCHAR(200) NULL,                -- optional note to the friend
    status ENUM('new', 'seen', 'ordered') NOT NULL DEFAULT 'new',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (menu_id) REFERENCES menus(id),
    FOREIGN KEY (soup_id) REFERENCES soups(id),
    FOREIGN KEY (spice_level_id) REFERENCES spice_levels(id)
);
 
CREATE TABLE recipe_share_ingredients (
    share_id INT NOT NULL,
    ingredient_id INT NOT NULL,
    action ENUM('added', 'removed') NOT NULL DEFAULT 'added',
    quantity INT NOT NULL DEFAULT 1 CHECK (quantity > 0),
    PRIMARY KEY (share_id, ingredient_id),
    FOREIGN KEY (share_id) REFERENCES recipe_shares(id) ON DELETE CASCADE,
    FOREIGN KEY (ingredient_id) REFERENCES ingredients(id)
);
 
 
-- =====================================================================
-- 9. ORDERS  → Cart & checkout (pickup time), Order list (admin),   [Zan]
--              Order history [Lin]
--     Names and prices are COPIED so old orders never change.
-- =====================================================================
CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(30) NOT NULL UNIQUE, -- e.g. 'CH-20261019-0001'
    user_id INT NULL,                         -- NULL = guest order
    customer_name VARCHAR(100) NOT NULL,      -- always saved (guests too)
    customer_phone VARCHAR(20),
    pickup_time DATETIME NOT NULL,
    total_price INT NOT NULL DEFAULT 0 CHECK (total_price >= 0),
    payment_method ENUM('mock_card') NOT NULL DEFAULT 'mock_card',
    payment_status ENUM('unpaid', 'paid', 'refunded') NOT NULL DEFAULT 'unpaid',  -- set by trigger
    status ENUM('pending', 'confirmed', 'preparing', 'ready', 'completed', 'cancelled')
        NOT NULL DEFAULT 'pending',
    note TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);
 
-- Each bowl in the order
CREATE TABLE order_bowls (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    shared_recipe_id INT NULL,                -- Family & Friends: ordered from a shared recipe
    menu_id INT NULL,
    menu_name VARCHAR(100) NOT NULL,          -- copy
    menu_price INT NOT NULL,                  -- copy
    has_soup BOOLEAN NOT NULL DEFAULT TRUE,   -- スープあり / なし
    soup_id INT NULL,
    soup_name VARCHAR(100),                   -- copy (味)
    soup_price INT NOT NULL DEFAULT 0,        -- copy
    spice_level_id INT NULL,
    spice_name VARCHAR(50),                   -- copy
    spice_price INT NOT NULL DEFAULT 0,       -- copy
    quantity INT NOT NULL DEFAULT 1 CHECK (quantity > 0),
    bowl_total INT NOT NULL DEFAULT 0,        -- (menu+soup+spice+added ingredients) × quantity
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (shared_recipe_id) REFERENCES recipe_shares(id) ON DELETE SET NULL,
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
 
-- =====================================================================
-- 10. PAYMENTS  → Mock payment                         [Zan]
-- =====================================================================
CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    method ENUM('mock_card') NOT NULL DEFAULT 'mock_card',
    amount INT NOT NULL CHECK (amount >= 0),
    status ENUM('pending', 'succeeded', 'failed', 'refunded') NOT NULL DEFAULT 'pending',
    mock_transaction_id VARCHAR(50),          -- fake ID, e.g. 'MOCK-8F3K2'
    card_last4 CHAR(4),                       -- ONLY last 4 digits
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);
 
 
-- =====================================================================
-- 11. FAVORITES  → Favorites & reorder                 [Lin]
--     favorites + favorite_ingredients = マイレシピ (a whole custom bowl)
--     favorite_ingredient_picks       = お気に入りの具材 (ingredients I
--       often add, shown first when I customize any menu)
-- =====================================================================
CREATE TABLE favorites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    nickname VARCHAR(50),                     -- e.g. 'いつもの'
    menu_id INT NOT NULL,
    has_soup BOOLEAN NOT NULL DEFAULT TRUE,   -- スープあり / なし
    soup_id INT NOT NULL,                     -- 味
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

-- お気に入りの具材 (one row = one ingredient I like)
CREATE TABLE favorite_ingredient_picks (
    user_id INT NOT NULL,
    ingredient_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, ingredient_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (ingredient_id) REFERENCES ingredients(id) ON DELETE CASCADE
);


-- =====================================================================
-- 12. SUPPLIES  → 備品・消耗品 (admin only)               [Myat]
--     Things the shop uses but does NOT sell (塩, 油, ティッシュ, スプーン).
--     Not ingredients → customers never see them.
--     Sample data: database/add-supplies.sql
-- =====================================================================
CREATE TABLE supplies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category VARCHAR(20) NOT NULL,                -- 調味料 / 消耗品 / 道具
    name VARCHAR(100) NOT NULL,
    unit VARCHAR(20) NOT NULL DEFAULT '個',       -- 個, 本, 箱, 袋, kg ...
    quantity DECIMAL(10,1) NOT NULL DEFAULT 0 CHECK (quantity >= 0),
    min_quantity DECIMAL(10,1) NOT NULL DEFAULT 0 CHECK (min_quantity >= 0),  -- 0 = no warning
    note VARCHAR(255),
    is_deleted BOOLEAN NOT NULL DEFAULT FALSE,
    sort_order INT NOT NULL DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);


-- =====================================================================
-- INDEXES (make pages faster)
-- =====================================================================
CREATE INDEX idx_orders_user    ON orders(user_id, created_at);
CREATE INDEX idx_orders_status  ON orders(status, pickup_time);
CREATE INDEX idx_cart_user      ON cart_bowls(user_id);
CREATE INDEX idx_cart_guest     ON cart_bowls(guest_token);
CREATE INDEX idx_shares_inbox   ON recipe_shares(receiver_id, status, created_at);
 
 
-- =====================================================================
-- TRIGGERS (the database does these jobs automatically)
--
-- Who made the change? Before an admin update, your API can run:
--     SET @current_user_id = 2;
-- Then price_history and order_status_history save that user.
-- =====================================================================
DELIMITER $$
 
-- A cart bowl must belong to a user OR a guest
CREATE TRIGGER trg_cart_bowls_owner
BEFORE INSERT ON cart_bowls FOR EACH ROW
BEGIN
    IF NEW.user_id IS NULL AND (NEW.guest_token IS NULL OR NEW.guest_token = '') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'cart_bowls needs user_id or guest_token';
    END IF;
END$$
 
-- Family & Friends: you can't add yourself as a friend
CREATE TRIGGER trg_friends_not_self
BEFORE INSERT ON friends FOR EACH ROW
BEGIN
    IF NEW.user_id = NEW.friend_id THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'You cannot add yourself as a friend';
    END IF;
END$$
 
-- Family & Friends: you can only share with someone in your friends list
CREATE TRIGGER trg_recipe_shares_only_friends
BEFORE INSERT ON recipe_shares FOR EACH ROW
BEGIN
    IF NOT EXISTS (SELECT 1 FROM friends
                   WHERE user_id = NEW.sender_id AND friend_id = NEW.receiver_id) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'You can only share with your friends';
    END IF;
END$$
 
-- Family & Friends: friend ordered the shared bowl (同じ内容で注文) → share is 'ordered'
CREATE TRIGGER trg_order_bowls_share_ordered
AFTER INSERT ON order_bowls FOR EACH ROW
BEGIN
    IF NEW.shared_recipe_id IS NOT NULL THEN
        UPDATE recipe_shares SET status = 'ordered' WHERE id = NEW.shared_recipe_id;
    END IF;
END$$
 
-- Payment result → order is marked paid / refunded
CREATE TRIGGER trg_payments_mark_order_insert
AFTER INSERT ON payments FOR EACH ROW
BEGIN
    IF NEW.status = 'succeeded' THEN
        UPDATE orders SET payment_status = 'paid' WHERE id = NEW.order_id;
    ELSEIF NEW.status = 'refunded' THEN
        UPDATE orders SET payment_status = 'refunded' WHERE id = NEW.order_id;
    END IF;
END$$
 
CREATE TRIGGER trg_payments_mark_order_update
AFTER UPDATE ON payments FOR EACH ROW
BEGIN
    IF NEW.status <> OLD.status THEN
        IF NEW.status = 'succeeded' THEN
            UPDATE orders SET payment_status = 'paid' WHERE id = NEW.order_id;
        ELSEIF NEW.status = 'refunded' THEN
            UPDATE orders SET payment_status = 'refunded' WHERE id = NEW.order_id;
        END IF;
    END IF;
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
 
 
-- =====================================================================
-- FUNCTION fn_bowl_price  → Live price calculation (server check)   [Zan]
--
-- The browser shows the price while the customer chooses. Before saving
-- the order, the server calls this function with the same choices and
-- uses ITS answer, so nobody can change the price in the browser.
-- It also stops the order if something is sold out or deleted.
--
--   SELECT fn_bowl_price(menu_id, soup_id, spice_level_id, quantity, items);
--   items = JSON list, e.g.
--   '[{"id":6,"qty":2}, {"id":2,"qty":1,"action":"removed"}]'
--   (action is "added" if you leave it out; spice can be NULL)
--   soup_id = 味 (flavor). スープあり/なし does not change the price.
-- =====================================================================
CREATE FUNCTION fn_bowl_price(
    p_menu_id INT,
    p_soup_id INT,
    p_spice_level_id INT,
    p_quantity INT,
    p_items TEXT
) RETURNS INT
READS SQL DATA
BEGIN
    DECLARE v_total INT DEFAULT 0;
    DECLARE v_price INT;
    DECLARE v_ok INT;
    DECLARE v_count INT DEFAULT 0;
    DECLARE v_i INT DEFAULT 0;
    DECLARE v_id INT;
    DECLARE v_qty INT;
    DECLARE v_action VARCHAR(10);
    DECLARE v_stock INT;
    DECLARE v_removable INT;
 
    IF p_quantity IS NULL OR p_quantity < 1 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Quantity must be 1 or more';
    END IF;
 
    -- Menu
    SELECT price, (is_available AND NOT is_deleted) INTO v_price, v_ok
    FROM menus WHERE id = p_menu_id;
    IF v_price IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Menu not found';
    END IF;
    IF v_ok = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Menu is sold out';
    END IF;
    SET v_total = v_price;
 
    -- Soup (optional)
    IF p_soup_id IS NOT NULL THEN
        SET v_price = NULL;
        SELECT price, is_available INTO v_price, v_ok FROM soups WHERE id = p_soup_id;
        IF v_price IS NULL THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Soup not found';
        END IF;
        IF v_ok = 0 THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Soup is sold out';
        END IF;
        SET v_total = v_total + v_price;
    END IF;
 
    -- Spice level (optional)
    IF p_spice_level_id IS NOT NULL THEN
        SET v_price = NULL;
        SELECT extra_price INTO v_price FROM spice_levels WHERE id = p_spice_level_id;
        IF v_price IS NULL THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Spice level not found';
        END IF;
        SET v_total = v_total + v_price;
    END IF;
 
    -- Ingredients
    IF p_items IS NOT NULL AND p_items <> '' THEN
        IF JSON_VALID(p_items) = 0 THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Ingredient list is not valid JSON';
        END IF;
        SET v_count = JSON_LENGTH(p_items);
        WHILE v_i < v_count DO
            SET v_id = JSON_UNQUOTE(JSON_EXTRACT(p_items, CONCAT('$[', v_i, '].id')));
            SET v_qty = COALESCE(JSON_UNQUOTE(JSON_EXTRACT(p_items, CONCAT('$[', v_i, '].qty'))), 1);
            SET v_action = COALESCE(JSON_UNQUOTE(JSON_EXTRACT(p_items, CONCAT('$[', v_i, '].action'))), 'added');
            IF v_qty < 1 THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Ingredient quantity must be 1 or more';
            END IF;
 
            IF v_action = 'removed' THEN
                -- Only a default ingredient that is allowed to be removed
                SET v_removable = NULL;
                SELECT is_removable INTO v_removable
                FROM menu_ingredients WHERE menu_id = p_menu_id AND ingredient_id = v_id;
                IF v_removable IS NULL OR v_removable = 0 THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'This ingredient cannot be removed';
                END IF;
            ELSE
                SET v_price = NULL;
                SELECT price, (is_available AND NOT is_deleted), stock
                INTO v_price, v_ok, v_stock
                FROM ingredients WHERE id = v_id;
                IF v_price IS NULL THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Ingredient not found';
                END IF;
                IF v_ok = 0 OR v_stock < v_qty * p_quantity THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Ingredient is sold out';
                END IF;
                SET v_total = v_total + v_price * v_qty;
            END IF;
            SET v_i = v_i + 1;
        END WHILE;
    END IF;
 
    RETURN v_total * p_quantity;
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
    cb.guest_token,
    m.name AS menu_name,
    cb.has_soup,
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
    o.payment_status,
    o.status,
    (o.user_id IS NULL) AS is_guest,
    (SELECT SUM(ob.quantity) FROM order_bowls ob WHERE ob.order_id = o.id) AS bowl_count,
    o.note,
    o.created_at
FROM orders o
WHERE o.status NOT IN ('completed', 'cancelled');
 
-- Every bowl of every open order, with ingredients and spice in one row
--   → Order list (admin): "ingredients, spice level and pickup time at a glance"
CREATE VIEW v_admin_order_details AS
SELECT
    o.id AS order_id,
    o.order_number,
    o.pickup_time,
    o.status,
    o.payment_status,
    ob.id AS order_bowl_id,
    ob.menu_name,
    ob.has_soup,
    ob.soup_name,
    ob.spice_name,
    ob.quantity,
    (SELECT GROUP_CONCAT(CONCAT(oi.ingredient_name, ' ×', oi.quantity) SEPARATOR ', ')
       FROM order_items oi WHERE oi.order_bowl_id = ob.id AND oi.action = 'added') AS added_ingredients,
    (SELECT GROUP_CONCAT(oi.ingredient_name SEPARATOR ', ')
       FROM order_items oi WHERE oi.order_bowl_id = ob.id AND oi.action = 'removed') AS removed_ingredients,
    (ob.shared_recipe_id IS NOT NULL) AS from_shared_recipe,
    ob.bowl_total
FROM orders o
JOIN order_bowls ob ON ob.order_id = o.id
WHERE o.status NOT IN ('completed', 'cancelled');
 
-- Customer order history, one row per order (newest first in the app)
--   → Order history [Lin]: "Logged-in customers see their past orders"
--   Use: SELECT * FROM v_order_history WHERE user_id = ? ORDER BY ordered_at DESC;
CREATE VIEW v_order_history AS
SELECT
    o.user_id,
    o.id AS order_id,
    o.order_number,
    o.created_at AS ordered_at,
    o.pickup_time,
    o.status,
    o.payment_status,
    o.total_price,
    (SELECT SUM(ob.quantity) FROM order_bowls ob WHERE ob.order_id = o.id) AS bowl_count,
    (SELECT GROUP_CONCAT(CONCAT(ob.menu_name, '（', ob.soup_name, '）×', ob.quantity)
                         ORDER BY ob.id SEPARATOR ' / ')
       FROM order_bowls ob WHERE ob.order_id = o.id) AS bowls_summary
FROM orders o
WHERE o.user_id IS NOT NULL;
 
-- Customer order history, one row per bowl (detail page + 再注文 / reorder)
--   Use: SELECT * FROM v_order_history_bowls WHERE user_id = ? AND order_id = ?;
CREATE VIEW v_order_history_bowls AS
SELECT
    o.user_id,
    o.id AS order_id,
    o.order_number,
    o.created_at AS ordered_at,
    o.status,
    ob.id AS order_bowl_id,
    ob.menu_id,
    ob.menu_name,
    ob.has_soup,
    ob.soup_id,
    ob.soup_name,
    ob.spice_level_id,
    ob.spice_name,
    ob.quantity,
    (SELECT GROUP_CONCAT(CONCAT(oi.ingredient_name, ' ×', oi.quantity) ORDER BY oi.id SEPARATOR ', ')
       FROM order_items oi WHERE oi.order_bowl_id = ob.id AND oi.action = 'added') AS added_ingredients,
    (SELECT GROUP_CONCAT(oi.ingredient_name ORDER BY oi.id SEPARATOR ', ')
       FROM order_items oi WHERE oi.order_bowl_id = ob.id AND oi.action = 'removed') AS removed_ingredients,
    ob.bowl_total,
    (ob.shared_recipe_id IS NOT NULL) AS from_shared_recipe
FROM orders o
JOIN order_bowls ob ON ob.order_id = o.id
WHERE o.user_id IS NOT NULL;
 
-- Family & Friends inbox: recipes my friends sent me, with today's price
--   → 友達に届く！ screen ("山田さん レシピをシェアしました", ¥2,200, 同じ内容で注文)
CREATE VIEW v_recipe_inbox AS
SELECT
    rs.id AS share_id,
    rs.receiver_id,
    rs.sender_id,
    u.name AS sender_name,
    rs.status,
    m.name AS menu_name,
    rs.has_soup,
    s.name AS soup_name,
    sp.level AS spice_level,
    sp.name AS spice_name,
    (SELECT GROUP_CONCAT(i.name ORDER BY i.id SEPARATOR ', ')
       FROM recipe_share_ingredients ri JOIN ingredients i ON i.id = ri.ingredient_id
       WHERE ri.share_id = rs.id AND ri.action = 'added') AS ingredients,
    (m.price + s.price + COALESCE(sp.extra_price, 0)
      + COALESCE((SELECT SUM(i.price * ri.quantity)
                  FROM recipe_share_ingredients ri JOIN ingredients i ON i.id = ri.ingredient_id
                  WHERE ri.share_id = rs.id AND ri.action = 'added'), 0)) AS price_now,
    rs.message,
    rs.created_at
FROM recipe_shares rs
JOIN users u              ON u.id = rs.sender_id
JOIN menus m              ON m.id = rs.menu_id
JOIN soups s              ON s.id = rs.soup_id
LEFT JOIN spice_levels sp ON sp.id = rs.spice_level_id;
 
-- Store page: visible photos and announcements showing today  → Edit store page
CREATE VIEW v_store_announcements AS
SELECT id, title, body, start_date, end_date
FROM announcements
WHERE is_published = TRUE
  AND (start_date IS NULL OR start_date <= CURRENT_DATE)
  AND (end_date IS NULL OR end_date >= CURRENT_DATE);
 
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
 
INSERT INTO store_photos (image_url, caption, sort_order) VALUES
('/images/store/front.jpg', '店の外観', 1),
('/images/store/bowl.jpg',  '人気の麻辣湯', 2);
 
INSERT INTO announcements (title, body, is_published) VALUES
('オープン記念', 'トッピング1品無料！', TRUE);
 
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
('辛くない', 1, 0),
('ピリ辛',   2, 0),
('中辛',     3, 0),
('大辛',     4, 0),
('激辛',     5, 0);
 
 
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
 
-- [Live price of my cart] (user 1, or a guest with token 'abc123')
-- SELECT * FROM v_cart_bowl_prices WHERE user_id = 1;
-- SELECT SUM(bowl_total) AS cart_total FROM v_cart_bowl_prices WHERE user_id = 1;
-- SELECT * FROM v_cart_bowl_prices WHERE guest_token = 'abc123';
 
-- [Live price — server check before saving an order]
-- Menu 1, tomato soup (3), 大辛 (4), 2 bowls, +2 エビ, no もやし → 2200
-- SELECT fn_bowl_price(1, 3, 4, 2, '[{"id":6,"qty":2},{"id":2,"action":"removed"}]');
 
-- [Family & Friends] (山田 = user 1, さくら = user 3)
-- 1) My friends list (友達にシェアしよう screen)
-- INSERT INTO friends (user_id, friend_id, relation) VALUES (1, 3, 'friend');
-- SELECT f.friend_id, COALESCE(f.nickname, u.name) AS name, f.relation
--   FROM friends f JOIN users u ON u.id = f.friend_id WHERE f.user_id = 1;
-- 2) Share my custom bowl with さくら (友達にシェア button)
-- INSERT INTO recipe_shares (sender_id, receiver_id, menu_id, has_soup, soup_id, spice_level_id)
--   VALUES (1, 3, 1, TRUE, 1, 4);
-- INSERT INTO recipe_share_ingredients (share_id, ingredient_id) VALUES (LAST_INSERT_ID(), 4);
-- 3) さくら's inbox (友達に届く！ screen)
-- SELECT * FROM v_recipe_inbox WHERE receiver_id = 3 ORDER BY created_at DESC;
-- 4) 同じ内容で注文 → copy the share into さくら's cart, then check out as usual
--    (save shared_recipe_id on the order_bowls row; the share becomes 'ordered')
-- INSERT INTO cart_bowls (user_id, menu_id, has_soup, soup_id, spice_level_id)
--   SELECT receiver_id, menu_id, has_soup, soup_id, spice_level_id FROM recipe_shares WHERE id = 1;
-- INSERT INTO cart_bowl_ingredients (cart_bowl_id, ingredient_id, action, quantity)
--   SELECT LAST_INSERT_ID(), ingredient_id, action, quantity FROM recipe_share_ingredients WHERE share_id = 1;
 
-- [Guest cart] (no account; token comes from the browser session)
-- INSERT INTO cart_bowls (guest_token, menu_id, has_soup, soup_id) VALUES ('abc123', 1, TRUE, 1);
 
-- [Edit store page]
-- UPDATE store_settings SET opening_hours = '11:00〜22:00', is_open = TRUE WHERE id = 1;
-- INSERT INTO store_photos (image_url, caption, sort_order) VALUES ('/images/store/new.jpg', '新メニュー', 3);
-- INSERT INTO announcements (title, body, is_published, end_date, created_by)
--   VALUES ('年末年始のお休み', '12/31〜1/3 はお休みです', TRUE, '2027-01-03', 2);
-- SELECT * FROM store_photos WHERE is_visible = TRUE ORDER BY sort_order;
-- SELECT * FROM v_store_announcements;
 
-- [Sold-out switch] (ingredient 3)
-- UPDATE ingredients SET is_available = FALSE WHERE id = 3;
 
-- [Change price] (admin 2 changes menu 1 to 550円 — history is saved automatically)
-- SET @current_user_id = 2;
-- UPDATE menus SET price = 550 WHERE id = 1;
 
-- [Change order status] (admin 2 — history is saved automatically)
-- SET @current_user_id = 2;
-- UPDATE orders SET status = 'preparing' WHERE id = 1;
 
-- [Admin order list] + every bowl with ingredients and spice
-- SELECT * FROM v_admin_orders ORDER BY pickup_time;
-- SELECT * FROM v_admin_order_details ORDER BY pickup_time, order_id;
 
-- [Mock payment] → the order becomes 'paid' automatically
-- INSERT INTO payments (order_id, amount, status, mock_transaction_id, card_last4)
--   VALUES (1, 2200, 'succeeded', 'MOCK-8F3K2', '4242');
 
-- [Order history] (user 1)  [Lin]
-- List page:   SELECT * FROM v_order_history WHERE user_id = 1 ORDER BY ordered_at DESC;
-- Detail page: SELECT * FROM v_order_history_bowls WHERE user_id = 1 AND order_id = 5;
-- 再注文 (reorder one bowl from history, order_bowl_id = 7) → copy into the cart:
-- INSERT INTO cart_bowls (user_id, menu_id, has_soup, soup_id, spice_level_id, quantity)
--   SELECT user_id, menu_id, has_soup, soup_id, spice_level_id, quantity
--   FROM v_order_history_bowls WHERE user_id = 1 AND order_bowl_id = 7;
-- INSERT INTO cart_bowl_ingredients (cart_bowl_id, ingredient_id, action, quantity)
--   SELECT LAST_INSERT_ID(), ingredient_id, action, quantity
--   FROM order_items WHERE order_bowl_id = 7 AND ingredient_id IS NOT NULL;
 
-- Check: show all tables and views
SHOW FULL TABLES;
 

