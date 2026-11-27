<?php

declare(strict_types=1);

namespace App\Models\Admin;

use App\Models\CouponManager;
use App\Models\InventoryMovement;
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

        return [
            'orderCount' => $orderCount,
            'pendingCount' => $this->pendingConfirmationCount(),
        ];
    }

    public function pendingConfirmationCount(): int
    {
        return (int) $this->connection->query(
            "SELECT COUNT(*) FROM orders WHERE status = 'pending'"
        )->fetchColumn();
    }

    public function findAll(): array
    {
        $statement = $this->connection->query(
            'SELECT o.id, o.order_number, o.recipient_name, o.total_vnd, o.status, o.created_at,'
            . ' o.payment_method, o.payment_status, o.discount_vnd, u.name AS customer_name,'
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
            .             ' o.subtotal_vnd, o.shipping_fee_vnd, o.discount_vnd, o.coupon_code, o.total_vnd,'
            . ' o.payment_method, o.payment_status, o.status, o.created_at,'
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
        $refund = $this->connection->prepare(
            'SELECT r.amount_vnd, r.status, r.note, r.processed_at, u.name AS processed_by_name'
            . ' FROM payment_refunds r LEFT JOIN users u ON u.id = r.processed_by_user_id'
            . ' WHERE r.order_id = :order_id LIMIT 1'
        );
        $refund->execute(['order_id' => $id]);
        $order['refund'] = $refund->fetch() ?: null;

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

    public function recordCodPayment(string $orderId): void
    {
        $this->connection->beginTransaction();
        try {
            $lockClause = $this->connection->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
                ? ' FOR UPDATE'
                : '';
            $statement = $this->connection->prepare(
                'SELECT payment_method, payment_status, status FROM orders WHERE id = :id' . $lockClause
            );
            $statement->execute(['id' => $orderId]);
            $order = $statement->fetch();
            if (!is_array($order)) {
                throw new DomainException('Không tìm thấy đơn hàng.');
            }
            if ($order['payment_method'] !== 'cod' || $order['payment_status'] !== 'unpaid'
                || !in_array($order['status'], ['shipping', 'completed'], true)) {
                throw new DomainException('Chỉ ghi nhận COD đã thu khi đơn đang giao hoặc đã hoàn thành.');
            }

            $update = $this->connection->prepare(
                'UPDATE orders SET payment_status = :payment_status WHERE id = :id'
            );
            $update->execute(['payment_status' => 'paid', 'id' => $orderId]);
            $this->connection->commit();
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }
    }

    public function recordRefundComplete(string $orderId, string $adminUserId, string $note): void
    {
        if (mb_strlen($note) < 3 || mb_strlen($note) > 500) {
            throw new DomainException('Ghi chú hoàn tiền cần có từ 3 đến 500 ký tự.');
        }

        $this->connection->beginTransaction();
        try {
            $lockClause = $this->connection->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
                ? ' FOR UPDATE'
                : '';
            $statement = $this->connection->prepare(
                'SELECT total_vnd, payment_status FROM orders WHERE id = :id' . $lockClause
            );
            $statement->execute(['id' => $orderId]);
            $order = $statement->fetch();
            if (!is_array($order) || $order['payment_status'] !== 'paid') {
                throw new DomainException('Chỉ có thể ghi nhận hoàn tiền cho đơn đã thanh toán.');
            }

            $refund = $this->connection->prepare(
                'INSERT INTO payment_refunds'
                . ' (order_id, amount_vnd, status, note, processed_by_user_id, processed_at)'
                . ' VALUES (:order_id, :amount, :status, :note, :user_id, NOW())'
            );
            $refund->execute([
                'order_id' => $orderId,
                'amount' => (int) $order['total_vnd'],
                'status' => 'completed',
                'note' => $note,
                'user_id' => $adminUserId,
            ]);
            $transaction = $this->connection->prepare(
                'UPDATE payment_transactions SET status = :status WHERE order_id = :order_id AND status = :paid'
            );
            $transaction->execute([
                'status' => 'refunded',
                'order_id' => $orderId,
                'paid' => 'paid',
            ]);
            $update = $this->connection->prepare(
                'UPDATE orders SET payment_status = :status WHERE id = :id'
            );
            $update->execute(['status' => 'refunded', 'id' => $orderId]);
            $this->connection->commit();
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }
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
                'SELECT status, payment_method, payment_status, user_id FROM orders WHERE id = :id' . $lockClause
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
            $currentPaymentMethod = (string) $currentOrder['payment_method'];
            $currentPaymentStatus = (string) $currentOrder['payment_status'];

            if ($currentStatus === $nextStatus) {
                $this->connection->commit();
                return false;
            }

            if ($customerUserId !== null && $currentStatus !== 'pending') {
                throw new DomainException('Chỉ có thể hủy đơn khi đơn hàng còn chờ xác nhận.');
            }
            if ($nextStatus === 'cancelled' && $currentPaymentStatus === 'paid') {
                throw new DomainException('Đơn đã thanh toán; cần hoàn tiền trước khi hủy đơn.');
            }
            if ($nextStatus !== 'cancelled' && in_array($currentPaymentMethod, ['sepay', 'legacy_online'], true)
                && $currentPaymentStatus !== 'paid') {
                throw new DomainException('Chỉ xác nhận đơn thanh toán trực tuyến sau khi đã nhận xác nhận thanh toán.');
            }
            if ($nextStatus !== 'cancelled' && $currentPaymentStatus === 'refunded') {
                throw new DomainException('Không thể tiếp tục xử lý đơn đã hoàn tiền.');
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
                $lockProductStock = $this->connection->prepare(
                    'SELECT stock_quantity FROM products WHERE id = :id' . $lockClause
                );
                $lockVariantStock = $this->connection->prepare(
                    'SELECT stock_quantity FROM product_variants'
                    . ' WHERE id = :id AND product_id = :product_id' . $lockClause
                );

                foreach ($items->fetchAll() as $item) {
                    $productId = (string) $item['product_id'];
                    $quantity = (int) $item['quantity'];
                    $lockProductStock->execute(['id' => $productId]);
                    $productStock = $lockProductStock->fetchColumn();
                    if ($productStock === false || (int) $productStock + $quantity > 1000000000) {
                        throw new DomainException('Không thể hoàn lại tồn kho cho đơn hàng này.');
                    }
                    $restoreStock->execute([
                        'quantity' => $quantity,
                        'id' => $productId,
                    ]);
                    if ($restoreStock->rowCount() !== 1) {
                        throw new DomainException('Không thể hoàn lại tồn kho cho đơn hàng này.');
                    }
                    $variantId = null;
                    $before = (int) $productStock;
                    if ($item['product_variant_id'] !== null) {
                        $variantId = (string) $item['product_variant_id'];
                        $lockVariantStock->execute(['id' => $variantId, 'product_id' => $productId]);
                        $variantStock = $lockVariantStock->fetchColumn();
                        if ($variantStock === false) {
                            $variantId = null;
                        } else {
                            if ((int) $variantStock + $quantity > 1000000000) {
                                throw new DomainException('Không thể hoàn lại tồn kho biến thể cho đơn hàng này.');
                            }
                            $before = (int) $variantStock;
                        }
                    }
                    if ($variantId !== null) {
                        $restoreVariantStock->execute([
                            'quantity' => $quantity,
                            'id' => $variantId,
                            'product_id' => $productId,
                        ]);
                        if ($restoreVariantStock->rowCount() !== 1) {
                            throw new DomainException('Không thể hoàn lại tồn kho biến thể cho đơn hàng này.');
                        }
                    }
                    InventoryMovement::record(
                        $this->connection,
                        $productId,
                        $variantId,
                        $orderId,
                        $changedByUserId,
                        'cancellation',
                        $before,
                        $before + $quantity,
                        'Hủy đơn hàng'
                    );
                }
                CouponManager::releaseForOrder($this->connection, $orderId);
            }

            $update = $this->connection->prepare(
                'UPDATE orders SET status = :status, payment_status = CASE'
                . " WHEN :next_status = 'cancelled' AND payment_status IN ('unpaid', 'pending')"
                . " THEN 'cancelled' ELSE payment_status END WHERE id = :id"
            );
            $update->execute([
                'status' => $nextStatus,
                'next_status' => $nextStatus,
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
