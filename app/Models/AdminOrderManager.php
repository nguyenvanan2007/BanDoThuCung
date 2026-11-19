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
        $history = $this->connection->prepare(
            'SELECT h.status, h.created_at, u.name AS changed_by_name'
            . ' FROM order_status_history h'
            . ' LEFT JOIN users u ON u.id = h.changed_by_user_id'
            . ' WHERE h.order_id = :order_id'
            . ' ORDER BY h.created_at ASC, h.id ASC'
        );
        $history->execute(['order_id' => $id]);
        $order['statusHistory'] = $history->fetchAll();

        return $order;
    }

    public function allowedTransitions(string $status): array
    {
        return self::STATUS_TRANSITIONS[$status] ?? [];
    }

    public function cancelForCustomer(string $orderId, string $customerUserId): bool
    {
        return $this->updateStatus($orderId, 'cancelled', $customerUserId, $customerUserId);
    }

    public function updateStatus(
        string $orderId,
        string $nextStatus,
        string $changedByUserId,
        ?string $customerUserId = null
    ): bool
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
                'SELECT status, user_id FROM orders WHERE id = :id' . $lockClause
            );
            $currentStatement->execute(['id' => $orderId]);
            $currentOrder = $currentStatement->fetch();

            if (!is_array($currentOrder)) {
                throw new DomainException('Không tìm thấy đơn hàng.');
            }
            if ($customerUserId !== null && (string) $currentOrder['user_id'] !== $customerUserId) {
                throw new DomainException('Không tìm thấy đơn hàng.');
            }
            $currentStatus = (string) $currentOrder['status'];

            if ($currentStatus === $nextStatus) {
                $this->connection->commit();
                return false;
            }

            if ($customerUserId !== null && $currentStatus !== 'pending') {
                throw new DomainException('Chỉ có thể hủy đơn khi đơn hàng còn chờ xác nhận.');
            }

            if (!in_array($nextStatus, self::STATUS_TRANSITIONS[$currentStatus] ?? [], true)) {
                throw new DomainException('Không thể chuyển đơn hàng sang trạng thái đã chọn.');
            }

            if ($nextStatus === 'cancelled') {
                $items = $this->connection->prepare(
                    'SELECT product_id, product_variant_id, quantity FROM order_items WHERE order_id = :order_id'
                );
                $items->execute(['order_id' => $orderId]);
                $restoreStock = $this->connection->prepare(
                    'UPDATE products SET stock_quantity = stock_quantity + :quantity WHERE id = :id'
                );
                $restoreVariantStock = $this->connection->prepare(
                    'UPDATE product_variants SET stock_quantity = stock_quantity + :quantity'
                    . ' WHERE id = :id AND product_id = :product_id'
                );

                foreach ($items->fetchAll() as $item) {
                    $restoreStock->execute([
                        'quantity' => (int) $item['quantity'],
                        'id' => (string) $item['product_id'],
                    ]);
                    if ($restoreStock->rowCount() !== 1) {
                        throw new DomainException('Không thể hoàn lại tồn kho cho đơn hàng này.');
                    }
                    if ($item['product_variant_id'] !== null) {
                        $restoreVariantStock->execute([
                            'quantity' => (int) $item['quantity'],
                            'id' => (string) $item['product_variant_id'],
                            'product_id' => (string) $item['product_id'],
                        ]);
                        if ($restoreVariantStock->rowCount() !== 1) {
                            throw new DomainException('Không thể hoàn lại tồn kho biến thể cho đơn hàng này.');
                        }
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
            $history = $this->connection->prepare(
                'INSERT INTO order_status_history (order_id, status, changed_by_user_id)'
                . ' VALUES (:order_id, :status, :changed_by_user_id)'
            );
            $history->execute([
                'order_id' => $orderId,
                'status' => $nextStatus,
                'changed_by_user_id' => $changedByUserId,
            ]);
            $this->connection->commit();
            return true;
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }

            throw $exception;
        }
    }
}
