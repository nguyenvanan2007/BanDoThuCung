<section class="page-heading">
    <p class="eyebrow">TPet / Tài khoản</p>
    <h1>Đơn hàng của tôi</h1>
    <p class="page-description">Danh sách đơn hàng thuộc tài khoản của bạn.</p>
</section>

<section class="orders-page">
    <?php if ($orders === []): ?>
        <div class="empty-state">
            <h2>Bạn chưa có đơn hàng</h2>
            <p>Các đơn hàng sau khi đặt sẽ xuất hiện ở đây.</p>
            <a class="button" href="/">Xem sản phẩm</a>
        </div>
    <?php else: ?>
        <div class="orders-table-wrap">
            <table class="orders-table">
                <thead>
                    <tr>
                        <th>Mã đơn</th>
                        <th>Ngày đặt</th>
                        <th>Sản phẩm</th>
                        <th>Trạng thái</th>
                        <th>Tổng tiền</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td><?= e($order['order_number']) ?></td>
                            <td><?= e(date('d/m/Y H:i', strtotime((string) $order['created_at']))) ?></td>
                            <td><?= e($order['item_count']) ?></td>
                            <td>
                                <span class="order-status order-status-<?= e($order['status']) ?>">
                                    <?= e(order_status_label((string) $order['status'])) ?>
                                </span>
                            </td>
                            <td><?= e(format_vnd((int) $order['total_vnd'])) ?></td>
                            <td><a href="/orders/<?= e($order['id']) ?>">Chi tiết</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
