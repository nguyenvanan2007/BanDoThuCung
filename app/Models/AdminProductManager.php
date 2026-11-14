<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

final class AdminProductManager
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function findAll(): array
    {
        $statement = $this->connection->query(
            'SELECT id, sku, name, species, food_type, description, image_path, price_vnd, stock_quantity, is_active'
            . ' FROM products WHERE is_demo = 0 ORDER BY id DESC'
        );

        return array_map(
            fn (array $row): array => $this->mapProduct($row),
            $statement->fetchAll()
        );
    }

    public function findById(string $id): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT id, sku, name, species, food_type, description, image_path, price_vnd, stock_quantity, is_active'
            . ' FROM products WHERE id = :id AND is_demo = 0 LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $product = $statement->fetch();

        return is_array($product) ? $this->mapProduct($product) : null;
    }

    public function countActive(): int
    {
        return (int) $this->connection->query(
            'SELECT COUNT(*) FROM products WHERE is_demo = 0 AND is_active = 1'
        )->fetchColumn();
    }

    public function create(array $product): string
    {
        $statement = $this->connection->prepare(
            'INSERT INTO products'
            . ' (sku, name, species, food_type, description, image_path, price_vnd, stock_quantity, is_active, is_demo)'
            . ' VALUES'
            . ' (:sku, :name, :species, :food_type, :description, :image_path, :price_vnd, :stock_quantity, :is_active, 0)'
        );
        $statement->execute($product);

        return (string) $this->connection->lastInsertId();
    }

    public function update(string $id, array $product): void
    {
        $statement = $this->connection->prepare(
            'UPDATE products SET sku = :sku, name = :name, species = :species, food_type = :food_type,'
            . ' description = :description, image_path = :image_path, price_vnd = :price_vnd,'
            . ' stock_quantity = :stock_quantity, is_active = :is_active'
            . ' WHERE id = :id AND is_demo = 0'
        );
        $statement->execute($product + ['id' => $id]);
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
            'foodTypeLabel' => $foodType === 'dry' ? 'Thức ăn khô' : 'Thức ăn ướt',
            'description' => (string) ($row['description'] ?? ''),
            'imagePath' => safe_product_image_path($row['image_path'] ?? null),
            'priceVnd' => (int) $row['price_vnd'],
            'stockQuantity' => (int) $row['stock_quantity'],
            'isActive' => (bool) $row['is_active'],
        ];
    }
}
