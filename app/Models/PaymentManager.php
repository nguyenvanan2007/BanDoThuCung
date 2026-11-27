<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Payment\SePayBankTransfer;
use PDO;
use Throwable;

final class PaymentManager
{
    public function __construct(
        private readonly PDO $connection,
        private readonly SePayBankTransfer $gateway
    ) {
    }

    public function handleSePayWebhook(array $payload): array
    {
        $sepayTransactionId = $payload['id'] ?? null;
        $reference = $payload['code'] ?? null;
        $amount = $payload['transferAmount'] ?? null;
        if ((!is_int($sepayTransactionId) && !is_string($sepayTransactionId))
            || preg_match('/\A[1-9][0-9]{0,18}\z/', (string) $sepayTransactionId) !== 1
            || !is_string($reference)
            || preg_match('/\ATP[A-F0-9]{16}\z/', strtoupper($reference)) !== 1
            || ($payload['transferType'] ?? null) !== 'in'
            || !is_int($amount)
            || $amount < 1
            || !$this->gateway->isExpectedAccount($payload)) {
            return ['changed' => false];
        }

        $reference = strtoupper($reference);
        $this->connection->beginTransaction();
        try {
            $lockClause = $this->connection->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
                ? ' FOR UPDATE'
                : '';
            $statement = $this->connection->prepare(
                'SELECT t.id AS transaction_id, t.order_id, t.amount_vnd, t.status AS transaction_status,'
                . ' o.status AS order_status, o.payment_status'
                . ' FROM payment_transactions t INNER JOIN orders o ON o.id = t.order_id'
                . ' WHERE t.provider = :provider AND t.transaction_ref = :reference LIMIT 1' . $lockClause
            );
            $statement->execute([
                'provider' => 'SEPAY',
                'reference' => $reference,
            ]);
            $transaction = $statement->fetch();
            if (!is_array($transaction)) {
                $this->connection->commit();
                error_log('SePay payment notification did not match a pending order.');

                return ['changed' => false];
            }

            if ($amount !== (int) $transaction['amount_vnd']) {
                $this->connection->commit();
                error_log('SePay payment notification amount did not match the order.');

                return ['changed' => false];
            }

            if ($transaction['transaction_status'] !== 'pending') {
                $this->connection->commit();

                return ['changed' => false];
            }

            $isPendingOrder = $transaction['order_status'] === 'pending'
                && $transaction['payment_status'] === 'pending';
            $isCancelledOrder = $transaction['order_status'] === 'cancelled'
                && $transaction['payment_status'] === 'cancelled';
            if (!$isPendingOrder && !$isCancelledOrder) {
                $this->connection->commit();

                return ['changed' => false];
            }

            $updateTransaction = $this->connection->prepare(
                'UPDATE payment_transactions SET status = :status, response_code = :response,'
                . ' provider_transaction_no = :provider_no, paid_at = NOW() WHERE id = :id'
            );
            $updateTransaction->execute([
                'status' => 'paid',
                'response' => 'ORDER_PAID',
                'provider_no' => (string) $sepayTransactionId,
                'id' => (string) $transaction['transaction_id'],
            ]);

            $updateOrder = $this->connection->prepare(
                'UPDATE orders SET payment_status = :status WHERE id = :id'
            );
            $updateOrder->execute([
                'status' => 'paid',
                'id' => (string) $transaction['order_id'],
            ]);
            $useCoupon = $this->connection->prepare(
                'UPDATE coupon_redemptions SET status = :status'
                . ' WHERE order_id = :order_id AND status = :previous_status'
            );
            $useCoupon->execute([
                'status' => 'used',
                'order_id' => (string) $transaction['order_id'],
                'previous_status' => 'reserved',
            ]);

            $this->connection->commit();

            return [
                'changed' => true,
                'orderId' => (string) $transaction['order_id'],
                'paymentStatus' => 'paid',
            ];
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }
    }

    public function findForCustomer(string $reference, string $userId): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT o.id, o.order_number, o.status, o.payment_status, t.status AS transaction_status'
            . ' FROM payment_transactions t INNER JOIN orders o ON o.id = t.order_id'
            . ' WHERE t.transaction_ref = :reference AND t.provider = :provider'
            . ' AND o.user_id = :user_id LIMIT 1'
        );
        $statement->execute([
            'reference' => $reference,
            'provider' => 'SEPAY',
            'user_id' => $userId,
        ]);
        $order = $statement->fetch();

        return is_array($order) ? $order : null;
    }

    public function findSePayPaymentForCustomer(string $reference, string $userId): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT o.id, o.order_number, o.status AS order_status, o.payment_status,'
            . ' t.amount_vnd, t.status AS transaction_status'
            . ' FROM payment_transactions t INNER JOIN orders o ON o.id = t.order_id'
            . ' WHERE t.transaction_ref = :reference AND t.provider = :provider'
            . ' AND o.user_id = :user_id LIMIT 1'
        );
        $statement->execute([
            'reference' => $reference,
            'provider' => 'SEPAY',
            'user_id' => $userId,
        ]);
        $payment = $statement->fetch();

        return is_array($payment) ? $payment : null;
    }
}
