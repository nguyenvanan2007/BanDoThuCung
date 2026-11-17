<section class="auth-panel account-panel">
    <p class="eyebrow">TPet / Tài khoản</p>
    <h1>Xin chào, <?= e($user['name']) ?></h1>
    <p class="page-description">Email tài khoản: <?= e($user['email']) ?></p>
    <p>Quản lý đơn hàng hoặc tiếp tục mua sắm tại cửa hàng.</p>
    <nav class="account-shortcuts" aria-label="Lối tắt tài khoản">
        <a class="account-shortcut" href="/orders">
            <span>
                <strong>Đơn hàng của tôi</strong>
                <small>Xem trạng thái và chi tiết đơn đã đặt</small>
            </span>
            <span class="account-shortcut-arrow" aria-hidden="true">→</span>
        </a>
        <a class="account-shortcut" href="/cart">
            <span>
                <strong>Giỏ hàng</strong>
                <small>Kiểm tra sản phẩm trước khi thanh toán</small>
            </span>
            <span class="account-shortcut-arrow" aria-hidden="true">→</span>
        </a>
        <a class="account-shortcut" href="/">
            <span>
                <strong>Tiếp tục mua sắm</strong>
                <small>Quay lại danh sách thức ăn cho thú cưng</small>
            </span>
            <span class="account-shortcut-arrow" aria-hidden="true">→</span>
        </a>
    </nav>
</section>
