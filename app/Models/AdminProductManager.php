<?php

declare(strict_types=1);

namespace App\Models;

use PDO;
use Throwable;

final class AdminProductManager
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function findAll(): array
    {
        $statement = $this->connection->query(
            'SELECT p.id, p.sku, p.name, p.species, p.food_type, c.name AS category_name,'
            . ' p.description, p.image_path, p.price_vnd, p.stock_quantity, p.is_active,'
            . ' (SELECT COUNT(*) FROM product_images i WHERE i.product_id = p.id) AS image_count,'
            . ' (SELECT COUNT(*) FROM product_variants v WHERE v.product_id = p.id AND v.is_active = 1) AS variant_count'
            . ' FROM products p INNER JOIN product_categories c ON c.id = p.category_id'
            . ' WHERE p.is_demo = 0 ORDER BY p.id DESC'
        );

        return array_map(
            fn (array $row): array => $this->mapProduct($row),
            $statement->fetchAll()
        );
    }

    public function findById(string $id): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT p.id, p.sku, p.name, p.species, p.food_type, p.category_id,'
            . ' c.name AS category_name, p.description, p.image_path, p.price_vnd,'
            . ' p.stock_quantity, p.is_active FROM products p'
            . ' INNER JOIN product_categories c ON c.id = p.category_id'
            . ' WHERE p.id = :id AND p.is_demo = 0 LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $product = $statement->fetch();

        if (!is_array($product)) {
            return null;
        }

        $product = $this->mapProduct($product);
        $images = $this->connection->prepare(
            'SELECT image_path FROM product_images WHERE product_id = :product_id ORDER BY sort_order ASC, id ASC'
        );
        $images->execute(['product_id' => $id]);
        $product['images'] = array_values(array_filter(array_map(
            static fn (array $row): ?string => safe_product_image_path($row['image_path'] ?? null),
            $images->fetchAll()
        )));
        if ($product['images'] === [] && $product['imagePath'] !== null) {
            $product['images'] = [$product['imagePath']];
        }

        $variants = $this->connection->prepare(
            'SELECT id, sku, name, price_vnd, stock_quantity, is_active FROM product_variants'
            . ' WHERE product_id = :product_id ORDER BY id ASC'
        );
        $variants->execute(['product_id' => $id]);
        $product['variants'] = array_map(
            static fn (array $row): array => [
                'id' => (string) $row['id'],
                'sku' => (string) $row['sku'],
                'name' => (string) $row['name'],
                'priceVnd' => (int) $row['price_vnd'],
                'stockQuantity' => (int) $row['stock_quantity'],
                'isActive' => (bool) $row['is_active'],
            ],
            $variants->fetchAll()
        );

        return $product;
    }

    public function findCategories(bool $includeInactive = false): array
    {
        $where = $includeInactive ? '' : ' WHERE is_active = 1';

        $categories = $this->connection->query(
            'SELECT id, name, slug, species, food_type AS foodType, is_active AS isActive'
            . ' FROM product_categories' . $where . ' ORDER BY species, food_type, name'
        )->fetchAll();

        return array_map(
            static fn (array $category): array => [
                'id' => (string) $category['id'],
                'name' => (string) $category['name'],
                'slug' => (string) $category['slug'],
                'species' => (string) $category['species'],
                'foodType' => (string) $category['foodType'],
                'isActive' => (int) $category['isActive'] === 1,
            ],
            $categories
        );
    }

    public function createCategory(string $name, string $slug, string $species, string $foodType): void
    {
        $statement = $this->connection->prepare(
            'INSERT INTO product_categories (name, slug, species, food_type)'
            . ' VALUES (:name, :slug, :species, :food_type)'
        );
        $statement->execute([
            'name' => $name,
            'slug' => $slug,
            'species' => $species,
            'food_type' => $foodType,
        ]);
    }

    public function setCategoryActive(string $categoryId, bool $isActive): void
    {
        $statement = $this->connection->prepare(
            'UPDATE product_categories SET is_active = :is_active WHERE id = :id'
        );
        $statement->execute([
            'is_active' => $isActive ? 1 : 0,
            'id' => $categoryId,
        ]);
    }

    public function countActive(): int
    {
        return (int) $this->connection->query(
            'SELECT COUNT(*) FROM products WHERE is_demo = 0 AND is_active = 1'
        )->fetchColumn();
    }

    public function create(array $product): string
    {
        $this->connection->beginTransaction();

        try {
            $statement = $this->connection->prepare(
                'INSERT INTO products'
                . ' (sku, name, species, food_type, category_id, description, image_path, price_vnd, stock_quantity, is_active, is_demo)'
                . ' VALUES'
                . ' (:sku, :name, :species, :food_type, :category_id, :description, :image_path, :price_vnd, :stock_quantity, :is_active, 0)'
            );
            $statement->execute($this->productColumns($product));
            $productId = (string) $this->connection->lastInsertId();
            $this->syncImages($productId, $product['images']);
            $this->syncVariants($productId, $product['variants']);
            $this->connection->commit();

            return $productId;
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }
    }

    public function update(string $id, array $product): void
    {
        $this->connection->beginTransaction();

        try {
            $statement = $this->connection->prepare(
                'UPDATE products SET sku = :sku, name = :name, species = :species, food_type = :food_type,'
                . ' category_id = :category_id, description = :description, image_path = :image_path,'
                . ' price_vnd = :price_vnd, stock_quantity = :stock_quantity, is_active = :is_active'
                . ' WHERE id = :id AND is_demo = 0'
            );
            $statement->execute($this->productColumns($product) + ['id' => $id]);
            $this->syncImages($id, $product['images']);
            $this->syncVariants($id, $product['variants']);
            $this->connection->commit();
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }
    }

    public function delete(string $id): bool
    {
        $this->connection->beginTransaction();

        try {
            $lockClause = $this->connection->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
                ? ' FOR UPDATE'
                : '';
            $lock = $this->connection->prepare(
                'SELECT id FROM products WHERE id = :id AND is_demo = 0' . $lockClause
            );
            $lock->execute(['id' => $id]);
            if ($lock->fetchColumn() === false) {
                $this->connection->commit();
                return false;
            }

            $orders = $this->connection->prepare(
                'SELECT COUNT(*) FROM order_items WHERE product_id = :product_id'
            );
            $orders->execute(['product_id' => $id]);
            if ((int) $orders->fetchColumn() > 0) {
                $this->connection->commit();
                return false;
            }

            $statement = $this->connection->prepare(
                'DELETE FROM products WHERE id = :id AND is_demo = 0'
            );
            $statement->execute(['id' => $id]);
            $deleted = $statement->rowCount() === 1;
            $this->connection->commit();

            return $deleted;
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }
    }

    private function syncImages(string $productId, array $images): void
    {
        $delete = $this->connection->prepare('DELETE FROM product_images WHERE product_id = :product_id');
        $delete->execute(['product_id' => $productId]);

        $insert = $this->connection->prepare(
            'INSERT INTO product_images (product_id, image_path, sort_order)'
            . ' VALUES (:product_id, :image_path, :sort_order)'
        );
        foreach (array_values(array_unique($images)) as $order => $imagePath) {
            $insert->execute([
                'product_id' => $productId,
                'image_path' => $imagePath,
                'sort_order' => $order,
            ]);
        }
    }

    private function syncVariants(string $productId, array $variants): void
    {
        $existingStatement = $this->connection->prepare(
            'SELECT id FROM product_variants WHERE product_id = :product_id'
        );
        $existingStatement->execute(['product_id' => $productId]);
        $existingIds = array_fill_keys(
            array_map('strval', $existingStatement->fetchAll(PDO::FETCH_COLUMN)),
            true
        );
        $submittedIds = [];

        foreach ($variants as $variant) {
            if ($variant['id'] !== '') {
                if (!isset($existingIds[$variant['id']])) {
                    continue;
                }
                $update = $this->connection->prepare(
                    'UPDATE product_variants SET sku = :sku, name = :name, price_vnd = :price_vnd,'
                    . ' stock_quantity = :stock_quantity, is_active = 1 WHERE id = :id AND product_id = :product_id'
                );
                $update->execute($variant + ['product_id' => $productId]);
                $submittedIds[$variant['id']] = true;
                continue;
            }

            $insert = $this->connection->prepare(
                'INSERT INTO product_variants (product_id, sku, name, price_vnd, stock_quantity)'
                . ' VALUES (:product_id, :sku, :name, :price_vnd, :stock_quantity)'
            );
            $insert->execute([
                'product_id' => $productId,
                'sku' => $variant['sku'],
                'name' => $variant['name'],
                'price_vnd' => $variant['price_vnd'],
                'stock_quantity' => $variant['stock_quantity'],
            ]);
        }

        $deactivate = $this->connection->prepare(
            'UPDATE product_variants SET is_active = 0 WHERE id = :id AND product_id = :product_id'
        );
        foreach (array_keys($existingIds) as $existingId) {
            if (!isset($submittedIds[$existingId])) {
                $deactivate->execute(['id' => $existingId, 'product_id' => $productId]);
            }
        }

        $summary = $this->connection->prepare(
            'SELECT MIN(price_vnd) AS min_price, COALESCE(SUM(stock_quantity), 0) AS total_stock'
            . ' FROM product_variants WHERE product_id = :product_id AND is_active = 1'
        );
        $summary->execute(['product_id' => $productId]);
        $variantSummary = $summary->fetch();
        if (is_array($variantSummary) && $variantSummary['min_price'] !== null) {
            $updateProduct = $this->connection->prepare(
                'UPDATE products SET price_vnd = :price_vnd, stock_quantity = :stock_quantity WHERE id = :id'
            );
            $updateProduct->execute([
                'price_vnd' => (int) $variantSummary['min_price'],
                'stock_quantity' => (int) $variantSummary['total_stock'],
                'id' => $productId,
            ]);
        }
    }

    private function mapProduct(array $row): array
    {
        $species = (string) $row['species'];
        $foodType = (string) $row['food_type'];

        return [
            'id' => (string) $row['id'],
            'sku' => (string) $row['sku'],
            'name' => (string) $row['name'],
            'species' => $species,
            'speciesLabel' => $species === 'dog' ? 'Cho chó' : 'Cho mèo',
            'foodType' => $foodType,
            'foodTypeLabel' => (string) ($row['category_name'] ?? ($foodType === 'dry' ? 'Thức ăn khô' : 'Thức ăn ướt')),
            'categoryId' => (string) ($row['category_id'] ?? ''),
            'description' => (string) ($row['description'] ?? ''),
            'imagePath' => safe_product_image_path($row['image_path'] ?? null),
            'priceVnd' => (int) $row['price_vnd'],
            'stockQuantity' => (int) $row['stock_quantity'],
            'isActive' => (int) $row['is_active'] === 1,
            'imageCount' => (int) ($row['image_count'] ?? 0),
            'variantCount' => (int) ($row['variant_count'] ?? 0),
        ];
    }

    private function productColumns(array $product): array
    {
        return [
            'sku' => $product['sku'],
            'name' => $product['name'],
            'species' => $product['species'],
            'food_type' => $product['food_type'],
            'category_id' => $product['category_id'],
            'description' => $product['description'],
            'image_path' => $product['image_path'],
            'price_vnd' => $product['price_vnd'],
            'stock_quantity' => $product['stock_quantity'],
            'is_active' => $product['is_active'],
        ];
    }
}
