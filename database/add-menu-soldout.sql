-- =====================================================================
--  Menu sold out when a default ingredient is sold out — update an EXISTING malatang_ec   [Myat]
--  Run this in phpMyAdmin (SQL tab). It does not delete any data. Safe to run again.
--  (New databases made from create-tables.sql already have this.)
--
--  海鮮麻辣湯 has ホタテ from the start. ホタテ 品切れ → 海鮮麻辣湯 売り切れ
--  (menu page / customize / cart / order all stop it). ホタテ back in stock → can order again.
--  Only fn_bowl_price changes: 2 checks after the menu check.
-- =====================================================================
USE malatang_ec;

DROP FUNCTION IF EXISTS fn_bowl_price;

DELIMITER $$

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
    -- A default ingredient of the menu is sold out → the whole menu is sold out [Myat]
    IF EXISTS (SELECT 1 FROM menu_ingredients mi JOIN ingredients i ON i.id = mi.ingredient_id
               WHERE mi.menu_id = p_menu_id AND i.is_deleted = 0 AND i.is_available = 0) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Menu is sold out';
    END IF;
    -- ...or there is not enough of it for this many bowls
    IF EXISTS (SELECT 1 FROM menu_ingredients mi JOIN ingredients i ON i.id = mi.ingredient_id
               WHERE mi.menu_id = p_menu_id AND i.is_deleted = 0 AND i.stock < mi.quantity * p_quantity) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Ingredient is sold out';
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
