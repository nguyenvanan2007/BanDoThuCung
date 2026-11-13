<?php

declare(strict_types=1);

namespace App\Models;

interface ProductCatalogInterface
{
    public function search(array $filters): array;

    public function findById(string $id): ?array;

    public function isDemoMode(): bool;
}
