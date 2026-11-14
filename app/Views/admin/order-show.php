<section class="page-heading">
    <p class="eyebrow"><a href="/admin">Quản trị</a> / <a href="/admin/orders">Đơn hàng</a></p>
    <h1>Đơn <?= e($order['order_number']) ?></h1>
    <p class="page-description">Trạng thái hiện tại: <strong><?= e(order_status_label((string) $order['status'])) ?></strong></p>
</section>

<section class="admin-order-layout">
    <div class="admin-content-card">
        <h2>Sản phẩm trong đơn</h2>
        <div class="orders-table-wrap">
            <table class="orders-table admin-table">
                <thead>
                    <tr>
                        <th>Sản phẩm</th>
                        <th>Mã SKU</th>
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
        <div class="checkout-total">
            <span>Tổng cộng (COD)</span>
            <strong><?= e(format_vnd((int) $order['total_vnd'])) ?></strong>
        </div>

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
