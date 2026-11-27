<section class="page-heading">
    <p class="eyebrow"><a href="/admin">Quản trị</a> / Đơn hàng</p>
    <h1>Quản lý đơn hàng</h1>
    <p class="page-description">Theo dõi đơn COD và cập nhật trạng thái theo từng bước.</p>
</section>

<section class="admin-content-card">
    <?php if ($orders === []): ?>
        <div class="empty-state">
            <h2>Chưa có đơn hàng</h2>
            <p>Đơn COD được xác nhận sẽ xuất hiện tại đây.</p>
        </div>
    <?php else: ?>
        <div class="orders-table-wrap">
            <table class="orders-table admin-table">
                <thead>
                    <tr>
                        <th>Mã đơn</th>
                        <th>Khách hàng</th>
                        <th>Ngày đặt</th>
                        <th>Sản phẩm</th>
                        <th>Trạng thái</th>
                        <th>Thanh toán</th>
                        <th>Tổng tiền</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td><?= e($order['order_number']) ?></td>
                            <td><?= e($order['customer_name']) ?></td>
                            <td><?= e(date('d/m/Y H:i', strtotime((string) $order['created_at']))) ?></td>
                            <td><?= e($order['item_count']) ?></td>
                            <td><?= e(order_status_label((string) $order['status'])) ?></td>
                            <td><?= e(payment_method_label((string) $order['payment_method'])) ?><br>
                                <?= e(payment_status_label((string) $order['payment_status'])) ?></td>
                            <td><?= e(format_vnd((int) $order['total_vnd'])) ?></td>
                            <td><a href="/admin/orders/<?= e($order['id']) ?>">Chi tiết</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
