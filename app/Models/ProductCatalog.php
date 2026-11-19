<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

final class ProductCatalog implements ProductCatalogInterface
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function search(array $filters): array
    {
        $conditions = ['p.is_active = 1', 'c.is_active = 1'];
        $parameters = [];
        $query = \is_string($filters['q'] ?? null) ? trim($filters['q']) : '';
        $species = \in_array($filters['species'] ?? '', ['dog', 'cat'], true)
            ? $filters['species']
            : '';
        $category = \is_string($filters['category'] ?? null) ? trim($filters['category']) : '';

        if ($query !== '') {
            $conditions[] = '(p.name LIKE :name_query OR p.sku LIKE :sku_query'
                . ' OR c.name LIKE :category_query OR EXISTS ('
                . 'SELECT 1 FROM product_variants v WHERE v.product_id = p.id AND v.is_active = 1'
                . ' AND (v.name LIKE :variant_name_query OR v.sku LIKE :variant_sku_query)))';
            $likeQuery = '%' . $query . '%';
            $parameters += [
                'name_query' => $likeQuery,
                'sku_query' => $likeQuery,
                'category_query' => $likeQuery,
                'variant_name_query' => $likeQuery,
                'variant_sku_query' => $likeQuery,
            ];
        }

        if ($species !== '') {
            $conditions[] = 'p.species = :species';
            $parameters['species'] = $species;
        }

        if ($category !== '') {
            $conditions[] = 'c.slug = :category_slug';
            $parameters['category_slug'] = $category;
        }

        $priceRange = \is_string($filters['priceRange'] ?? null) ? $filters['priceRange'] : '';
        if ($priceRange === 'under_100k') {
            $conditions[] = 'COALESCE((SELECT MIN(v.price_vnd) FROM product_variants v'
                . ' WHERE v.product_id = p.id AND v.is_active = 1), p.price_vnd) < :max_price';
            $parameters['max_price'] = 100000;
        } elseif ($priceRange === '100k_200k') {
            $conditions[] = 'COALESCE((SELECT MIN(v.price_vnd) FROM product_variants v'
                . ' WHERE v.product_id = p.id AND v.is_active = 1), p.price_vnd)'
                . ' BETWEEN :min_price AND :max_price';
            $parameters['min_price'] = 100000;
            $parameters['max_price'] = 200000;
        } elseif ($priceRange === 'over_200k') {
            $conditions[] = 'COALESCE((SELECT MIN(v.price_vnd) FROM product_variants v'
                . ' WHERE v.product_id = p.id AND v.is_active = 1), p.price_vnd) > :min_price';
            $parameters['min_price'] = 200000;
        }

        $orderBy = match ($filters['sort'] ?? '') {
            'price_asc' => 'display_price ASC, p.id ASC',
            'price_desc' => 'display_price DESC, p.id ASC',
            default => 'p.name ASC, p.id ASC',
        };

        $sql = 'SELECT p.id, p.sku, p.name, p.species, p.food_type, c.id AS category_id,'
            . ' c.name AS category_name, c.slug AS category_slug, p.description, p.image_path,'
            . ' COALESCE((SELECT MIN(v.price_vnd) FROM product_variants v'
            . ' WHERE v.product_id = p.id AND v.is_active = 1), p.price_vnd) AS display_price,'
            . ' COALESCE((SELECT SUM(v.stock_quantity) FROM product_variants v'
            . ' WHERE v.product_id = p.id AND v.is_active = 1), p.stock_quantity) AS display_stock,'
            . ' EXISTS(SELECT 1 FROM product_variants v WHERE v.product_id = p.id AND v.is_active = 1) AS has_variants,'
            . ' p.is_demo FROM products p INNER JOIN product_categories c ON c.id = p.category_id'
            . ' WHERE ' . implode(' AND ', $conditions)
            . ' ORDER BY ' . $orderBy;
        $statement = $this->connection->prepare($sql);
        $statement->execute($parameters);
        $products = array_map(
            fn (array $row): array => $this->mapProduct($row),
            $statement->fetchAll()
        );

        $this->attachImages($products);

        return $products;
    }

    public function findById(string $id): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT p.id, p.sku, p.name, p.species, p.food_type, c.id AS category_id,'
            . ' c.name AS category_name, c.slug AS category_slug, p.description, p.image_path,'
            . ' COALESCE((SELECT MIN(v.price_vnd) FROM product_variants v'
            . ' WHERE v.product_id = p.id AND v.is_active = 1), p.price_vnd) AS display_price,'
            . ' COALESCE((SELECT SUM(v.stock_quantity) FROM product_variants v'
            . ' WHERE v.product_id = p.id AND v.is_active = 1), p.stock_quantity) AS display_stock,'
            . ' EXISTS(SELECT 1 FROM product_variants v WHERE v.product_id = p.id AND v.is_active = 1) AS has_variants,'
            . ' p.is_demo FROM products p INNER JOIN product_categories c ON c.id = p.category_id'
            . ' WHERE p.id = :id AND p.is_active = 1 AND c.is_active = 1 LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        if (!\is_array($row)) {
            return null;
        }

        $product = $this->mapProduct($row);
        $products = [$product];
        $this->attachImages($products);
        $product = $products[0];

        $variants = $this->connection->prepare(
            'SELECT id, sku, name, price_vnd, stock_quantity FROM product_variants'
            . ' WHERE product_id = :product_id AND is_active = 1 ORDER BY id ASC'
        );
        $variants->execute(['product_id' => $id]);
        $product['variants'] = array_map(
            static fn (array $variant): array => [
                'id' => (string) $variant['id'],
                'sku' => (string) $variant['sku'],
                'name' => (string) $variant['name'],
                'price' => (int) $variant['price_vnd'],
                'stockQuantity' => (int) $variant['stock_quantity'],
            ],
            $variants->fetchAll()
        );

        return $product;
    }

    public function findVariant(string $productId, string $variantId): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT id, product_id, sku, name, price_vnd, stock_quantity'
            . ' FROM product_variants WHERE id = :id AND product_id = :product_id'
            . ' AND is_active = 1 LIMIT 1'
        );
        $statement->execute(['id' => $variantId, 'product_id' => $productId]);
        $variant = $statement->fetch();

        return \is_array($variant) ? [
            'id' => (string) $variant['id'],
            'productId' => (string) $variant['product_id'],
            'sku' => (string) $variant['sku'],
            'name' => (string) $variant['name'],
            'price' => (int) $variant['price_vnd'],
            'stockQuantity' => (int) $variant['stock_quantity'],
        ] : null;
    }

    public function categories(): array
    {
        return $this->connection->query(
            'SELECT id, name, slug, species, food_type AS foodType'
            . ' FROM product_categories WHERE is_active = 1 ORDER BY species, food_type, name'
        )->fetchAll();
    }

    public function isDemoMode(): bool
    {
        return false;
    }

    private function mapProduct(array $row): array
    {
        $species = (string) $row['species'];
        $foodType = (string) $row['food_type'];
        $categoryLabel = (string) $row['category_name'];
        $imagePath = safe_product_image_path($row['image_path'] ?? null);

        return [
            'id' => (string) $row['id'],
            'sku' => (string) $row['sku'],
            'name' => (string) $row['name'],
            'species' => $species,
            'speciesLabel' => $species === 'dog' ? 'Cho chó' : 'Cho mèo',
            'category' => $foodType,
            'categoryLabel' => $categoryLabel,
            'categoryId' => (string) $row['category_id'],
            'categorySlug' => (string) $row['category_slug'],
            'description' => (string) ($row['description'] ?? ''),
            'imagePath' => $imagePath,
            'images' => $imagePath === null ? [] : [$imagePath],
            'price' => (int) $row['display_price'],
            'stockQuantity' => (int) $row['display_stock'],
            'hasVariants' => (int) $row['has_variants'] === 1,
            'icon' => $species === 'dog' ? '🐶' : '🐱',
            'isDemo' => (int) $row['is_demo'] === 1,
            'variants' => [],
        ];
    }

    private function attachImages(array &$products): void
    {
        if ($products === []) {
            return;
        }

        $parameters = [];
        $placeholders = [];
        foreach ($products as $index => $product) {
            $key = 'product_' . $index;
            $placeholders[] = ':' . $key;
            $parameters[$key] = $product['id'];
        }

        $statement = $this->connection->prepare(
            'SELECT product_id, image_path FROM product_images WHERE product_id IN ('
            . implode(', ', $placeholders) . ') ORDER BY sort_order ASC, id ASC'
        );
        $statement->execute($parameters);
        $imagesByProduct = [];
        foreach ($statement->fetchAll() as $row) {
            $imagePath = safe_product_image_path($row['image_path'] ?? null);
            if ($imagePath !== null) {
                $imagesByProduct[(string) $row['product_id']][] = $imagePath;
            }
        }

        foreach ($products as &$product) {
            $images = $imagesByProduct[$product['id']] ?? [];
            if ($images !== []) {
                $product['images'] = $images;
                $product['imagePath'] = $images[0];
            }
        }
        unset($product);
    }
}
