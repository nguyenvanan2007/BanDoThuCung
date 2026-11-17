<?php

declare(strict_types=1);

namespace App\Models;

use DomainException;
use PDO;
use Throwable;

final class Order
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function createForCustomer(
        string $userId,
        array $cartItems,
        string $recipientName,
        string $phone,
        string $address,
        int $shippingFee
    ): string {
        if ($cartItems === []) {
            throw new DomainException('Giỏ hàng đang trống.');
        }

        $this->connection->beginTransaction();

        try {
            $customerStatement = $this->connection->prepare(
                'SELECT id FROM users WHERE id = :id AND role = :role AND is_active = :is_active LIMIT 1'
            );
            $customerStatement->execute([
                'id' => $userId,
                'role' => 'customer',
                'is_active' => 1,
            ]);
            if ($customerStatement->fetchColumn() === false) {
                throw new DomainException('Tài khoản khách hàng không còn hoạt động.');
            }

            $products = [];
            $subtotal = 0;
            $productIds = array_keys($cartItems);
            usort($productIds, static fn (string|int $left, string|int $right): int => (int) $left <=> (int) $right);

            $lockClause = $this->connection->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
                ? ' FOR UPDATE'
                : '';
            $selectProduct = $this->connection->prepare(
                'SELECT id, sku, name, image_path, price_vnd, stock_quantity, is_active, is_demo'
                . ' FROM products WHERE id = :id' . $lockClause
            );

            foreach ($productIds as $productId) {
                $quantity = $cartItems[$productId] ?? null;
                if (!is_int($quantity) || $quantity < 1) {
                    throw new DomainException('Số lượng trong giỏ hàng không hợp lệ.');
                }

                $selectProduct->execute(['id' => (string) $productId]);
                $product = $selectProduct->fetch();

                if (!is_array($product) || (int) $product['is_active'] !== 1) {
                    throw new DomainException('Một sản phẩm trong giỏ không còn được bán.');
                }

                if ((int) $product['is_demo'] === 1) {
                    throw new DomainException('Không thể đặt hàng với sản phẩm dữ liệu minh họa.');
                }

                $price = (int) $product['price_vnd'];
                if ((int) $product['stock_quantity'] < $quantity) {
                    throw new DomainException(
                        sprintf('Sản phẩm "%s" không đủ tồn kho.', (string) $product['name'])
                    );
                }

                if ($price > intdiv(PHP_INT_MAX, $quantity)) {
                    throw new DomainException('Tổng giá trị đơn hàng vượt quá giới hạn.');
                }

                $lineTotal = $price * $quantity;
                if ($subtotal > PHP_INT_MAX - $lineTotal) {
                    throw new DomainException('Tổng giá trị đơn hàng vượt quá giới hạn.');
                }

                $subtotal += $lineTotal;
                $products[(string) $productId] = [
                    'id' => (string) $product['id'],
                    'sku' => (string) $product['sku'],
                    'name' => (string) $product['name'],
                    'imagePath' => safe_product_image_path($product['image_path'] ?? null) ?? '',
                    'price' => $price,
                    'quantity' => $quantity,
                    'subtotal' => $lineTotal,
                ];
            }

            if ($shippingFee < 0 || $subtotal > PHP_INT_MAX - $shippingFee) {
                throw new DomainException('Phí giao hàng hoặc tổng giá trị đơn hàng không hợp lệ.');
            }

            $total = $subtotal + $shippingFee;
            $orderNumber = 'PH-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(4)));
            $insertOrder = $this->connection->prepare(
                'INSERT INTO orders'
                . ' (user_id, order_number, recipient_name, phone, shipping_address,'
                . ' subtotal_vnd, shipping_fee_vnd, total_vnd, payment_method, status)'
                . ' VALUES'
                . ' (:user_id, :order_number, :recipient_name, :phone, :shipping_address,'
                . ' :subtotal_vnd, :shipping_fee_vnd, :total_vnd, :payment_method, :status)'
            );
            $insertOrder->execute([
                'user_id' => $userId,
                'order_number' => $orderNumber,
                'recipient_name' => $recipientName,
                'phone' => $phone,
                'shipping_address' => $address,
                'subtotal_vnd' => $subtotal,
                'shipping_fee_vnd' => $shippingFee,
                'total_vnd' => $total,
                'payment_method' => 'cod',
                'status' => 'pending',
            ]);
            $orderId = (string) $this->connection->lastInsertId();

            $insertStatusHistory = $this->connection->prepare(
                'INSERT INTO order_status_history (order_id, status, changed_by_user_id)'
                . ' VALUES (:order_id, :status, :changed_by_user_id)'
            );
            $insertStatusHistory->execute([
                'order_id' => $orderId,
                'status' => 'pending',
                'changed_by_user_id' => $userId,
            ]);

            $decreaseStock = $this->connection->prepare(
                'UPDATE products SET stock_quantity = stock_quantity - :quantity'
                . ' WHERE id = :id AND is_active = 1 AND is_demo = 0'
                . ' AND stock_quantity >= :required_quantity'
            );
            $insertItem = $this->connection->prepare(
                'INSERT INTO order_items'
                .                 ' (order_id, product_id, product_sku, product_name, product_image_path, quantity, unit_price_vnd, subtotal_vnd)'
                . ' VALUES'
                . ' (:order_id, :product_id, :product_sku, :product_name, :product_image_path, :quantity, :unit_price_vnd, :subtotal_vnd)'
            );

            foreach ($products as $product) {
                $decreaseStock->execute([
                    'quantity' => $product['quantity'],
                    'id' => $product['id'],
                    'required_quantity' => $product['quantity'],
                ]);
                if ($decreaseStock->rowCount() !== 1) {
                    throw new DomainException(
                        sprintf('Tồn kho sản phẩm "%s" vừa thay đổi. Hãy kiểm tra lại giỏ hàng.', $product['name'])
                    );
                }

                $insertItem->execute([
                    'order_id' => $orderId,
                    'product_id' => $product['id'],
                    'product_sku' => $product['sku'],
                    'product_name' => $product['name'],
                    'product_image_path' => $product['imagePath'],
                    'quantity' => $product['quantity'],
                    'unit_price_vnd' => $product['price'],
                    'subtotal_vnd' => $product['subtotal'],
                ]);
            }

            $this->connection->commit();

            return $orderId;
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }

            throw $exception;
        }
    }

    public function findAllForCustomer(string $userId): array
    {
        $statement = $this->connection->prepare(
            'SELECT o.id, o.order_number, o.total_vnd, o.status, o.created_at,'
            . ' (SELECT COALESCE(SUM(oi.quantity), 0) FROM order_items oi WHERE oi.order_id = o.id) AS item_count'
            . ' FROM orders o WHERE o.user_id = :user_id ORDER BY o.created_at DESC, o.id DESC'
        );
        $statement->execute(['user_id' => $userId]);

        return $statement->fetchAll();
    }

    public function findForCustomer(string $userId, string $orderId): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT id, order_number, recipient_name, phone, shipping_address, subtotal_vnd,'
            . ' shipping_fee_vnd, total_vnd, payment_method, status, created_at'
            . ' FROM orders WHERE id = :id AND user_id = :user_id LIMIT 1'
        );
        $statement->execute([
            'id' => $orderId,
            'user_id' => $userId,
        ]);
        $order = $statement->fetch();

        if (!is_array($order)) {
            return null;
        }

        $items = $this->connection->prepare(
            'SELECT product_sku, product_name, product_image_path, quantity, unit_price_vnd, subtotal_vnd'
            . ' FROM order_items WHERE order_id = :order_id ORDER BY id ASC'
        );
        $items->execute(['order_id' => $orderId]);
        $order['items'] = array_map(
            static function (array $item): array {
                $item['product_image_path'] = safe_product_image_path($item['product_image_path'] ?? null) ?? '';

                return $item;
            },
            $items->fetchAll()
        );
        $order['statusHistory'] = $this->findStatusHistory($orderId);

        return $order;
    }

    private function findStatusHistory(string $orderId): array
    {
        $statement = $this->connection->prepare(
            'SELECT h.status, h.created_at, u.name AS changed_by_name'
            . ' FROM order_status_history h'
            . ' LEFT JOIN users u ON u.id = h.changed_by_user_id'
            . ' WHERE h.order_id = :order_id'
            . ' ORDER BY h.created_at ASC, h.id ASC'
        );
        $statement->execute(['order_id' => $orderId]);

        return $statement->fetchAll();
    }
}
