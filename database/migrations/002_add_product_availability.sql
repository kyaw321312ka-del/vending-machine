ALTER TABLE products
    ADD COLUMN is_enabled TINYINT(1) NOT NULL DEFAULT 0
    AFTER quantity_available;

UPDATE products
SET is_enabled = 1
WHERE quantity_available > 0;
