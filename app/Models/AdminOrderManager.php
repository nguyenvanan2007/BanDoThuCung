<?php

declare(strict_types=1);

namespace App\Models;

use DomainException;
use PDO;
use Throwable;

final class AdminOrderManager
{
    private const STATUS_TRANSITIONS = [
        'pending' => ['confirmed', 'cancelled'],
        'confirmed' => ['shipping', 'cancelled'],
        'shipping' => ['completed'],
        'completed' => [],
        'cancelled' => [],
    ];

    public function __construct(private readonly PDO $connection)
    {
    }

    public function dashboardStats(): array
    {
        $orderCount = (int) $this->connection->query('SELECT COUNT(*) FROM orders')->fetchColumn();
        $pendingCount = (int) $this->connection->query(
            "SELECT COUNT(*) FROM orders WHERE status = 'pending'"
        )->fetchColumn();

        return [
            'orderCount' => $orderCount,
            'pendingCount' => $pendingCount,
        ];
    }

    public function findAll(): array
    {
        $statement = $this->connection->query(
            'SELECT o.id, o.order_number, o.recipient_name, o.total_vnd, o.status, o.created_at,'
            . ' u.name AS customer_name,'
            . ' (SELECT COALESCE(SUM(oi.quantity), 0) FROM order_items oi WHERE oi.order_id = o.id) AS item_count'
            . ' FROM orders o INNER JOIN users u ON u.id = o.user_id'
            . ' ORDER BY o.created_at DESC, o.id DESC'
        );

        return $statement->fetchAll();
    }

    public function findById(string $id): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT o.id, o.order_number, o.recipient_name, o.phone, o.shipping_address,'
            . ' o.subtotal_vnd, o.shipping_fee_vnd, o.total_vnd, o.payment_method, o.status, o.created_at,'
            . ' u.name AS customer_name, u.email AS customer_email'
            . ' FROM orders o INNER JOIN users u ON u.id = o.user_id'
            . ' WHERE o.id = :id LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $order = $statement->fetch();

        if (!is_array($order)) {
            return null;
        }

        $items = $this->connection->prepare(
            'SELECT product_sku, product_name, product_image_path, quantity, unit_price_vnd, subtotal_vnd'
            . ' FROM order_items WHERE order_id = :order_id ORDER BY id ASC'
        );
        $items->execute(['order_id' => $id]);
        $order['items'] = array_map(
            static function (array $item): array {
                $item['product_image_path'] = safe_product_image_path($item['product_image_path'] ?? null) ?? '';

                return $item;
            },
            $items->fetchAll()
        );

        return $order;
    }

    public function allowedTransitions(string $status): array
    {
        return self::STATUS_TRANSITIONS[$status] ?? [];
    }

    public function updateStatus(string $orderId, string $nextStatus): void
    {
        if (!in_array($nextStatus, ['pending', 'confirmed', 'shipping', 'completed', 'cancelled'], true)) {
            throw new DomainException('Trạng thái đơn hàng không hợp lệ.');
        }

        $this->connection->beginTransaction();

        try {
            $lockClause = $this->connection->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
                ? ' FOR UPDATE'
                : '';
            $currentStatement = $this->connection->prepare(
                'SELECT status FROM orders WHERE id = :id' . $lockClause
            );
            $currentStatement->execute(['id' => $orderId]);
            $currentStatus = $currentStatement->fetchColumn();

            if (!is_string($currentStatus)) {
                throw new DomainException('Không tìm thấy đơn hàng.');
            }

            if ($currentStatus === $nextStatus) {
                $this->connection->commit();
                return;
            }

            if (!in_array($nextStatus, self::STATUS_TRANSITIONS[$currentStatus] ?? [], true)) {
                throw new DomainException('Không thể chuyển đơn hàng sang trạng thái đã chọn.');
            }

            if ($nextStatus === 'cancelled') {
                $items = $this->connection->prepare(
                    'SELECT product_id, quantity FROM order_items WHERE order_id = :order_id'
                );
                $items->execute(['order_id' => $orderId]);
                $restoreStock = $this->connection->prepare(
                    'UPDATE products SET stock_quantity = stock_quantity + :quantity WHERE id = :id'
                );

                foreach ($items->fetchAll() as $item) {
                    $restoreStock->execute([
                        'quantity' => (int) $item['quantity'],
                        'id' => (string) $item['product_id'],
                    ]);
                    if ($restoreStock->rowCount() !== 1) {
                        throw new DomainException('Không thể hoàn lại tồn kho cho đơn hàng này.');
                    }
                }
            }

            $update = $this->connection->prepare(
                'UPDATE orders SET status = :status WHERE id = :id'
            );
            $update->execute([
                'status' => $nextStatus,
                'id' => $orderId,
            ]);
            $this->connection->commit();
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }

            throw $exception;
        }
    }
}
