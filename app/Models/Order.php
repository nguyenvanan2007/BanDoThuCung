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
        if (mb_strlen($recipientName) < 2 || mb_strlen($recipientName) > 100
            || preg_match('/\A[0-9+().\-\s]{7,20}\z/', $phone) !== 1
            || mb_strlen($address) < 10 || mb_strlen($address) > 500) {
            throw new DomainException('Thông tin giao hàng không hợp lệ.');
        }
        if ($shippingFee < 0) {
            throw new DomainException('Phí giao hàng không hợp lệ.');
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
            $cartKeys = array_keys($cartItems);
            usort($cartKeys, static function (string|int $left, string|int $right): int {
                preg_match('/\A([0-9]+)/', (string) $left, $leftMatch);
                preg_match('/\A([0-9]+)/', (string) $right, $rightMatch);

                return (int) ($leftMatch[1] ?? 0) <=> (int) ($rightMatch[1] ?? 0)
                    ?: strcmp((string) $left, (string) $right);
            });

            $lockClause = $this->connection->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
                ? ' FOR UPDATE'
                : '';
            $selectProduct = $this->connection->prepare(
                'SELECT p.id, p.sku, p.name, p.image_path, p.price_vnd, p.stock_quantity,'
                . ' p.is_active, p.is_demo, c.is_active AS category_active'
                . ' FROM products p INNER JOIN product_categories c ON c.id = p.category_id'
                . ' WHERE p.id = :id' . $lockClause
            );
            $selectVariant = $this->connection->prepare(
                'SELECT id, sku, name, price_vnd, stock_quantity, is_active FROM product_variants'
                . ' WHERE id = :id AND product_id = :product_id' . $lockClause
            );

            foreach ($cartKeys as $cartKey) {
                $parts = $this->parseCartKey((string) $cartKey);
                if ($parts === null) {
                    throw new DomainException('Một sản phẩm trong giỏ hàng không hợp lệ.');
                }
                [$productId, $variantId] = $parts;
                $quantity = $cartItems[$cartKey] ?? null;
                if (!is_int($quantity) || $quantity < 1) {
                    throw new DomainException('Số lượng trong giỏ hàng không hợp lệ.');
                }

                $selectProduct->execute(['id' => $productId]);
                $product = $selectProduct->fetch();

                if (!is_array($product) || (int) $product['is_active'] !== 1
                    || (int) $product['category_active'] !== 1) {
                    throw new DomainException('Một sản phẩm trong giỏ không còn được bán.');
                }

                if ((int) $product['is_demo'] === 1) {
                    throw new DomainException('Không thể đặt hàng với sản phẩm dữ liệu minh họa.');
                }

                $variant = null;
                if ($variantId !== null) {
                    $selectVariant->execute(['id' => $variantId, 'product_id' => $productId]);
                    $variant = $selectVariant->fetch();
                    if (!is_array($variant) || (int) $variant['is_active'] !== 1) {
                        throw new DomainException('Một biến thể trong giỏ hàng không còn được bán.');
                    }
                } else {
                    $variantCheck = $this->connection->prepare(
                        'SELECT COUNT(*) FROM product_variants WHERE product_id = :product_id AND is_active = 1'
                    );
                    $variantCheck->execute(['product_id' => $productId]);
                    if ((int) $variantCheck->fetchColumn() > 0) {
                        throw new DomainException('Vui lòng chọn biến thể cho sản phẩm "' . $product['name'] . '".');
                    }
                }

                $price = (int) ($variant['price_vnd'] ?? $product['price_vnd']);
                $availableStock = (int) ($variant['stock_quantity'] ?? $product['stock_quantity']);
                if ($availableStock < $quantity || (int) $product['stock_quantity'] < $quantity) {
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
                $itemSku = (string) ($variant['sku'] ?? $product['sku']);
                $itemName = mb_substr(
                    (string) $product['name'] . ($variant === null ? '' : ' - ' . (string) $variant['name']),
                    0,
                    180
                );
                $products[(string) $cartKey] = [
                    'id' => (string) $product['id'],
                    'variantId' => $variant === null ? null : (string) $variant['id'],
                    'sku' => $itemSku,
                    'name' => $itemName,
                    'imagePath' => safe_product_image_path($product['image_path'] ?? null) ?? '',
                    'price' => $price,
                    'quantity' => $quantity,
                    'subtotal' => $lineTotal,
                ];
            }

            if ($subtotal > PHP_INT_MAX - $shippingFee) {
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
            $decreaseVariantStock = $this->connection->prepare(
                'UPDATE product_variants SET stock_quantity = stock_quantity - :quantity'
                . ' WHERE id = :id AND product_id = :product_id AND is_active = 1'
                . ' AND stock_quantity >= :required_quantity'
            );
            $insertItem = $this->connection->prepare(
                'INSERT INTO order_items'
                . ' (order_id, product_id, product_variant_id, product_sku, product_name, product_image_path, quantity, unit_price_vnd, subtotal_vnd)'
                . ' VALUES'
                . ' (:order_id, :product_id, :product_variant_id, :product_sku, :product_name, :product_image_path, :quantity, :unit_price_vnd, :subtotal_vnd)'
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
                if ($product['variantId'] !== null) {
                    $decreaseVariantStock->execute([
                        'quantity' => $product['quantity'],
                        'id' => $product['variantId'],
                        'product_id' => $product['id'],
                        'required_quantity' => $product['quantity'],
                    ]);
                    if ($decreaseVariantStock->rowCount() !== 1) {
                        throw new DomainException(
                            sprintf('Tồn kho biến thể của "%s" vừa thay đổi. Hãy kiểm tra lại giỏ hàng.', $product['name'])
                        );
                    }
                }

                $insertItem->execute([
                    'order_id' => $orderId,
                    'product_id' => $product['id'],
                    'product_variant_id' => $product['variantId'],
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
            'SELECT o.id, o.order_number, o.recipient_name, o.phone, o.shipping_address, o.subtotal_vnd,'
            . ' o.shipping_fee_vnd, o.total_vnd, o.payment_method, o.status, o.created_at,'
            . ' u.name AS customer_name, u.email AS customer_email'
            . ' FROM orders o INNER JOIN users u ON u.id = o.user_id'
            . ' WHERE o.id = :id AND o.user_id = :user_id LIMIT 1'
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

    private function parseCartKey(string $cartKey): ?array
    {
        if (preg_match('/\A([1-9][0-9]*)(?::v([1-9][0-9]*))?\z/', $cartKey, $matches) !== 1) {
            return null;
        }

        return [$matches[1], isset($matches[2]) ? $matches[2] : null];
    }
}
