<?php

declare(strict_types=1);

namespace App\Models;

final class DemoProductCatalog implements ProductCatalogInterface
{
    private const PRODUCTS = [
        [
            'id' => '1',
            'sku' => 'DEMO-DOG-DRY-A',
            'name' => 'Thức ăn khô cho chó — mẫu minh họa A',
            'species' => 'dog',
            'speciesLabel' => 'Cho chó',
            'category' => 'dry',
            'categoryLabel' => 'Thức ăn khô',
            'price' => 219000,
            'stockQuantity' => 10,
            'icon' => '🐶',
            'description' => 'Dữ liệu minh họa, không phải thông tin hàng hóa thật.',
            'imagePath' => '/assets/images/products/pedigree-dry-dog.jpg',
            'isDemo' => true,
        ],
        [
            'id' => '2',
            'sku' => 'DEMO-DOG-WET-B',
            'name' => 'Thức ăn ướt cho chó — mẫu minh họa B',
            'species' => 'dog',
            'speciesLabel' => 'Cho chó',
            'category' => 'wet',
            'categoryLabel' => 'Thức ăn ướt',
            'price' => 45000,
            'stockQuantity' => 10,
            'icon' => '🐶',
            'description' => 'Dữ liệu minh họa, không phải thông tin hàng hóa thật.',
            'imagePath' => '/assets/images/products/pedigree-wet-dog.jpg',
            'isDemo' => true,
        ],
        [
            'id' => '3',
            'sku' => 'DEMO-CAT-DRY-A',
            'name' => 'Thức ăn khô cho mèo — mẫu minh họa A',
            'species' => 'cat',
            'speciesLabel' => 'Cho mèo',
            'category' => 'dry',
            'categoryLabel' => 'Thức ăn khô',
            'price' => 179000,
            'stockQuantity' => 10,
            'icon' => '🐱',
            'description' => 'Dữ liệu minh họa, không phải thông tin hàng hóa thật.',
            'imagePath' => '/assets/images/products/cat-food-bowl.jpg',
            'isDemo' => true,
        ],
        [
            'id' => '4',
            'sku' => 'DEMO-CAT-WET-B',
            'name' => 'Thức ăn ướt cho mèo — mẫu minh họa B',
            'species' => 'cat',
            'speciesLabel' => 'Cho mèo',
            'category' => 'wet',
            'categoryLabel' => 'Thức ăn ướt',
            'price' => 32000,
            'stockQuantity' => 10,
            'icon' => '🐱',
            'description' => 'Dữ liệu minh họa, không phải thông tin hàng hóa thật.',
            'imagePath' => '/assets/images/products/cat-wet-food.jpg',
            'isDemo' => true,
        ],
        [
            'id' => '5',
            'sku' => 'DEMO-DOG-DRY-C',
            'name' => 'Thức ăn khô cho chó — mẫu minh họa C',
            'species' => 'dog',
            'speciesLabel' => 'Cho chó',
            'category' => 'dry',
            'categoryLabel' => 'Thức ăn khô',
            'price' => 56000,
            'stockQuantity' => 10,
            'icon' => '🐶',
            'description' => 'Dữ liệu minh họa, không phải thông tin hàng hóa thật.',
            'imagePath' => '/assets/images/products/dog-eating-food.jpg',
            'isDemo' => true,
        ],
        [
            'id' => '6',
            'sku' => 'DEMO-CAT-DRY-C',
            'name' => 'Thức ăn khô cho mèo — mẫu minh họa C',
            'species' => 'cat',
            'speciesLabel' => 'Cho mèo',
            'category' => 'dry',
            'categoryLabel' => 'Thức ăn khô',
            'price' => 235000,
            'stockQuantity' => 10,
            'icon' => '🐱',
            'description' => 'Dữ liệu minh họa, không phải thông tin hàng hóa thật.',
            'imagePath' => '/assets/images/products/cat-food-kibble.jpg',
            'isDemo' => true,
        ],
    ];

    public function search(array $filters): array
    {
        $products = array_filter(self::PRODUCTS, static function (array $product) use ($filters): bool {
            if ($filters['q'] !== ''
                && mb_stripos($product['name'] . ' ' . $product['sku'], $filters['q']) === false) {
                return false;
            }

            if ($filters['species'] !== '' && $product['species'] !== $filters['species']) {
                return false;
            }

            if ($filters['category'] !== '' && $product['category'] !== $filters['category']) {
                return false;
            }

            return self::matchesPriceRange($product['price'], $filters['priceRange']);
        });

        usort($products, static function (array $left, array $right) use ($filters): int {
            return match ($filters['sort']) {
                'price_asc' => $left['price'] <=> $right['price'],
                'price_desc' => $right['price'] <=> $left['price'],
                default => strcmp(mb_strtolower($left['name']), mb_strtolower($right['name'])),
            };
        });

        return array_values($products);
    }

    public function findById(string $id): ?array
    {
        foreach (self::PRODUCTS as $product) {
            if ($product['id'] === $id) {
                return $product;
            }
        }

        return null;
    }

    public function isDemoMode(): bool
    {
        return true;
    }

    private static function matchesPriceRange(int $price, string $range): bool
    {
        return match ($range) {
            'under_100k' => $price < 100000,
            '100k_200k' => $price >= 100000 && $price <= 200000,
            'over_200k' => $price > 200000,
            default => true,
        };
    }
}
