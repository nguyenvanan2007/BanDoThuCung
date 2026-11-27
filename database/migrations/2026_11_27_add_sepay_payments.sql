ALTER TABLE orders
    MODIFY COLUMN payment_method ENUM('cod', 'vnpay', 'sepay', 'legacy_online')
        NOT NULL DEFAULT 'cod';

UPDATE orders
SET payment_method = 'legacy_online'
WHERE payment_method = 'vnpay';

ALTER TABLE orders
    MODIFY COLUMN payment_method ENUM('cod', 'sepay', 'legacy_online')
        NOT NULL DEFAULT 'cod';
