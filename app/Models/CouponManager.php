<?php

declare(strict_types=1);

namespace App\Models;

use DomainException;
use PDO;

final class CouponManager
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function quote(string $code, int $subtotal): array
    {
        $code = strtoupper(trim($code));
        if ($code === '') {
            return ['discount' => 0, 'error' => null];
        }

        $statement = $this->connection->prepare(
            'SELECT id, code, discount_type, discount_value, min_order_vnd, max_uses, used_count'
            . ' FROM coupons WHERE code = :code AND is_active = 1'
            . ' AND starts_at <= NOW() AND ends_at >= NOW() LIMIT 1'
        );
        $statement->execute(['code' => $code]);
        $coupon = $statement->fetch();
        if (!is_array($coupon)) {
            return ['discount' => 0, 'error' => 'Mã giảm giá không tồn tại, đã hết hạn hoặc đang tạm ngưng.'];
        }
        if ($subtotal < (int) $coupon['min_order_vnd']) {
            return ['discount' => 0, 'error' => 'Đơn hàng chưa đạt giá trị tối thiểu của mã giảm giá.'];
        }
        if ($coupon['max_uses'] !== null && (int) $coupon['used_count'] >= (int) $coupon['max_uses']) {
            return ['discount' => 0, 'error' => 'Mã giảm giá đã hết lượt sử dụng.'];
        }

        return [
            'discount' => $this->discountFor($coupon, $subtotal),
            'error' => null,
            'coupon' => $coupon,
        ];
    }

    public function findAll(): array
    {
        return $this->connection->query(
            'SELECT id, code, discount_type, discount_value, starts_at, ends_at, min_order_vnd,'
            . ' max_uses, used_count, allow_stackable, is_active FROM coupons'
            . ' ORDER BY created_at DESC, id DESC'
        )->fetchAll();
    }

    public function findById(string $id): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT id, code, discount_type, discount_value, starts_at, ends_at, min_order_vnd,'
            . ' max_uses, used_count, allow_stackable, is_active FROM coupons WHERE id = :id LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $coupon = $statement->fetch();

        return is_array($coupon) ? $coupon : null;
    }

    public function save(?string $id, array $coupon): void
    {
        if ($id === null) {
            $statement = $this->connection->prepare(
                'INSERT INTO coupons (code, discount_type, discount_value, starts_at, ends_at,'
                . ' min_order_vnd, max_uses, allow_stackable, is_active)'
                . ' VALUES (:code, :type, :value, :starts, :ends, :minimum, :max_uses, :stackable, :active)'
            );
        } else {
            $statement = $this->connection->prepare(
                'UPDATE coupons SET code = :code, discount_type = :type, discount_value = :value,'
                . ' starts_at = :starts, ends_at = :ends, min_order_vnd = :minimum,'
                . ' max_uses = :max_uses, allow_stackable = :stackable, is_active = :active'
                . ' WHERE id = :id'
            );
        }
        $statement->execute([
            'code' => $coupon['code'],
            'type' => $coupon['type'],
            'value' => $coupon['value'],
            'starts' => $coupon['starts'],
            'ends' => $coupon['ends'],
            'minimum' => $coupon['minimum'],
            'max_uses' => $coupon['max_uses'],
            'stackable' => $coupon['stackable'] ? 1 : 0,
            'active' => $coupon['active'] ? 1 : 0,
        ] + ($id === null ? [] : ['id' => $id]));
    }

    public function setActive(string $id, bool $isActive): bool
    {
        $statement = $this->connection->prepare(
            'UPDATE coupons SET is_active = :active WHERE id = :id'
        );
        $statement->execute(['active' => $isActive ? 1 : 0, 'id' => $id]);

        return $statement->rowCount() === 1;
    }

    public static function releaseForOrder(PDO $connection, string $orderId): void
    {
        $lockClause = $connection->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
            ? ' FOR UPDATE'
            : '';
        $statement = $connection->prepare(
            'SELECT coupon_id, status FROM coupon_redemptions WHERE order_id = :order_id' . $lockClause
        );
        $statement->execute(['order_id' => $orderId]);
        $redemption = $statement->fetch();
        if (!is_array($redemption) || $redemption['status'] === 'released') {
            return;
        }

        $decrement = $connection->prepare(
            'UPDATE coupons SET used_count = IF(used_count > 0, used_count - 1, 0) WHERE id = :id'
        );
        $decrement->execute(['id' => (string) $redemption['coupon_id']]);
        $release = $connection->prepare(
            'UPDATE coupon_redemptions SET status = :status WHERE order_id = :order_id'
        );
        $release->execute(['status' => 'released', 'order_id' => $orderId]);
    }

    public static function discountFor(array $coupon, int $subtotal): int
    {
        $value = (int) $coupon['discount_value'];
        if ($coupon['discount_type'] === 'percent') {
            $discount = intdiv($subtotal, 100) * $value + intdiv(($subtotal % 100) * $value, 100);
        } else {
            $discount = $value;
        }

        return min($discount, $subtotal);
    }
}
