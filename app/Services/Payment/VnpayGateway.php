<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class VnpayGateway
{
    public function assertConfigured(): void
    {
        $paymentUrl = $this->requiredEnvironmentValue('VNPAY_PAYMENT_URL');
        $parsedUrl = parse_url($paymentUrl);
        if (!is_array($parsedUrl) || ($parsedUrl['scheme'] ?? '') !== 'https'
            || !isset($parsedUrl['host'])) {
            throw new RuntimeException('VNPAY_PAYMENT_URL phải là URL HTTPS hợp lệ.');
        }
        $this->requiredEnvironmentValue('VNPAY_TMN_CODE');
        $this->requiredEnvironmentValue('VNPAY_HASH_SECRET');
        $this->requiredEnvironmentValue('APP_URL');
    }

    public static function newTransactionReference(): string
    {
        return 'TP' . date('YmdHis') . strtoupper(bin2hex(random_bytes(6)));
    }

    public function createPaymentUrl(
        string $transactionReference,
        string $orderNumber,
        int $amountVnd,
        string $clientIp
    ): string {
        if ($amountVnd < 1 || $amountVnd > intdiv(PHP_INT_MAX, 100)
            || preg_match('/\A[A-Za-z0-9_-]{1,100}\z/', $transactionReference) !== 1) {
            throw new RuntimeException('Thông tin giao dịch VNPAY không hợp lệ.');
        }

        $this->assertConfigured();
        $paymentUrl = $this->requiredEnvironmentValue('VNPAY_PAYMENT_URL');
        $returnUrl = rtrim($this->requiredEnvironmentValue('APP_URL'), '/')
            . '/payment/vnpay/return';
        $secureSecret = $this->requiredEnvironmentValue('VNPAY_HASH_SECRET');

        $now = new \DateTimeImmutable('now', new \DateTimeZone('Asia/Ho_Chi_Minh'));
        $parameters = [
            'vnp_Version' => '2.1.0',
            'vnp_Command' => 'pay',
            'vnp_TmnCode' => $this->requiredEnvironmentValue('VNPAY_TMN_CODE'),
            'vnp_Amount' => (string) ($amountVnd * 100),
            'vnp_CurrCode' => 'VND',
            'vnp_TxnRef' => $transactionReference,
            'vnp_OrderInfo' => 'Thanh toan don ' . $orderNumber,
            'vnp_OrderType' => 'other',
            'vnp_Locale' => 'vn',
            'vnp_ReturnUrl' => $returnUrl,
            'vnp_IpAddr' => filter_var($clientIp, FILTER_VALIDATE_IP) !== false ? $clientIp : '127.0.0.1',
            'vnp_CreateDate' => $now->format('YmdHis'),
            'vnp_ExpireDate' => $now->modify('+15 minutes')->format('YmdHis'),
        ];
        ksort($parameters);
        $hashData = $this->hashData($parameters);
        $parameters['vnp_SecureHash'] = hash_hmac('sha512', $hashData, $secureSecret);

        return $paymentUrl . '?' . http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
    }

    public function hasValidSignature(array $parameters): bool
    {
        $providedHash = $parameters['vnp_SecureHash'] ?? null;
        $merchantCode = $parameters['vnp_TmnCode'] ?? null;
        if (!is_string($providedHash)
            || preg_match('/\A[a-f0-9]{128}\z/i', $providedHash) !== 1
            || !is_string($merchantCode)
            || !hash_equals($this->requiredEnvironmentValue('VNPAY_TMN_CODE'), $merchantCode)) {
            return false;
        }

        $signedParameters = [];
        foreach ($parameters as $key => $value) {
            if (!is_string($key) || !str_starts_with($key, 'vnp_')
                || in_array($key, ['vnp_SecureHash', 'vnp_SecureHashType'], true)
                || !is_string($value)) {
                continue;
            }
            $signedParameters[$key] = $value;
        }
        ksort($signedParameters);

        return hash_equals(
            hash_hmac('sha512', $this->hashData($signedParameters), $this->requiredEnvironmentValue('VNPAY_HASH_SECRET')),
            $providedHash
        );
    }

    private function hashData(array $parameters): string
    {
        $pairs = [];
        foreach ($parameters as $key => $value) {
            $pairs[] = urlencode((string) $key) . '=' . urlencode((string) $value);
        }

        return implode('&', $pairs);
    }

    private function requiredEnvironmentValue(string $name): string
    {
        $value = $_ENV[$name] ?? $_SERVER[$name] ?? getenv($name);
        if (!is_string($value) || trim($value) === '') {
            throw new RuntimeException($name . ' chưa được cấu hình.');
        }

        return trim($value);
    }
}
