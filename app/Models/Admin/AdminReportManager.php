<?php

declare(strict_types=1);

namespace App\Models\Admin;

use PDO;

final class AdminReportManager
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function orderCounts(): array
    {
        return $this->connection->query(
            'SELECT status, COUNT(*) AS order_count FROM orders GROUP BY status ORDER BY status'
        )->fetchAll();
    }

    public function dailyRevenue(): array
    {
        return $this->connection->query(
            'SELECT DATE(created_at) AS period, payment_status, COUNT(*) AS order_count,'
            . ' SUM(CASE WHEN payment_status = "paid" THEN CAST(total_vnd AS SIGNED)'
            . ' WHEN payment_status = "refunded" THEN -CAST(total_vnd AS SIGNED) ELSE 0 END) AS total_vnd FROM orders'
            . ' WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)'
            . ' GROUP BY DATE(created_at), payment_status ORDER BY period DESC, payment_status'
        )->fetchAll();
    }

    public function monthlyRevenue(): array
    {
        return $this->connection->query(
            'SELECT DATE_FORMAT(created_at, "%Y-%m") AS period, payment_status,'
            . ' COUNT(*) AS order_count, SUM(CASE WHEN payment_status = "paid" THEN CAST(total_vnd AS SIGNED)'
            . ' WHEN payment_status = "refunded" THEN -CAST(total_vnd AS SIGNED) ELSE 0 END) AS total_vnd FROM orders'
            . ' WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)'
            . ' GROUP BY DATE_FORMAT(created_at, "%Y-%m"), payment_status'
            . ' ORDER BY period DESC, payment_status'
        )->fetchAll();
    }

    public function bestSellers(): array
    {
        return $this->connection->query(
            'SELECT oi.product_sku, oi.product_name, SUM(oi.quantity) AS quantity_sold,'
            . ' SUM(oi.subtotal_vnd) AS revenue_vnd FROM order_items oi'
            . ' INNER JOIN orders o ON o.id = oi.order_id'
            . ' WHERE o.status <> "cancelled" AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)'
            . ' GROUP BY oi.product_sku, oi.product_name'
            . ' ORDER BY quantity_sold DESC, revenue_vnd DESC LIMIT 20'
        )->fetchAll();
    }

    public function slowSellers(): array
    {
        return $this->connection->query(
            'SELECT p.sku, p.name, COALESCE(s.quantity_sold, 0) AS quantity_sold'
            . ' FROM products p LEFT JOIN ('
            . ' SELECT oi.product_id, SUM(oi.quantity) AS quantity_sold'
            . ' FROM order_items oi INNER JOIN orders o ON o.id = oi.order_id'
            . ' WHERE o.status <> "cancelled" AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)'
            . ' GROUP BY oi.product_id'
            . ' ) s ON s.product_id = p.id'
            . ' WHERE p.is_demo = 0 AND p.is_active = 1'
            . ' ORDER BY quantity_sold ASC, p.name ASC LIMIT 20'
        )->fetchAll();
    }

    public function inventoryAlerts(): array
    {
        return $this->connection->query(
            'SELECT p.id, p.sku, p.name, p.stock_quantity, p.low_stock_threshold,'
            . ' (SELECT COUNT(*) FROM product_variants v WHERE v.product_id = p.id AND v.is_active = 1) AS variant_count'
            . ' FROM products p WHERE p.is_demo = 0'
            . ' AND p.stock_quantity <= p.low_stock_threshold'
            . ' ORDER BY p.stock_quantity ASC, p.name ASC'
        )->fetchAll();
    }

    public function couponUsage(): array
    {
        return $this->connection->query(
            'SELECT o.coupon_code, COUNT(*) AS usage_count, SUM(o.discount_vnd) AS discount_total_vnd'
            . ' FROM orders o INNER JOIN coupon_redemptions r ON r.order_id = o.id'
            . ' WHERE r.status = "used" AND o.coupon_code IS NOT NULL AND o.coupon_code <> ""'
            . ' GROUP BY o.coupon_code'
            . ' ORDER BY usage_count DESC, coupon_code ASC'
        )->fetchAll();
    }
}
