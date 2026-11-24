<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Models\AdminOrderManager;
use App\Models\PaymentManager;
use App\Services\OrderNotificationMailer;
use App\Services\VnpayGateway;
use PDOException;
use PHPMailer\PHPMailer\Exception as MailException;
use RuntimeException;

final class PaymentController extends Controller
{
    public function vnpayReturn(): void
    {
        $user = $_SESSION['user'] ?? null;
        if (!is_array($user) || ($user['role'] ?? '') !== 'customer'
            || !is_string($user['id'] ?? null)) {
            $_SESSION['_flash'] = [
                'type' => 'error',
                'message' => 'Đăng nhập để xem trạng thái thanh toán.',
            ];
            $this->redirect('/login');
        }

        $reference = $_GET['vnp_TxnRef'] ?? '';
        $order = is_string($reference)
            ? (new PaymentManager(Database::connection(), new VnpayGateway()))
                ->findForCustomer($reference, $user['id'])
            : null;
        if ($order === null) {
            http_response_code(404);
            (new NotFoundController())->show();
            return;
        }

        $this->render('payments/vnpay-result', [
            'title' => 'Kết quả thanh toán | TPet',
            'order' => $order,
        ]);
    }

    public function vnpayIpn(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $result = (new PaymentManager(Database::connection(), new VnpayGateway()))
            ->handleVnpayIpn($_GET);

        if (($result['changed'] ?? false) === true && is_string($result['orderId'] ?? null)) {
            try {
                $order = (new AdminOrderManager(Database::connection()))->findById($result['orderId']);
                if ($order === null) {
                    throw new RuntimeException('Không thể tải đơn hàng vừa cập nhật để gửi email thanh toán.');
                }
                (new OrderNotificationMailer())->sendPaymentUpdate($order);
            } catch (MailException | RuntimeException | PDOException $exception) {
                error_log(sprintf(
                    'Payment status email failed for order %s (%s).',
                    $result['orderId'],
                    get_class($exception)
                ));
            }
        }

        echo json_encode([
            'RspCode' => $result['code'],
            'Message' => $result['message'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
