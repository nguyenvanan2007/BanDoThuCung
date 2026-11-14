<section class="page-heading">
    <p class="eyebrow">Pet House / Quản trị</p>
    <h1>Khu quản trị</h1>
    <p class="page-description">Quản lý sản phẩm thức ăn cho chó mèo và theo dõi đơn đặt hàng.</p>
</section>

<section class="admin-dashboard-grid" aria-label="Tổng quan cửa hàng">
    <article class="admin-stat-card">
        <span>Sản phẩm đang bán</span>
        <strong><?= e($activeProductCount) ?></strong>
        <a href="/admin/products">Quản lý sản phẩm</a>
    </article>
    <article class="admin-stat-card">
        <span>Tổng đơn hàng</span>
        <strong><?= e($orderStats['orderCount']) ?></strong>
        <a href="/admin/orders">Xem đơn hàng</a>
    </article>
    <article class="admin-stat-card">
        <span>Đơn chờ xác nhận</span>
        <strong><?= e($orderStats['pendingCount']) ?></strong>
        <a href="/admin/orders">Xử lý đơn hàng</a>
    </article>
</section>
