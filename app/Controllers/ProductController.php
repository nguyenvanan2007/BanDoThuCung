<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\ProductCatalogInterface;

final class ProductController extends Controller
{
    public function __construct(private readonly ProductCatalogInterface $catalog)
    {
    }

    public function index(): void
    {
        $filters = [
            'q' => $this->queryText('q'),
            'species' => $this->queryChoice('species', ['dog', 'cat']),
            'category' => $this->queryChoice('category', ['dry', 'wet']),
            'priceRange' => $this->queryChoice('price', ['under_100k', '100k_200k', 'over_200k']),
            'sort' => $this->queryChoice('sort', ['price_asc', 'price_desc']),
        ];
        $products = $this->catalog->search($filters);

        $this->render('products/index', [
            'title' => 'Thức ăn cho chó và mèo | TPet',
            'filters' => $filters,
            'products' => $products,
            'productCount' => count($products),
            'isDemoMode' => $this->catalog->isDemoMode(),
        ]);
    }

    public function show(string $id): void
    {
        $product = $this->catalog->findById($id);

        if ($product === null) {
            (new NotFoundController())->show();
            return;
        }

        $this->render('products/show', [
            'title' => $product['name'] . ' | TPet',
            'product' => $product,
            'isDemoMode' => $this->catalog->isDemoMode(),
        ]);
    }

    private function queryText(string $key): string
    {
        $value = $_GET[$key] ?? '';

        return is_string($value) ? mb_substr(trim($value), 0, 100) : '';
    }

    private function queryChoice(string $key, array $allowedValues): string
    {
        $value = $_GET[$key] ?? '';

        return is_string($value) && in_array($value, $allowedValues, true) ? $value : '';
    }
}
