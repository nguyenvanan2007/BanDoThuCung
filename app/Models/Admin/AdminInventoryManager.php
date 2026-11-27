<?php

declare(strict_types=1);

namespace App\Models\Admin;

use App\Models\InventoryMovement;
use DomainException;
use PDO;
use Throwable;

final class AdminInventoryManager
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function snapshot(): array
    {
        $statement = $this->connection->query(
            'SELECT p.id AS product_id, p.sku AS product_sku, p.name AS product_name,'
            . ' p.image_path AS product_image_path, p.stock_quantity AS product_stock,'
            . ' p.low_stock_threshold, p.is_active AS product_active,'
            . ' v.id AS variant_id, v.sku AS variant_sku, v.name AS variant_name,'
            . ' v.stock_quantity AS variant_stock, v.is_active AS variant_active'
            . ' FROM products p LEFT JOIN product_variants v'
            . ' ON v.product_id = p.id AND v.is_active = 1'
            . ' WHERE p.is_demo = 0 ORDER BY p.name, v.name'
        );

        return array_map(
            static function (array $row): array {
                $hasVariant = $row['variant_id'] !== null;

                return [
                    'productId' => (string) $row['product_id'],
                    'productSku' => (string) $row['product_sku'],
                    'productName' => (string) $row['product_name'],
                    'imagePath' => safe_product_image_path($row['product_image_path'] ?? null),
                    'variantId' => $hasVariant ? (string) $row['variant_id'] : null,
                    'variantSku' => $hasVariant ? (string) $row['variant_sku'] : null,
                    'variantName' => $hasVariant ? (string) $row['variant_name'] : null,
                    'stockQuantity' => (int) ($hasVariant ? $row['variant_stock'] : $row['product_stock']),
                    'lowStockThreshold' => (int) $row['low_stock_threshold'],
                    'isActive' => (int) $row['product_active'] === 1
                        && (!$hasVariant || (int) $row['variant_active'] === 1),
                    'hasVariant' => $hasVariant,
                ];
            },
            $statement->fetchAll()
        );
    }

    public function history(int $limit = 100): array
    {
        $limit = max(1, min($limit, 500));
        $statement = $this->connection->query(
            'SELECT m.id, m.movement_type, m.quantity_delta, m.quantity_before, m.quantity_after,'
            . ' m.reason, m.created_at, p.name AS product_name, p.sku AS product_sku,'
            . ' v.name AS variant_name, u.name AS changed_by_name, o.order_number'
            . ' FROM inventory_movements m'
            . ' INNER JOIN products p ON p.id = m.product_id'
            . ' LEFT JOIN product_variants v ON v.id = m.product_variant_id'
            . ' LEFT JOIN users u ON u.id = m.changed_by_user_id'
            . ' LEFT JOIN orders o ON o.id = m.order_id'
            . ' ORDER BY m.created_at DESC, m.id DESC LIMIT ' . $limit
        );

        return $statement->fetchAll();
    }

    public function adjust(
        string $productId,
        ?string $variantId,
        int $delta,
        string $type,
        string $reason,
        string $userId
    ): void {
        if ($delta === 0 || !in_array($type, ['restock', 'adjustment'], true)
            || ($type === 'restock' && $delta < 1)
            || mb_strlen($reason) < 3 || mb_strlen($reason) > 255) {
            throw new DomainException('Thông tin điều chỉnh tồn kho không hợp lệ.');
        }

        $this->connection->beginTransaction();
        try {
            $lockClause = $this->connection->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
                ? ' FOR UPDATE'
                : '';
            $productStatement = $this->connection->prepare(
                'SELECT id, stock_quantity FROM products WHERE id = :id AND is_demo = 0' . $lockClause
            );
            $productStatement->execute(['id' => $productId]);
            $product = $productStatement->fetch();
            if (!is_array($product)) {
                throw new DomainException('Không tìm thấy sản phẩm cần điều chỉnh.');
            }

            if ($variantId !== null) {
                $variantStatement = $this->connection->prepare(
                    'SELECT id, stock_quantity FROM product_variants'
                    . ' WHERE id = :variant_id AND product_id = :product_id' . $lockClause
                );
                $variantStatement->execute([
                    'variant_id' => $variantId,
                    'product_id' => $productId,
                ]);
                $variant = $variantStatement->fetch();
                if (!is_array($variant)) {
                    throw new DomainException('Biến thể không thuộc sản phẩm đã chọn.');
                }
                $before = (int) $variant['stock_quantity'];
                $after = $before + $delta;
                if ($after < 0 || $after > 1000000000) {
                    throw new DomainException('Tồn kho biến thể sau điều chỉnh phải từ 0 đến 1.000.000.000.');
                }

                $updateVariant = $this->connection->prepare(
                    'UPDATE product_variants SET stock_quantity = :stock WHERE id = :id'
                );
                $updateVariant->execute(['stock' => $after, 'id' => $variantId]);
                $sum = $this->connection->prepare(
                    'SELECT COALESCE(SUM(stock_quantity), 0) FROM product_variants'
                    . ' WHERE product_id = :product_id AND is_active = 1'
                );
                $sum->execute(['product_id' => $productId]);
                $totalStock = (int) $sum->fetchColumn();
                if ($totalStock > 1000000000) {
                    throw new DomainException('Tổng tồn kho biến thể vượt quá giới hạn.');
                }
                $updateProduct = $this->connection->prepare(
                    'UPDATE products SET stock_quantity = :stock WHERE id = :id'
                );
                $updateProduct->execute(['stock' => $totalStock, 'id' => $productId]);
            } else {
                $activeVariants = $this->connection->prepare(
                    'SELECT COUNT(*) FROM product_variants WHERE product_id = :product_id AND is_active = 1'
                );
                $activeVariants->execute(['product_id' => $productId]);
                if ((int) $activeVariants->fetchColumn() > 0) {
                    throw new DomainException('Hãy điều chỉnh tồn kho theo từng biến thể.');
                }
                $before = (int) $product['stock_quantity'];
                $after = $before + $delta;
                if ($after < 0 || $after > 1000000000) {
                    throw new DomainException('Tồn kho sau điều chỉnh phải từ 0 đến 1.000.000.000.');
                }
                $updateProduct = $this->connection->prepare(
                    'UPDATE products SET stock_quantity = :stock WHERE id = :id'
                );
                $updateProduct->execute(['stock' => $after, 'id' => $productId]);
            }

            InventoryMovement::record(
                $this->connection,
                $productId,
                $variantId,
                null,
                $userId,
                $type,
                $before,
                $after,
                $reason
            );
            $this->connection->commit();
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }
    }
}
