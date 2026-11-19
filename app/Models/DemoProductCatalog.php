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
            'description' => 'Thức ăn dinh dưỡng, thơm ngon và phù hợp cho thói quen ăn uống của chó.',
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
            'description' => 'Thức ăn ướt giàu dinh dưỡng, dễ ăn và giúp chó luôn vui khỏe.',
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
            'description' => 'Công thức cân bằng với hương vị hấp dẫn, thích hợp cho mèo trưởng thành.',
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
            'description' => 'Thức ăn ướt thơm ngon với hương vị tự nhiên, dễ hấp thụ cho mèo.',
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
            'description' => 'Hỗ trợ sức khỏe và năng lượng cho chó với thành phần dễ tiêu hóa.',
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
            'description' => 'Công thức dinh dưỡng cân bằng với hạt thơm, giúp mèo ăn ngon mỗi ngày.',
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

            if ($filters['category'] !== ''
                && $filters['category'] !== $product['species'] . '-' . $product['category']) {
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

        return array_map(
            static fn (array $product): array => $product + ['hasVariants' => false],
            array_values($products)
        );
    }

    public function findById(string $id): ?array
    {
        foreach (self::PRODUCTS as $product) {
            if ($product['id'] === $id) {
                return $product + [
                    'categoryId' => '',
                    'categorySlug' => $product['species'] . '-' . $product['category'],
                    'images' => [$product['imagePath']],
                    'variants' => [],
                    'hasVariants' => false,
                ];
            }
        }

        return null;
    }

    public function findVariant(string $productId, string $variantId): ?array
    {
        return null;
    }

    public function categories(): array
    {
        return [
            ['id' => '', 'name' => 'Thức ăn khô cho chó', 'slug' => 'dog-dry', 'species' => 'dog', 'foodType' => 'dry'],
            ['id' => '', 'name' => 'Thức ăn ướt cho chó', 'slug' => 'dog-wet', 'species' => 'dog', 'foodType' => 'wet'],
            ['id' => '', 'name' => 'Thức ăn khô cho mèo', 'slug' => 'cat-dry', 'species' => 'cat', 'foodType' => 'dry'],
            ['id' => '', 'name' => 'Thức ăn ướt cho mèo', 'slug' => 'cat-wet', 'species' => 'cat', 'foodType' => 'wet'],
        ];
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
