CREATE TABLE IF NOT EXISTS coupons (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(40) NOT NULL,
    discount_type ENUM('fixed', 'percent') NOT NULL,
    discount_value BIGINT UNSIGNED NOT NULL,
    starts_at DATETIME NOT NULL,
    ends_at DATETIME NOT NULL,
    min_order_vnd BIGINT UNSIGNED NOT NULL DEFAULT 0,
    max_uses INT UNSIGNED NULL,
    used_count INT UNSIGNED NOT NULL DEFAULT 0,
    allow_stackable TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_coupons_code (code),
    KEY idx_coupons_active_dates (is_active, starts_at, ends_at)
) ENGINE=InnoDB
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

ALTER TABLE products
    ADD COLUMN low_stock_threshold INT UNSIGNED NOT NULL DEFAULT 5 AFTER stock_quantity;

ALTER TABLE orders
    MODIFY COLUMN payment_method ENUM('cod', 'vnpay') NOT NULL DEFAULT 'cod',
    ADD COLUMN discount_vnd BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER shipping_fee_vnd,
    ADD COLUMN coupon_id BIGINT UNSIGNED NULL AFTER discount_vnd,
    ADD COLUMN coupon_code VARCHAR(40) NULL AFTER coupon_id,
    ADD COLUMN payment_status ENUM('unpaid', 'pending', 'paid', 'failed', 'refunded', 'cancelled')
        NOT NULL DEFAULT 'unpaid' AFTER payment_method,
    ADD KEY idx_orders_payment_status_created (payment_status, created_at),
    ADD CONSTRAINT fk_orders_coupon FOREIGN KEY (coupon_id) REFERENCES coupons (id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS coupon_redemptions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    coupon_id BIGINT UNSIGNED NOT NULL,
    order_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    status ENUM('reserved', 'used', 'released') NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_coupon_redemptions_order (order_id),
    KEY idx_coupon_redemptions_coupon_status (coupon_id, status),
    CONSTRAINT fk_coupon_redemptions_coupon FOREIGN KEY (coupon_id) REFERENCES coupons (id),
    CONSTRAINT fk_coupon_redemptions_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE,
    CONSTRAINT fk_coupon_redemptions_user FOREIGN KEY (user_id) REFERENCES users (id)
) ENGINE=InnoDB
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payment_transactions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id BIGINT UNSIGNED NOT NULL,
    provider VARCHAR(30) NOT NULL,
    transaction_ref VARCHAR(100) NOT NULL,
    amount_vnd BIGINT UNSIGNED NOT NULL,
    status ENUM('pending', 'paid', 'failed', 'cancelled', 'refunded') NOT NULL,
    response_code VARCHAR(20) NULL,
    provider_transaction_no VARCHAR(100) NULL,
    paid_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_payment_transactions_order (order_id),
    UNIQUE KEY uq_payment_transactions_ref (transaction_ref),
    KEY idx_payment_transactions_status (status, created_at),
    CONSTRAINT fk_payment_transactions_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payment_refunds (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id BIGINT UNSIGNED NOT NULL,
    amount_vnd BIGINT UNSIGNED NOT NULL,
    status ENUM('pending', 'completed', 'failed') NOT NULL,
    note VARCHAR(500) NOT NULL,
    processed_by_user_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processed_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_payment_refunds_order (order_id),
    CONSTRAINT fk_payment_refunds_order FOREIGN KEY (order_id) REFERENCES orders (id),
    CONSTRAINT fk_payment_refunds_user FOREIGN KEY (processed_by_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inventory_movements (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id BIGINT UNSIGNED NOT NULL,
    product_variant_id BIGINT UNSIGNED NULL,
    order_id BIGINT UNSIGNED NULL,
    changed_by_user_id BIGINT UNSIGNED NULL,
    movement_type ENUM('restock', 'adjustment', 'order', 'cancellation') NOT NULL,
    quantity_delta INT NOT NULL,
    quantity_before INT UNSIGNED NOT NULL,
    quantity_after INT UNSIGNED NOT NULL,
    reason VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_inventory_movements_created (created_at, id),
    KEY idx_inventory_movements_product_created (product_id, created_at),
    CONSTRAINT fk_inventory_movements_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE,
    CONSTRAINT fk_inventory_movements_variant FOREIGN KEY (product_variant_id) REFERENCES product_variants (id) ON DELETE SET NULL,
    CONSTRAINT fk_inventory_movements_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE SET NULL,
    CONSTRAINT fk_inventory_movements_user FOREIGN KEY (changed_by_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

INSERT INTO inventory_movements
    (product_id, product_variant_id, movement_type, quantity_delta, quantity_before, quantity_after, reason)
SELECT p.id, NULL, 'restock', p.stock_quantity, 0, p.stock_quantity, 'Tồn kho ghi nhận khi nâng cấp'
FROM products p
WHERE p.is_demo = 0 AND p.stock_quantity > 0
    AND NOT EXISTS (
        SELECT 1 FROM product_variants v WHERE v.product_id = p.id AND v.is_active = 1
    );

INSERT INTO inventory_movements
    (product_id, product_variant_id, movement_type, quantity_delta, quantity_before, quantity_after, reason)
SELECT p.id, v.id, 'restock', v.stock_quantity, 0, v.stock_quantity, 'Tồn kho ghi nhận khi nâng cấp'
FROM products p
INNER JOIN product_variants v ON v.product_id = p.id AND v.is_active = 1
WHERE p.is_demo = 0 AND v.stock_quantity > 0;
