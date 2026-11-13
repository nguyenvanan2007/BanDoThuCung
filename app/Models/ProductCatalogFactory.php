<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use InvalidArgumentException;

final class ProductCatalogFactory
{
    public static function create(): ProductCatalogInterface
    {
        $source = $_ENV['CATALOG_SOURCE'] ?? 'demo';

        return match ($source) {
            'demo' => new DemoProductCatalog(),
            'database' => new ProductCatalog(Database::connection()),
            default => throw new InvalidArgumentException('CATALOG_SOURCE must be demo or database.'),
        };
    }
}
