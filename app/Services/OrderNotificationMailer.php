<?php

declare(strict_types=1);

namespace App\Services;

use PHPMailer\PHPMailer\Exception as MailException;
use PHPMailer\PHPMailer\PHPMailer;
use RuntimeException;

final class OrderNotificationMailer
{
    public function sendOrderConfirmation(array $order): void
    {
        $this->send(
            $order,
            'TPet đã nhận đơn hàng ' . (string) $order['order_number'],
            'Cảm ơn bạn đã đặt hàng. TPet đã nhận được đơn hàng và sẽ sớm xác nhận.'
        );
    }

    public function sendStatusUpdate(array $order): void
    {
        $this->send(
            $order,
            'Cập nhật đơn hàng ' . (string) $order['order_number'],
            'Đơn hàng của bạn vừa được cập nhật trạng thái.'
        );
    }

    private function send(array $order, string $subject, string $message): void
    {
        $recipient = $order['customer_email'] ?? null;
        if (!is_string($recipient) || filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('Địa chỉ email nhận thông báo đơn hàng không hợp lệ.');
        }

        $host = $this->requiredEnvironmentValue('MAIL_HOST');
        $fromAddress = $this->requiredEnvironmentValue('MAIL_FROM_ADDRESS');
        if (filter_var($fromAddress, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('MAIL_FROM_ADDRESS phải là địa chỉ email hợp lệ.');
        }

        $portValue = $this->requiredEnvironmentValue('MAIL_PORT');
        $port = filter_var($portValue, FILTER_VALIDATE_INT);
        if ($port === false || $port < 1 || $port > 65535) {
            throw new RuntimeException('MAIL_PORT phải là số nguyên từ 1 đến 65535.');
        }

        $encryption = strtolower($this->requiredEnvironmentValue('MAIL_ENCRYPTION'));
        if (!in_array($encryption, ['tls', 'ssl', 'none'], true)) {
            throw new RuntimeException('MAIL_ENCRYPTION phải là tls, ssl hoặc none.');
        }

        $username = $this->environmentValue('MAIL_USERNAME');
        $password = $this->environmentValue('MAIL_PASSWORD');
        if (($username === '') !== ($password === '')) {
            throw new RuntimeException('MAIL_USERNAME và MAIL_PASSWORD cần được cấu hình cùng nhau.');
        }

        $mailer = new PHPMailer(true);
        $mailer->isSMTP();
        $mailer->Host = $host;
        $mailer->Port = $port;
        $mailer->SMTPAuth = $username !== '';
        $mailer->Username = $username;
        $mailer->Password = $password;
        $mailer->SMTPSecure = match ($encryption) {
            'tls' => PHPMailer::ENCRYPTION_STARTTLS,
            'ssl' => PHPMailer::ENCRYPTION_SMTPS,
            default => '',
        };
        $mailer->SMTPAutoTLS = $encryption === 'tls';
        $mailer->Timeout = 10;
        $mailer->CharSet = PHPMailer::CHARSET_UTF8;
        $mailer->setFrom(
            $fromAddress,
            $this->environmentValue('MAIL_FROM_NAME') ?: 'TPet'
        );
        $mailer->addAddress($recipient, (string) ($order['customer_name'] ?? $order['recipient_name'] ?? ''));
        $mailer->isHTML(true);
        $mailer->Subject = $subject;
        [$mailer->Body, $mailer->AltBody] = $this->messageBodies($order, $message);

        if (!$mailer->send()) {
            throw new MailException('SMTP did not accept the order notification.');
        }
    }

    private function messageBodies(array $order, string $message): array
    {
        $escape = static fn (mixed $value): string => htmlspecialchars(
            (string) $value,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
        $items = $order['items'] ?? [];
        if (!is_array($items)) {
            throw new RuntimeException('Chi tiết sản phẩm trong email không hợp lệ.');
        }

        $rows = '';
        $textItems = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                throw new RuntimeException('Chi tiết sản phẩm trong email không hợp lệ.');
            }
            $name = (string) ($item['product_name'] ?? '');
            $quantity = (int) ($item['quantity'] ?? 0);
            $subtotal = (int) ($item['subtotal_vnd'] ?? 0);
            $rows .= '<tr><td>' . $escape($name) . '</td><td>' . $quantity
                . '</td><td>' . $escape(format_vnd($subtotal)) . '</td></tr>';
            $textItems[] = $name . ' x ' . $quantity . ' - ' . format_vnd($subtotal);
        }

        $orderNumber = $escape($order['order_number'] ?? '');
        $status = $escape(order_status_label((string) ($order['status'] ?? '')));
        $recipientName = $escape($order['recipient_name'] ?? '');
        $subtotal = format_vnd((int) ($order['subtotal_vnd'] ?? 0));
        $shippingFee = format_vnd((int) ($order['shipping_fee_vnd'] ?? 0));
        $total = format_vnd((int) ($order['total_vnd'] ?? 0));
        $html = '<!doctype html><html lang="vi"><meta charset="utf-8"><body>'
            . '<h1>' . $escape($message) . '</h1><p>Xin chào ' . $recipientName . '.</p>'
            . '<p>Mã đơn: <strong>' . $orderNumber . '</strong><br>Trạng thái: ' . $status . '</p>'
            . '<table><thead><tr><th>Sản phẩm</th><th>Số lượng</th><th>Tạm tính</th></tr></thead>'
            . '<tbody>' . $rows . '</tbody></table>'
            . '<p>Tạm tính: ' . $escape($subtotal) . '<br>Phí giao hàng: ' . $escape($shippingFee)
            . '<br><strong>Tổng cộng (COD): ' . $escape($total) . '</strong></p>'
            . '</body></html>';
        $text = $message . "\nXin chào " . (string) ($order['recipient_name'] ?? '')
            . "\nMã đơn: " . (string) ($order['order_number'] ?? '')
            . "\nTrạng thái: " . order_status_label((string) ($order['status'] ?? ''))
            . "\n" . implode("\n", $textItems)
            . "\nTạm tính: " . $subtotal
            . "\nPhí giao hàng: " . $shippingFee
            . "\nTổng cộng (COD): " . $total;

        return [$html, $text];
    }

    private function requiredEnvironmentValue(string $name): string
    {
        $value = $this->environmentValue($name);
        if ($value === '') {
            throw new RuntimeException($name . ' chưa được cấu hình.');
        }

        return $value;
    }

    private function environmentValue(string $name): string
    {
        $value = $_ENV[$name] ?? $_SERVER[$name] ?? getenv($name);

        return is_string($value) ? trim($value) : '';
    }
}
