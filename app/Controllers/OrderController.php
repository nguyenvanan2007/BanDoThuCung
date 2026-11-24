<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Models\AdminOrderManager;
use App\Models\Order;
use App\Services\OrderNotificationMailer;
use DomainException;
use PHPMailer\PHPMailer\Exception as MailException;
use PDOException;
use RuntimeException;

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
            'canCancel' => $order['status'] === 'pending' && $order['payment_status'] !== 'paid',
        ]);
    }

    public function cancel(string $orderId): void
    {
        if (!$this->requireCustomer()) {
            return;
        }
        if (!valid_csrf_token($_POST['_csrf_token'] ?? null)) {
            http_response_code(403);
            $this->render('cart/error', [
                'title' => 'Yêu cầu không hợp lệ | TPet',
                'message' => 'Phiên gửi biểu mẫu không hợp lệ. Hãy tải lại trang và thử lại.',
            ]);
            return;
        }
        if (($_POST['confirm_cancel'] ?? null) !== '1') {
            $this->flashError('Vui lòng xác nhận trước khi hủy đơn hàng.');
            $this->redirect('/orders/' . rawurlencode($orderId));
        }

        $userId = (string) $_SESSION['user']['id'];
        $orderModel = new Order(Database::connection());
        $order = $orderModel->findForCustomer($userId, $orderId);
        if ($order === null) {
            (new NotFoundController())->show();
            return;
        }

        $manager = new AdminOrderManager(Database::connection());
        try {
            $changed = $manager->cancelForCustomer($orderId, $userId);
        } catch (DomainException $exception) {
            $this->flashError($exception->getMessage());
            $this->redirect('/orders/' . rawurlencode($orderId));
        }

        if (!$changed) {
            $this->flashSuccess('Đơn hàng đã được hủy trước đó.');
            $this->redirect('/orders/' . rawurlencode($orderId));
        }

        try {
            $updatedOrder = $orderModel->findForCustomer($userId, $orderId);
            if ($updatedOrder === null) {
                throw new RuntimeException('Không thể tải đơn hàng vừa hủy để gửi email.');
            }
            (new OrderNotificationMailer())->sendStatusUpdate($updatedOrder);
            $this->flashSuccess('Đã hủy đơn hàng và gửi email thông báo.');
        } catch (MailException | RuntimeException | PDOException $exception) {
            error_log(sprintf(
                'Customer cancellation email failed for order %s (%s).',
                $orderId,
                get_class($exception)
            ));
            $this->flashError('Đơn hàng đã được hủy nhưng email thông báo chưa gửi được. Vui lòng kiểm tra cấu hình email.');
        }

        $this->redirect('/orders/' . rawurlencode($orderId));
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

    private function flashSuccess(string $message): void
    {
        $_SESSION['_flash'] = [
            'type' => 'success',
            'message' => $message,
        ];
    }

    private function flashError(string $message): void
    {
        $_SESSION['_flash'] = [
            'type' => 'error',
            'message' => $message,
        ];
    }
}
