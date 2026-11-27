<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Models\Admin\AdminOrderManager;
use App\Models\PaymentManager;
use App\Services\Notification\OrderNotificationMailer;
use App\Services\Payment\SePayBankTransfer;
use PDOException;
use PHPMailer\PHPMailer\Exception as MailException;
use RuntimeException;

final class PaymentController extends Controller
{
    public function sepayPayment(string $reference): void
    {
        $user = $_SESSION['user'] ?? null;
        if (!is_array($user) || ($user['role'] ?? '') !== 'customer'
            || !is_string($user['id'] ?? null)) {
            $_SESSION['_flash'] = [
                'type' => 'error',
                'message' => 'Đăng nhập để xem thông tin thanh toán.',
            ];
            $this->redirect('/login');
        }

        $gateway = new SePayBankTransfer();
        $payment = (new PaymentManager(Database::connection(), $gateway))
            ->findSePayPaymentForCustomer($reference, $user['id']);
        if ($payment === null) {
            http_response_code(404);
            (new NotFoundController())->show();
            return;
        }

        $qrImageUrl = null;
        $transferDescription = null;
        $accountDetails = null;
        $configurationError = false;
        if ($payment['payment_status'] === 'pending'
            && $payment['transaction_status'] === 'pending'
            && $payment['order_status'] === 'pending') {
            try {
                $qrImageUrl = $gateway->createQrImageUrl($reference, (int) $payment['amount_vnd']);
                $transferDescription = $gateway->transferDescription($reference);
                $accountDetails = $gateway->accountDetails();
            } catch (RuntimeException $exception) {
                error_log('SePay payment page configuration error: ' . $exception->getMessage());
                $configurationError = true;
            }
        }

        $this->render('payments/sepay', [
            'title' => 'Thanh toán qua SePay | TPet',
            'payment' => $payment,
            'qrImageUrl' => $qrImageUrl,
            'transferDescription' => $transferDescription,
            'accountDetails' => $accountDetails,
            'configurationError' => $configurationError,
            'statusUrl' => '/payment/sepay/status/' . rawurlencode($reference),
            'orderUrl' => '/orders/' . rawurlencode((string) $payment['id']),
        ]);
    }

    public function sepayStatus(string $reference): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        $user = $_SESSION['user'] ?? null;
        if (!is_array($user) || ($user['role'] ?? '') !== 'customer'
            || !is_string($user['id'] ?? null)) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            return;
        }

        $payment = (new PaymentManager(Database::connection(), new SePayBankTransfer()))
            ->findForCustomer($reference, $user['id']);
        if ($payment === null) {
            http_response_code(404);
            echo json_encode(['error' => 'Not found']);
            return;
        }

        echo json_encode([
            'paymentStatus' => $payment['payment_status'],
            'orderStatus' => $payment['status'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function sepayWebhook(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $contentLength = $_SERVER['CONTENT_LENGTH'] ?? null;
        if (is_string($contentLength) && ctype_digit($contentLength) && (int) $contentLength > 65536) {
            http_response_code(413);
            echo json_encode(['success' => false, 'message' => 'Request body is too large']);
            return;
        }

        $body = file_get_contents('php://input');
        if (!is_string($body) || $body === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Empty request body']);
            return;
        }
        if (strlen($body) > 65536) {
            http_response_code(413);
            echo json_encode(['success' => false, 'message' => 'Request body is too large']);
            return;
        }

        $gateway = new SePayBankTransfer();
        try {
            $isValid = $gateway->hasValidWebhookSignature(
                $body,
                is_string($_SERVER['HTTP_X_SEPAY_SIGNATURE'] ?? null)
                    ? $_SERVER['HTTP_X_SEPAY_SIGNATURE']
                    : '',
                is_string($_SERVER['HTTP_X_SEPAY_TIMESTAMP'] ?? null)
                    ? $_SERVER['HTTP_X_SEPAY_TIMESTAMP']
                    : ''
            );
        } catch (RuntimeException $exception) {
            error_log('SePay webhook configuration error: ' . $exception->getMessage());
            http_response_code(503);
            echo json_encode(['success' => false, 'message' => 'Webhook is not configured']);
            return;
        }
        if (!$isValid) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Invalid signature']);
            return;
        }

        $payload = json_decode($body, true);
        if (!is_array($payload) || json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid JSON payload']);
            return;
        }

        try {
            $result = (new PaymentManager(Database::connection(), $gateway))
                ->handleSePayWebhook($payload);
        } catch (PDOException $exception) {
            $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());
            $driverCode = (string) ($exception->errorInfo[1] ?? 'unknown');
            error_log(sprintf(
                'SePay webhook database failure (SQLSTATE %s, driver code %s).',
                $sqlState,
                $driverCode
            ));
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Payment update failed']);
            return;
        } catch (RuntimeException $exception) {
            error_log('SePay webhook configuration error: ' . $exception->getMessage());
            http_response_code(503);
            echo json_encode(['success' => false, 'message' => 'Webhook is not configured']);
            return;
        }

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

        echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
