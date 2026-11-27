<section class="page-heading">
    <p class="eyebrow"><a href="/admin">Quản trị</a> / <a href="/admin/orders">Đơn hàng</a></p>
    <h1>Đơn <?= e($order['order_number']) ?></h1>
    <p class="page-description">
        Trạng thái đơn: <strong><?= e(order_status_label((string) $order['status'])) ?></strong>
        · Thanh toán: <strong><?= e(payment_method_label((string) $order['payment_method'])) ?></strong>
        · <?= e(payment_status_label((string) $order['payment_status'])) ?>
    </p>
</section>

<section class="admin-order-layout">
    <div class="admin-content-card">
        <h2>Sản phẩm trong đơn</h2>
        <div class="orders-table-wrap">
            <table class="orders-table admin-table">
                <thead>
                    <tr>
                        <th>Sản phẩm</th>
                        <th>Mã sản phẩm</th>
                        <th>Số lượng</th>
                        <th>Đơn giá</th>
                        <th>Tạm tính</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($order['items'] as $item): ?>
                        <tr>
                            <td class="order-product-name">
                                <?php if ($item['product_image_path'] !== ''): ?>
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

    <aside class="admin-content-card admin-order-summary">
        <h2>Thông tin giao hàng</h2>
        <p><strong><?= e($order['recipient_name']) ?></strong></p>
        <p><?= e($order['customer_email']) ?></p>
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
        <?php if ($order['refund'] !== null): ?>
            <hr>
            <h3>Thông tin hoàn tiền</h3>
            <p><?= e(payment_status_label((string) $order['payment_status'])) ?> ·
                <?= e(format_vnd((int) $order['refund']['amount_vnd'])) ?></p>
            <p><?= e($order['refund']['note']) ?></p>
            <small><?= e($order['refund']['processed_at']) ?> · <?= e($order['refund']['processed_by_name'] ?? 'Admin') ?></small>
        <?php endif; ?>

        <?php if ($order['payment_method'] === 'cod'
            && $order['payment_status'] === 'unpaid'
            && in_array($order['status'], ['shipping', 'completed'], true)): ?>
            <form class="admin-status-form" method="post" action="/admin/orders/<?= e($order['id']) ?>/cod-paid">
                <input type="hidden" name="_csrf_token" value="<?= e(csrf_token()) ?>">
                <button class="button" type="submit">Ghi nhận đã thu tiền COD</button>
            </form>
        <?php endif; ?>

        <?php if ($order['payment_status'] === 'paid'): ?>
            <form class="admin-status-form" method="post" action="/admin/orders/<?= e($order['id']) ?>/refund">
                <input type="hidden" name="_csrf_token" value="<?= e(csrf_token()) ?>">
                <p>Thực hiện hoàn tiền trên cổng thanh toán trước, sau đó ghi nhận kết quả tại đây.</p>
                <label for="refund-note">Ghi chú / mã giao dịch hoàn tiền</label>
                <input id="refund-note" name="note" type="text" minlength="3" maxlength="500" required>
                <button class="button danger-button" type="submit">Ghi nhận đã hoàn tiền</button>
            </form>
        <?php endif; ?>

        <?php if ($nextStatuses !== []): ?>
            <form class="admin-status-form" method="post" action="/admin/orders/<?= e($order['id']) ?>/status">
                <input type="hidden" name="_csrf_token" value="<?= e(csrf_token()) ?>">
                <label for="status">Cập nhật trạng thái</label>
                <select id="status" name="status" required>
                    <?php foreach ($nextStatuses as $status): ?>
                        <option value="<?= e($status) ?>"><?= e(order_status_label($status)) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="button" type="submit">Cập nhật đơn hàng</button>
            </form>
        <?php else: ?>
            <p class="admin-terminal-status">Đơn hàng đã kết thúc, không thể đổi trạng thái.</p>
        <?php endif; ?>
    </aside>
</section>

<?php require dirname(__DIR__, 2) . '/partials/order-history.php'; ?>
