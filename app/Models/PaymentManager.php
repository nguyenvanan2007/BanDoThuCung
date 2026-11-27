<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Payment\VnpayGateway;
use DomainException;
use PDO;
use Throwable;

final class PaymentManager
{
    public function __construct(
        private readonly PDO $connection,
        private readonly VnpayGateway $gateway
    ) {
    }

    public function handleVnpayIpn(array $parameters): array
    {
        if (!$this->gateway->hasValidSignature($parameters)) {
            return ['code' => '97', 'message' => 'Invalid signature', 'changed' => false];
        }

        $reference = $parameters['vnp_TxnRef'] ?? null;
        $amount = $parameters['vnp_Amount'] ?? null;
        $responseCode = $parameters['vnp_ResponseCode'] ?? null;
        $transactionStatus = $parameters['vnp_TransactionStatus'] ?? null;
        if (!is_string($reference) || !is_string($amount)
            || preg_match('/\A[0-9]+\z/', $amount) !== 1
            || !is_string($responseCode) || !is_string($transactionStatus)) {
            return ['code' => '99', 'message' => 'Invalid request data', 'changed' => false];
        }

        $this->connection->beginTransaction();
        try {
            $lockClause = $this->connection->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
                ? ' FOR UPDATE'
                : '';
            $lookup = $this->connection->prepare(
                'SELECT order_id FROM payment_transactions'
                . ' WHERE transaction_ref = :reference AND provider = :provider LIMIT 1'
            );
            $lookup->execute(['reference' => $reference, 'provider' => 'VNPAY']);
            $orderId = $lookup->fetchColumn();
            if ($orderId === false) {
                $this->connection->rollBack();
                return ['code' => '01', 'message' => 'Order not found', 'changed' => false];
            }

            $orderStatement = $this->connection->prepare(
                'SELECT id, status, payment_status FROM orders WHERE id = :id' . $lockClause
            );
            $orderStatement->execute(['id' => (string) $orderId]);
            $order = $orderStatement->fetch();
            if (!is_array($order)) {
                $this->connection->rollBack();
                return ['code' => '01', 'message' => 'Order not found', 'changed' => false];
            }
            $transactionStatement = $this->connection->prepare(
                'SELECT id, order_id, amount_vnd, status FROM payment_transactions'
                . ' WHERE transaction_ref = :reference AND provider = :provider LIMIT 1' . $lockClause
            );
            $transactionStatement->execute(['reference' => $reference, 'provider' => 'VNPAY']);
            $transaction = $transactionStatement->fetch();
            if (!is_array($transaction) || (string) $transaction['order_id'] !== (string) $orderId) {
                $this->connection->rollBack();
                return ['code' => '01', 'message' => 'Order not found', 'changed' => false];
            }

            $expectedAmount = (int) $transaction['amount_vnd'];
            if ($expectedAmount > intdiv(PHP_INT_MAX, 100)
                || (int) $amount !== $expectedAmount * 100) {
                $this->connection->rollBack();
                return ['code' => '04', 'message' => 'Invalid amount', 'changed' => false];
            }

            $isPaid = $responseCode === '00' && $transactionStatus === '00';
            if ($transaction['status'] !== 'pending') {
                $this->connection->commit();
                return [
                    'code' => '00',
                    'message' => 'Confirm Success',
                    'changed' => false,
                    'orderId' => (string) $transaction['order_id'],
                    'paymentStatus' => (string) $order['payment_status'],
                ];
            }
            if ($order['status'] === 'cancelled' && $order['payment_status'] === 'cancelled') {
                if ($isPaid) {
                    $updateTransaction = $this->connection->prepare(
                        'UPDATE payment_transactions SET status = :status, response_code = :response,'
                        . ' provider_transaction_no = :provider_no, paid_at = NOW() WHERE id = :id'
                    );
                    $updateTransaction->execute([
                        'status' => 'paid',
                        'response' => $responseCode,
                        'provider_no' => $this->stringParameter($parameters, 'vnp_TransactionNo'),
                        'id' => (string) $transaction['id'],
                    ]);
                    $updateOrder = $this->connection->prepare(
                        'UPDATE orders SET payment_status = :status WHERE id = :id'
                    );
                    $updateOrder->execute(['status' => 'paid', 'id' => (string) $order['id']]);
                    $this->connection->commit();

                    return [
                        'code' => '00',
                        'message' => 'Confirm Success',
                        'changed' => true,
                        'orderId' => (string) $order['id'],
                        'paymentStatus' => 'paid',
                    ];
                }

                $failCancelled = $this->connection->prepare(
                    'UPDATE payment_transactions SET status = :status, response_code = :response,'
                    . ' provider_transaction_no = :provider_no WHERE id = :id'
                );
                $failCancelled->execute([
                    'status' => 'failed',
                    'response' => $responseCode,
                    'provider_no' => $this->stringParameter($parameters, 'vnp_TransactionNo'),
                    'id' => (string) $transaction['id'],
                ]);
                $this->connection->commit();

                return [
                    'code' => '00',
                    'message' => 'Confirm Success',
                    'changed' => false,
                    'orderId' => (string) $order['id'],
                    'paymentStatus' => 'cancelled',
                ];
            }
            if ($order['payment_status'] !== 'pending' || $order['status'] !== 'pending') {
                $this->connection->rollBack();
                return ['code' => '02', 'message' => 'Order already confirmed', 'changed' => false];
            }

            if ($isPaid) {
                $updateTransaction = $this->connection->prepare(
                    'UPDATE payment_transactions SET status = :status, response_code = :response,'
                    . ' provider_transaction_no = :provider_no, paid_at = NOW() WHERE id = :id'
                );
                $updateTransaction->execute([
                    'status' => 'paid',
                    'response' => $responseCode,
                    'provider_no' => $this->stringParameter($parameters, 'vnp_TransactionNo'),
                    'id' => (string) $transaction['id'],
                ]);
                $updateOrder = $this->connection->prepare(
                    'UPDATE orders SET payment_status = :status WHERE id = :id'
                );
                $updateOrder->execute(['status' => 'paid', 'id' => (string) $order['id']]);
                $useCoupon = $this->connection->prepare(
                    'UPDATE coupon_redemptions SET status = :status'
                    . ' WHERE order_id = :order_id AND status = :previous_status'
                );
                $useCoupon->execute([
                    'status' => 'used',
                    'order_id' => (string) $order['id'],
                    'previous_status' => 'reserved',
                ]);
                $paymentStatus = 'paid';
            } else {
                $this->restoreOrderStock((string) $order['id']);
                CouponManager::releaseForOrder($this->connection, (string) $order['id']);
                $updateTransaction = $this->connection->prepare(
                    'UPDATE payment_transactions SET status = :status, response_code = :response,'
                    . ' provider_transaction_no = :provider_no WHERE id = :id'
                );
                $updateTransaction->execute([
                    'status' => 'failed',
                    'response' => $responseCode,
                    'provider_no' => $this->stringParameter($parameters, 'vnp_TransactionNo'),
                    'id' => (string) $transaction['id'],
                ]);
                $updateOrder = $this->connection->prepare(
                    'UPDATE orders SET payment_status = :payment_status, status = :status WHERE id = :id'
                );
                $updateOrder->execute([
                    'payment_status' => 'failed',
                    'status' => 'cancelled',
                    'id' => (string) $order['id'],
                ]);
                $history = $this->connection->prepare(
                    'INSERT INTO order_status_history (order_id, status, changed_by_user_id)'
                    . ' VALUES (:order_id, :status, NULL)'
                );
                $history->execute(['order_id' => (string) $order['id'], 'status' => 'cancelled']);
                $paymentStatus = 'failed';
            }

            $this->connection->commit();

            return [
                'code' => '00',
                'message' => 'Confirm Success',
                'changed' => true,
                'orderId' => (string) $order['id'],
                'paymentStatus' => $paymentStatus,
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
            . ' WHERE t.transaction_ref = :reference AND o.user_id = :user_id LIMIT 1'
        );
        $statement->execute(['reference' => $reference, 'user_id' => $userId]);
        $order = $statement->fetch();

        return is_array($order) ? $order : null;
    }

    public function findRequestForCustomer(string $reference, string $userId): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT o.id, o.order_number, t.amount_vnd'
            . ' FROM payment_transactions t INNER JOIN orders o ON o.id = t.order_id'
            . ' WHERE t.transaction_ref = :reference AND o.user_id = :user_id'
            . ' AND t.provider = :provider AND t.status = :status LIMIT 1'
        );
        $statement->execute([
            'reference' => $reference,
            'user_id' => $userId,
            'provider' => 'VNPAY',
            'status' => 'pending',
        ]);
        $request = $statement->fetch();

