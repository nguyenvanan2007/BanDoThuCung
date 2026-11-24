<section class="page-heading">
    <p class="eyebrow"><a href="/orders">Đơn hàng của tôi</a> / Chi tiết</p>
    <h1>Đơn <?= e($order['order_number']) ?></h1>
    <p class="page-description">
        Trạng thái:
        <span class="order-status order-status-<?= e($order['status']) ?>">
            <?= e(order_status_label((string) $order['status'])) ?>
        </span>
        · <?= e(payment_method_label((string) $order['payment_method'])) ?>
    </p>
    <p>Trạng thái thanh toán: <?= e(payment_status_label((string) $order['payment_status'])) ?></p>
</section>

<?php if ($order['status'] === 'cancelled' && $order['payment_status'] === 'paid'): ?>
    <div class="form-errors" role="alert">
        Giao dịch được xác nhận sau khi đơn đã hủy. Vui lòng liên hệ CSKH để được hướng dẫn hoàn tiền.
    </div>
<?php endif; ?>

<?php if ($canCancel): ?>
    <section class="order-cancel-panel">
        <form method="post" action="/orders/<?= e($order['id']) ?>/cancel">
            <input type="hidden" name="_csrf_token" value="<?= e(csrf_token()) ?>">
            <label>
                <input name="confirm_cancel" type="checkbox" value="1" required>
                Tôi xác nhận muốn hủy đơn hàng này
            </label>
            <button class="button danger-button" type="submit">Hủy đơn hàng</button>
        </form>
        <p>Chỉ có thể tự hủy khi đơn hàng còn chờ xác nhận.</p>
    </section>
<?php endif; ?>

<section class="order-detail-page">
    <div class="order-detail-card">
        <h2>Sản phẩm</h2>
        <div class="orders-table-wrap">
            <table class="orders-table">
                <thead>
                    <tr>
                        <th>Sản phẩm</th>
                        <th>Mã hàng</th>
                        <th>Số lượng</th>
                        <th>Đơn giá</th>
                        <th>Tạm tính</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($order['items'] as $item): ?>
                        <tr>
                            <td class="order-product-name">
                                <?php if (!empty($item['product_image_path'])): ?>
                                    <img src="<?= e($item['product_image_path']) ?>" alt="" loading="lazy" decoding="async">
                                <?php endif; ?>
                                <span><?= e($item['product_name']) ?></span>
                            </td>
                            <td><?= e($item['product_sku']) ?></td>
                            <td><?= e($item['quantity']) ?></td>
                            <td><?= e(format_vnd((int) $item['unit_price_vnd'])) ?></td>
                            <td><?= e(format_vnd((int) $item['subtotal_vnd'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <aside class="order-detail-card">
        <h2>Giao hàng</h2>
        <p><strong><?= e($order['recipient_name']) ?></strong></p>
        <p><?= e($order['phone']) ?></p>
        <p><?= e($order['shipping_address']) ?></p>
        <hr>
        <div class="checkout-line">
            <span>Tạm tính</span>
            <strong><?= e(format_vnd((int) $order['subtotal_vnd'])) ?></strong>
        </div>
        <div class="checkout-line">
            <span>Phí giao hàng</span>
            <strong><?= e(format_vnd((int) $order['shipping_fee_vnd'])) ?></strong>
        </div>
        <?php if ((int) $order['discount_vnd'] > 0): ?>
            <div class="checkout-line">
                <span>Giảm giá<?= $order['coupon_code'] !== null ? ' (' . e($order['coupon_code']) . ')' : '' ?></span>
                <strong>-<?= e(format_vnd((int) $order['discount_vnd'])) ?></strong>
            </div>
        <?php endif; ?>
        <div class="checkout-total">
            <span>Tổng cộng</span>
            <strong><?= e(format_vnd((int) $order['total_vnd'])) ?></strong>
        </div>
    </aside>
</section>

<?php require dirname(__DIR__) . '/partials/order-history.php'; ?>
