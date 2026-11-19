CREATE TABLE IF NOT EXISTS product_categories (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(140) NOT NULL,
    species ENUM('dog', 'cat') NOT NULL,
    food_type ENUM('dry', 'wet') NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_product_categories_slug (slug),
    KEY idx_product_categories_type_active (species, food_type, is_active)
) ENGINE=InnoDB
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

INSERT IGNORE INTO product_categories (name, slug, species, food_type)
VALUES
    ('Thức ăn khô cho chó', 'dog-dry', 'dog', 'dry'),
    ('Thức ăn ướt cho chó', 'dog-wet', 'dog', 'wet'),
    ('Thức ăn khô cho mèo', 'cat-dry', 'cat', 'dry'),
    ('Thức ăn ướt cho mèo', 'cat-wet', 'cat', 'wet');

ALTER TABLE products
    ADD COLUMN category_id BIGINT UNSIGNED NULL AFTER food_type;

UPDATE products p
INNER JOIN product_categories c
    ON c.species = p.species AND c.food_type = p.food_type
SET p.category_id = c.id
WHERE p.category_id IS NULL;

ALTER TABLE products
    MODIFY COLUMN category_id BIGINT UNSIGNED NOT NULL,
    ADD KEY idx_products_category (category_id),
    ADD CONSTRAINT fk_products_category
        FOREIGN KEY (category_id) REFERENCES product_categories (id);

CREATE TABLE IF NOT EXISTS product_images (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id BIGINT UNSIGNED NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_product_images_path (product_id, image_path),
    KEY idx_product_images_order (product_id, sort_order, id),
    CONSTRAINT fk_product_images_product
        FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

INSERT IGNORE INTO product_images (product_id, image_path, sort_order)
SELECT id, image_path, 0
FROM products
WHERE image_path IS NOT NULL AND image_path <> '';

CREATE TABLE IF NOT EXISTS product_variants (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id BIGINT UNSIGNED NOT NULL,
    sku VARCHAR(64) NOT NULL,
    name VARCHAR(120) NOT NULL,
    price_vnd BIGINT UNSIGNED NOT NULL,
    stock_quantity INT UNSIGNED NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_product_variants_sku (sku),
    KEY idx_product_variants_product_active (product_id, is_active),
    CONSTRAINT fk_product_variants_product
        FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

ALTER TABLE order_items
    ADD COLUMN product_variant_id BIGINT UNSIGNED NULL AFTER product_image_path,
    ADD KEY idx_order_items_variant (product_variant_id),
    ADD CONSTRAINT fk_order_items_variant
        FOREIGN KEY (product_variant_id) REFERENCES product_variants (id) ON DELETE SET NULL;
