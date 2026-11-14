<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Models\Order;

final class OrderController extends Controller
{
    public function index(): void
    {
        if (!$this->requireCustomer()) {
            return;
        }

        $orders = (new Order(Database::connection()))
            ->findAllForCustomer((string) $_SESSION['user']['id']);

        $this->render('orders/index', [
            'title' => 'Đơn hàng của tôi | TPet',
            'orders' => $orders,
        ]);
    }

    public function show(string $orderId): void
    {
        if (!$this->requireCustomer()) {
            return;
        }

        $order = (new Order(Database::connection()))
            ->findForCustomer((string) $_SESSION['user']['id'], $orderId);

        if ($order === null) {
            (new NotFoundController())->show();
            return;
        }

        $this->render('orders/show', [
            'title' => 'Chi tiết đơn hàng ' . $order['order_number'] . ' | TPet',
            'order' => $order,
        ]);
    }

    private function requireCustomer(): bool
    {
        if (isset($_SESSION['user']) && is_array($_SESSION['user'])
            && ($_SESSION['user']['role'] ?? '') === 'customer') {
            return true;
        }

        $_SESSION['_flash'] = [
            'type' => 'error',
            'message' => 'Vui lòng đăng nhập để xem đơn hàng.',
        ];
        $this->redirect('/login');
    }
}