        return is_array($request) ? $request : null;
    }

    private function restoreOrderStock(string $orderId): void
    {
        $lockClause = $this->connection->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
            ? ' FOR UPDATE'
            : '';
        $items = $this->connection->prepare(
            'SELECT product_id, product_variant_id, product_name, quantity'
            . ' FROM order_items WHERE order_id = :order_id ORDER BY product_id, product_variant_id'
        );
        $items->execute(['order_id' => $orderId]);
        foreach ($items->fetchAll() as $item) {
            $productId = (string) $item['product_id'];
            $quantity = (int) $item['quantity'];
            $product = $this->connection->prepare(
                'SELECT stock_quantity FROM products WHERE id = :id' . $lockClause
            );
            $product->execute(['id' => $productId]);
            $productStock = $product->fetchColumn();
            if ($productStock === false || (int) $productStock + $quantity > 1000000000) {
                throw new DomainException('Không thể hoàn lại tồn kho cho đơn thanh toán thất bại.');
            }

            $variantId = $item['product_variant_id'] === null
                ? null
                : (string) $item['product_variant_id'];
            $before = (int) $productStock;
            if ($variantId !== null) {
                $variant = $this->connection->prepare(
                    'SELECT stock_quantity FROM product_variants WHERE id = :id AND product_id = :product_id'
                    . $lockClause
                );
                $variant->execute(['id' => $variantId, 'product_id' => $productId]);
                $variantStock = $variant->fetchColumn();
                if ($variantStock !== false) {
                    if ((int) $variantStock + $quantity > 1000000000) {
                        throw new DomainException('Không thể hoàn lại tồn kho biến thể cho đơn thanh toán thất bại.');
                    }
                    $before = (int) $variantStock;
                    $increaseVariant = $this->connection->prepare(
                        'UPDATE product_variants SET stock_quantity = stock_quantity + :quantity WHERE id = :id'
                    );
                    $increaseVariant->execute(['quantity' => $quantity, 'id' => $variantId]);
                } else {
                    $variantId = null;
                }
            }

            $increaseProduct = $this->connection->prepare(
                'UPDATE products SET stock_quantity = stock_quantity + :quantity WHERE id = :id'
            );
            $increaseProduct->execute(['quantity' => $quantity, 'id' => $productId]);
            InventoryMovement::record(
                $this->connection,
                $productId,
                $variantId,
                $orderId,
                null,
                'cancellation',
                $before,
                $before + $quantity,
                'Thanh toán VNPAY thất bại'
            );
        }
    }

    private function stringParameter(array $parameters, string $key): ?string
    {
        $value = $parameters[$key] ?? null;

        return is_string($value) && $value !== '' ? mb_substr($value, 0, 100) : null;
    }
}
