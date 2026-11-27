<?php

declare(strict_types=1);

namespace App\Services\Payment;

use RuntimeException;

final class SePayBankTransfer
{
    public function assertConfigured(): void
    {
        $this->bankAccount();
        $this->bankName();
        $this->webhookSecret();
        $this->transferDescriptionPrefix();
    }

    public static function newPaymentReference(): string
    {
        return 'TP' . strtoupper(bin2hex(random_bytes(8)));
    }

    public function createQrImageUrl(string $reference, int $amountVnd): string
    {
        if ($amountVnd < 1
            || preg_match('/\ATP[A-F0-9]{16}\z/', $reference) !== 1) {
            throw new RuntimeException('Thông tin thanh toán SePay không hợp lệ.');
        }

        $this->assertConfigured();
        $parameters = [
            'acc' => $this->bankAccount(),
            'bank' => $this->bankName(),
            'amount' => (string) $amountVnd,
            'des' => $this->transferDescription($reference),
            'template' => 'compact',
            'showinfo' => 'true',
            'store' => 'TPet',
        ];
        $accountName = $this->environmentValue('SEPAY_ACCOUNT_NAME');
        if ($accountName !== '') {
            $parameters['holder'] = $accountName;
        }

        return 'https://vietqr.app/img?' . http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
    }

    public function transferDescription(string $reference): string
    {
        if (preg_match('/\ATP[A-F0-9]{16}\z/', $reference) !== 1) {
            throw new RuntimeException('Mã thanh toán SePay không hợp lệ.');
        }

        $prefix = $this->transferDescriptionPrefix();

        return $prefix === '' ? $reference : $prefix . ' ' . $reference;
    }

    public function accountDetails(): array
    {
        return [
            'bank' => $this->bankName(),
            'accountNumber' => $this->bankAccount(),
            'accountName' => $this->environmentValue('SEPAY_ACCOUNT_NAME'),
        ];
    }

    public function hasValidWebhookSignature(string $body, string $signature, string $timestamp): bool
    {
        if (preg_match('/\A[0-9]{10}\z/', $timestamp) !== 1
            || abs(time() - (int) $timestamp) > 300) {
            return false;
        }

        $expected = 'sha256=' . hash_hmac(
            'sha256',
            $timestamp . '.' . $body,
            $this->webhookSecret()
        );

        return hash_equals($expected, $signature);
    }

    public function isExpectedAccount(array $payload): bool
    {
        $expectedAccount = $this->bankAccount();
        foreach (['accountNumber', 'subAccount'] as $field) {
            $account = $payload[$field] ?? null;
            if (is_string($account) && hash_equals($expectedAccount, trim($account))) {
                return true;
            }
        }

        return false;
    }

    private function bankAccount(): string
    {
        $account = $this->requiredEnvironmentValue('SEPAY_BANK_ACCOUNT');
        if (preg_match('/\A[A-Za-z0-9_-]{3,50}\z/', $account) !== 1) {
            throw new RuntimeException('SEPAY_BANK_ACCOUNT không hợp lệ.');
        }

        return $account;
    }

    private function bankName(): string
    {
        $bank = $this->requiredEnvironmentValue('SEPAY_BANK');
        if (preg_match('/\A[A-Za-z0-9 ._-]{2,100}\z/', $bank) !== 1) {
            throw new RuntimeException('SEPAY_BANK không hợp lệ.');
        }

        return $bank;
    }

    private function transferDescriptionPrefix(): string
    {
        $prefix = $this->environmentValue('SEPAY_TRANSFER_DESCRIPTION_PREFIX');
        if (preg_match('/\A[A-Za-z0-9 _-]{0,50}\z/', $prefix) !== 1) {
            throw new RuntimeException('SEPAY_TRANSFER_DESCRIPTION_PREFIX không hợp lệ.');
        }

        return trim($prefix);
    }

    private function webhookSecret(): string
    {
        return $this->requiredEnvironmentValue('SEPAY_WEBHOOK_SECRET');
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
