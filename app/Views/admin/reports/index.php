<section class="page-heading admin-page-heading">
    <div>
        <p class="eyebrow"><a href="/admin">Quản trị</a> / Báo cáo</p>
        <h1>Báo cáo cửa hàng</h1>
        <p class="page-description">Doanh thu hiển thị theo trạng thái thanh toán; đơn SePay chỉ được tính sau khi webhook xác nhận chuyển khoản.</p>
    </div>
</section>

<section class="admin-content-card">
    <h2>Đơn hàng theo trạng thái</h2>
    <div class="orders-table-wrap">
        <table class="orders-table admin-table">
            <thead><tr><th>Trạng thái</th><th>Số đơn</th></tr></thead>
            <tbody>
                <?php foreach ($orderCounts as $row): ?>
                    <tr>
                        <td><?= e(order_status_label((string) $row['status'])) ?></td>
                        <td><?= e($row['order_count']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="admin-content-card">
    <h2>Doanh thu theo ngày (30 ngày gần nhất)</h2>
    <div class="orders-table-wrap">
        <table class="orders-table admin-table">
            <thead><tr><th>Ngày</th><th>Thanh toán</th><th>Số đơn</th><th>Doanh thu ròng</th></tr></thead>
            <tbody>
                <?php foreach ($dailyRevenue as $row): ?>
                    <tr>
                        <td><?= e($row['period']) ?></td>
                        <td><?= e(payment_status_label((string) $row['payment_status'])) ?></td>
                        <td><?= e($row['order_count']) ?></td>
                        <td><?= e(format_vnd((int) $row['total_vnd'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="admin-content-card">
    <h2>Doanh thu theo tháng (12 tháng gần nhất)</h2>
    <div class="orders-table-wrap">
        <table class="orders-table admin-table">
            <thead><tr><th>Tháng</th><th>Thanh toán</th><th>Số đơn</th><th>Doanh thu ròng</th></tr></thead>
            <tbody>
                <?php foreach ($monthlyRevenue as $row): ?>
                    <tr>
                        <td><?= e($row['period']) ?></td>
                        <td><?= e(payment_status_label((string) $row['payment_status'])) ?></td>
                        <td><?= e($row['order_count']) ?></td>
                        <td><?= e(format_vnd((int) $row['total_vnd'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="admin-content-card">
    <h2>Sản phẩm bán chạy (30 ngày gần nhất)</h2>
    <div class="orders-table-wrap">
        <table class="orders-table admin-table">
            <thead><tr><th>Sản phẩm</th><th>SKU</th><th>Đã bán</th><th>Doanh số</th></tr></thead>
            <tbody>
                <?php foreach ($bestSellers as $row): ?>
                    <tr>
                        <td><?= e($row['product_name']) ?></td>
                        <td><?= e($row['product_sku']) ?></td>
                        <td><?= e($row['quantity_sold']) ?></td>
                        <td><?= e(format_vnd((int) $row['revenue_vnd'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="admin-content-card">
    <h2>Sản phẩm bán chậm (30 ngày gần nhất)</h2>
    <div class="orders-table-wrap">
        <table class="orders-table admin-table">
            <thead><tr><th>Sản phẩm</th><th>SKU</th><th>Đã bán</th></tr></thead>
            <tbody>
                <?php foreach ($slowSellers as $row): ?>
                    <tr>
                        <td><?= e($row['name']) ?></td>
                        <td><?= e($row['sku']) ?></td>
                        <td><?= e($row['quantity_sold']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="admin-content-card">
    <h2>Sắp hết hàng / hết hàng</h2>
    <div class="orders-table-wrap">
        <table class="orders-table admin-table">
            <thead><tr><th>Sản phẩm</th><th>SKU</th><th>Tồn kho</th><th>Ngưỡng cảnh báo</th></tr></thead>
            <tbody>
                <?php foreach ($inventoryAlerts as $row): ?>
                    <tr>
                        <td><?= e($row['name']) ?></td>
                        <td><?= e($row['sku']) ?></td>
                        <td><?= e($row['stock_quantity']) ?></td>
                        <td><?= e($row['low_stock_threshold']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="admin-content-card">
    <h2>Sử dụng mã giảm giá</h2>
    <div class="orders-table-wrap">
        <table class="orders-table admin-table">
            <thead><tr><th>Mã</th><th>Lượt dùng</th><th>Tổng giảm</th></tr></thead>
            <tbody>
                <?php foreach ($couponUsage as $row): ?>
                    <tr>
                        <td><?= e($row['coupon_code']) ?></td>
                        <td><?= e($row['usage_count']) ?></td>
                        <td><?= e(format_vnd((int) $row['discount_total_vnd'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
