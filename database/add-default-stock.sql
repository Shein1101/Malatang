-- =====================================================================
--  Default ingredients also reduce stock — update an EXISTING malatang_ec   [Myat]
--  Run this in phpMyAdmin (SQL tab). It does not delete any data.
--  (New databases made from create-tables.sql already have these triggers.)
--  Safe to run again: each trigger is dropped and made again.
--
--  Before: only ADDED ingredients reduced stock (海鮮麻辣湯 never reduced ホタテ).
--  Now:    order a bowl          → its default ingredients go down (残り -1, 売れた +1)
--          customer removed one  → that one is given back
--          order cancelled       → default ingredients are given back
--          stock 0 or less       → 品切れ automatically (販売 ON is not possible)
-- =====================================================================
USE malatang_ec;

DROP TRIGGER IF EXISTS trg_ingredients_stock_soldout;
DROP TRIGGER IF EXISTS trg_ingredients_stock_soldout_insert;
DROP TRIGGER IF EXISTS trg_order_bowls_reduce_default_stock;
DROP TRIGGER IF EXISTS trg_order_items_reduce_stock;
DROP TRIGGER IF EXISTS trg_orders_cancel_default_stock;

DELIMITER $$

-- Ingredient stock: 0 (or less) → always sold out (販売 can not be ON), refilled → available again
CREATE TRIGGER trg_ingredients_stock_soldout
BEFORE UPDATE ON ingredients FOR EACH ROW
BEGIN
    IF NEW.stock <= 0 THEN
        SET NEW.is_available = FALSE;
    ELSEIF OLD.stock <= 0 THEN
        SET NEW.is_available = TRUE;
    END IF;
END$$

-- New ingredient with stock 0 → sold out from the start
CREATE TRIGGER trg_ingredients_stock_soldout_insert
BEFORE INSERT ON ingredients FOR EACH ROW
BEGIN
    IF NEW.stock <= 0 THEN
        SET NEW.is_available = FALSE;
    END IF;
END$$

-- Bowl saved in an order → the menu's default ingredients go down
-- (menu_ingredients.quantity × number of bowls)
CREATE TRIGGER trg_order_bowls_reduce_default_stock
AFTER INSERT ON order_bowls FOR EACH ROW
BEGIN
    IF NEW.menu_id IS NOT NULL THEN
        UPDATE ingredients i
        JOIN menu_ingredients mi ON mi.ingredient_id = i.id AND mi.menu_id = NEW.menu_id
        SET i.stock = i.stock - mi.quantity * NEW.quantity;
    END IF;
END$$

-- Ingredient change saved in an order
--   added   → stock goes down (api/orders.php stops the order if it goes below 0)
--   removed → the default ingredient taken out is given back
CREATE TRIGGER trg_order_items_reduce_stock
AFTER INSERT ON order_items FOR EACH ROW
BEGIN
    IF NEW.action = 'added' AND NEW.ingredient_id IS NOT NULL THEN
        UPDATE ingredients
        SET stock = stock - NEW.quantity *
            (SELECT quantity FROM order_bowls WHERE id = NEW.order_bowl_id)
        WHERE id = NEW.ingredient_id;
    ELSEIF NEW.action = 'removed' AND NEW.ingredient_id IS NOT NULL THEN
        UPDATE ingredients i
        JOIN order_bowls ob ON ob.id = NEW.order_bowl_id
        JOIN menu_ingredients mi ON mi.menu_id = ob.menu_id AND mi.ingredient_id = i.id
        SET i.stock = i.stock + mi.quantity * ob.quantity
        WHERE i.id = NEW.ingredient_id;
    END IF;
END$$

-- Order cancelled → give back the default ingredients that were used
-- (added ingredients are given back by api/orders.php)
CREATE TRIGGER trg_orders_cancel_default_stock
AFTER UPDATE ON orders FOR EACH ROW
BEGIN
    IF NEW.status = 'cancelled' AND OLD.status <> 'cancelled' THEN
        UPDATE ingredients i
        JOIN (SELECT mi.ingredient_id, SUM(mi.quantity * ob.quantity) AS qty
              FROM order_bowls ob
              JOIN menu_ingredients mi ON mi.menu_id = ob.menu_id
              WHERE ob.order_id = NEW.id
                AND NOT EXISTS (SELECT 1 FROM order_items oi
                                WHERE oi.order_bowl_id = ob.id AND oi.action = 'removed'
                                  AND oi.ingredient_id = mi.ingredient_id)
              GROUP BY mi.ingredient_id) used ON used.ingredient_id = i.id
        SET i.stock = i.stock + used.qty;
    END IF;
END$$

DELIMITER ;
