-- Migration 017: product_stock
-- Añade control de stock por unidad a la tabla products.
-- stock_quantity NULL significa sin límite (ilimitado).
-- stock_quantity >= 0 activa el control de stock.

ALTER TABLE products
    ADD COLUMN stock_quantity INT UNSIGNED NULL DEFAULT NULL
        COMMENT 'Stock disponible. NULL = sin límite. 0 = agotado.' AFTER is_active;
