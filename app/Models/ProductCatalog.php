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
        $conditions = ['is_active = 1'];
        $parameters = [];
        $query = is_string($filters['q'] ?? null) ? trim($filters['q']) : '';
        $species = in_array($filters['species'] ?? '', ['dog', 'cat'], true)
            ? $filters['species']
            : '';
        $foodType = in_array($filters['category'] ?? '', ['dry', 'wet'], true)
            ? $filters['category']
            : '';

        if ($query !== '') {
            $conditions[] = '(name LIKE :name_query OR sku LIKE :sku_query)';
            $parameters['name_query'] = '%' . $query . '%';
            $parameters['sku_query'] = '%' . $query . '%';
        }

        if ($species !== '') {
            $conditions[] = 'species = :species';
            $parameters['species'] = $species;
        }

        if ($foodType !== '') {
            $conditions[] = 'food_type = :food_type';
            $parameters['food_type'] = $foodType;
        }

        $priceRange = is_string($filters['priceRange'] ?? null) ? $filters['priceRange'] : '';
        if ($priceRange === 'under_100k') {
            $conditions[] = 'price_vnd < :max_price';
            $parameters['max_price'] = 100000;
        } elseif ($priceRange === '100k_200k') {
            $conditions[] = 'price_vnd BETWEEN :min_price AND :max_price';
            $parameters['min_price'] = 100000;
            $parameters['max_price'] = 200000;
        } elseif ($priceRange === 'over_200k') {
            $conditions[] = 'price_vnd > :min_price';
            $parameters['min_price'] = 200000;
        }

        $orderBy = match ($filters['sort'] ?? '') {
            'price_asc' => 'price_vnd ASC, id ASC',
            'price_desc' => 'price_vnd DESC, id ASC',
            default => 'name ASC, id ASC',
        };

        $sql = 'SELECT id, sku, name, species, food_type, description, price_vnd, stock_quantity, is_demo'
            . ' FROM products WHERE ' . implode(' AND ', $conditions)
            . ' ORDER BY ' . $orderBy;
        $statement = $this->connection->prepare($sql);
        $statement->execute($parameters);

        return array_map(
            fn (array $row): array => $this->mapProduct($row),
            $statement->fetchAll()
        );
    }

    public function findById(string $id): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT id, sku, name, species, food_type, description, price_vnd, stock_quantity, is_demo'
            . ' FROM products WHERE id = :id AND is_active = 1 LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return is_array($row) ? $this->mapProduct($row) : null;
    }

    public function isDemoMode(): bool
    {
        return false;
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
            'category' => $foodType,
            'categoryLabel' => $foodType === 'dry' ? 'Thức ăn khô' : 'Thức ăn ướt',
            'description' => (string) ($row['description'] ?? ''),
            'price' => (int) $row['price_vnd'],
            'stockQuantity' => (int) $row['stock_quantity'],
            'icon' => $species === 'dog' ? '🐶' : '🐱',
            'isDemo' => (bool) $row['is_demo'],
        ];
    }
}
