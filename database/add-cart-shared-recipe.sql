-- =====================================================================
--  Family & Friends: remember which shared recipe a cart bowl came from
--  — add to an EXISTING malatang_ec database   [Lin] (FEAT-20)
--  Run this once in phpMyAdmin (SQL tab). It does not delete anything.
--  (New databases made from create-tables.sql already have this column.)
--
--  同じ内容で注文 → cart_bowls.shared_recipe_id = the share
--  → checkout copies it to order_bowls.shared_recipe_id
--  → trigger trg_order_bowls_share_ordered marks the share 'ordered'
-- =====================================================================
USE malatang_ec;

ALTER TABLE cart_bowls
    ADD COLUMN shared_recipe_id INT NULL AFTER spice_level_id,
    ADD CONSTRAINT fk_cart_bowls_shared_recipe
        FOREIGN KEY (shared_recipe_id) REFERENCES recipe_shares(id) ON DELETE SET NULL;
