<section class="page-heading">
    <p class="eyebrow"><a href="/orders">Đơn hàng của tôi</a> / Chi tiết</p>
    <h1>Đơn <?= e($order['order_number']) ?></h1>
    <p class="page-description">
        Trạng thái:
        <span class="order-status order-status-<?= e($order['status']) ?>">
            <?= e(order_status_label((string) $order['status'])) ?>
        </span>
        · Thanh toán khi nhận hàng (COD)
    </p>
</section>

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
        <div class="checkout-total">
            <span>Tổng cộng</span>
            <strong><?= e(format_vnd((int) $order['total_vnd'])) ?></strong>
        </div>
    </aside>
</section>
