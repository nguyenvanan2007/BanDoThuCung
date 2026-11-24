<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

final class InventoryMovement
{
    public static function record(
        PDO $connection,
        string $productId,
        ?string $variantId,
        ?string $orderId,
        ?string $changedByUserId,
        string $type,
        int $quantityBefore,
        int $quantityAfter,
        string $reason
    ): void {
        $statement = $connection->prepare(
            'INSERT INTO inventory_movements'
            . ' (product_id, product_variant_id, order_id, changed_by_user_id, movement_type,'
            . ' quantity_delta, quantity_before, quantity_after, reason)'
            . ' VALUES (:product_id, :variant_id, :order_id, :user_id, :type, :delta, :before, :after, :reason)'
        );
        $statement->execute([
            'product_id' => $productId,
            'variant_id' => $variantId,
            'order_id' => $orderId,
            'user_id' => $changedByUserId,
            'type' => $type,
            'delta' => $quantityAfter - $quantityBefore,
            'before' => $quantityBefore,
            'after' => $quantityAfter,
            'reason' => $reason,
        ]);
    }
}
