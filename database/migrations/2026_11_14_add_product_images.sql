ALTER TABLE products
    ADD COLUMN image_path VARCHAR(255) NULL AFTER description;

ALTER TABLE order_items
    ADD COLUMN product_image_path VARCHAR(255) NULL AFTER product_name;
