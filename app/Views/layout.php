<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="TPet - cửa hàng thức ăn dành cho chó và mèo.">
    <title><?= e($title ?? 'TPet') ?></title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
    <?php
    $headerUser = $_SESSION['user'] ?? null;
    $pendingOrderCount = isset($pendingOrderCount) && is_int($pendingOrderCount)
        ? max(0, $pendingOrderCount)
        : 0;
    $headerAccountName = is_array($headerUser) && is_string($headerUser['name'] ?? null)
        ? trim($headerUser['name'])
        : '';
    ?>
    <header class="site-header">
        <a class="brand" href="/" aria-label="TPet - Trang chủ">
            <span class="brand-mark" aria-hidden="true">
                <img src="/assets/images/tpet-logo.png" alt="">
            </span>
            <span>TPet</span>
        </a>
        <nav class="site-nav" aria-label="Điều hướng chính">
            <a href="/">Trang chủ</a>
            <?php if (!is_array($headerUser) || ($headerUser['role'] ?? '') !== 'admin'): ?>
                <a href="/cart">Giỏ hàng</a>
            <?php endif; ?>
            <?php if (isset($_SESSION['user']) && is_array($_SESSION['user'])): ?>
                <?php if (($_SESSION['user']['role'] ?? '') === 'admin'): ?>
                    <a href="/admin">Quản trị</a>
                    <a href="/admin/products">Sản phẩm</a>
                    <a href="/admin/categories">Danh mục</a>
                    <a href="/admin/users">Tài khoản</a>
                    <a
                        class="nav-orders-link"
                        href="/admin/orders"
                        aria-label="<?= e($pendingOrderCount > 0
                            ? 'Đơn hàng, ' . $pendingOrderCount . ' đơn chờ xác nhận'
                            : 'Đơn hàng') ?>"
                    >
                        Đơn hàng
                        <?php if ($pendingOrderCount > 0): ?>
                            <span class="nav-notification-badge" aria-hidden="true">
                                <?= e($pendingOrderCount > 99 ? '99+' : (string) $pendingOrderCount) ?>
                            </span>
                        <?php endif; ?>
                    </a>
                    <a href="/admin/inventory">Kho</a>
                    <a href="/admin/coupons">Mã giảm giá</a>
                    <a href="/admin/reports">Báo cáo</a>
                <?php else: ?>
                    <a href="/account">Tài khoản</a>
                    <a href="/orders">Đơn hàng</a>
                <?php endif; ?>
                <form class="logout-form" method="post" action="/logout">
                    <input type="hidden" name="_csrf_token" value="<?= e(csrf_token()) ?>">
                    <button class="nav-button" type="submit">Đăng xuất</button>
                </form>
            <?php else: ?>
                <a href="/login">Đăng nhập</a>
                <a href="/register">Đăng ký</a>
            <?php endif; ?>
        </nav>
        <?php if ($headerAccountName !== ''): ?>
            <p class="site-tagline site-account-name">Xin chào, <?= e($headerAccountName) ?></p>
        <?php else: ?>
            <p class="site-tagline">Thức ăn cho chó và mèo</p>
        <?php endif; ?>
    </header>
    <main>
        <?php $flash = take_flash(); ?>
        <?php if ($flash !== null): ?>
            <div class="flash-message flash-<?= e($flash['type'] ?? 'success') ?>" role="status">
                <?= e($flash['message'] ?? '') ?>
            </div>
        <?php endif; ?>
        <?= $content ?? '' ?>
    </main>
    <footer class="site-footer">
        <section class="footer-highlights" aria-label="Thông tin mua sắm">
            <div class="footer-container footer-highlight-grid">
                <div class="footer-highlight">
                    <span class="footer-highlight-icon" aria-hidden="true">🐾</span>
                    <span>
                        <strong>Thức ăn cho chó mèo</strong>
                        <small>Chọn theo thú cưng</small>
                    </span>
                </div>
                <div class="footer-highlight">
                    <span class="footer-highlight-icon" aria-hidden="true">⌕</span>
                    <span>
                        <strong>Tìm sản phẩm dễ dàng</strong>
                        <small>Lọc theo tên, loại và giá</small>
                    </span>
                </div>
                <div class="footer-highlight">
                    <span class="footer-highlight-icon" aria-hidden="true">💳</span>
                    <span>
                        <strong>Thanh toán linh hoạt</strong>
                        <small>COD hoặc VNPAY</small>
                    </span>
                </div>
                <div class="footer-highlight">
                    <span class="footer-highlight-icon" aria-hidden="true">📦</span>
                    <span>
                        <strong>Quản lý đơn hàng</strong>
                        <small>Xem đơn trong tài khoản</small>
                    </span>
                </div>
            </div>
        </section>

        <div class="footer-main">
            <div class="footer-container footer-grid">
                <section class="footer-column footer-company">
                    <a class="footer-logo" href="/">TPet</a>
                    <p>Cửa hàng thức ăn cho chó và mèo.</p>
                    <address class="footer-contact">
                        <span>Địa chỉ: 123 Nguyễn Văn A, Hà Nội</span>
                        <a href="tel:0977365366">SĐT: 0977 365 366</a>
                        <a href="mailto:abc@gmail.com">Email: abc@gmail.com</a>
                    </address>
                </section>

                <nav class="footer-column" aria-label="Mua sắm">
                    <h2>Mua sắm</h2>
                    <a href="/">Tất cả sản phẩm</a>
                    <a href="/?species=dog#catalog">Thức ăn cho chó</a>
                    <a href="/?species=cat#catalog">Thức ăn cho mèo</a>
                    <a href="/cart">Giỏ hàng</a>
                </nav>

                <nav class="footer-column" aria-label="Hỗ trợ khách hàng">
                    <h2>Hỗ trợ khách hàng</h2>
                    <a href="/account">Tài khoản</a>
                    <a href="/orders">Đơn hàng</a>
                    <a href="/login">Đăng nhập</a>
                    <a href="/register">Đăng ký</a>
                </nav>

                <section class="footer-column">
                    <h2>Thanh toán</h2>
                    <p>Chọn phương thức thanh toán khi đặt hàng:</p>
                    <div class="footer-payment-options" aria-label="COD và VNPAY">
                        <span>COD</span>
                        <span>VNPAY</span>
                    </div>
                </section>
            </div>
        </div>

        <div class="footer-bottom">
            <div class="footer-container footer-bottom-content">
                <span>© <?= date('Y') ?> TPet</span>
                <span>Thức ăn dành cho chó và mèo</span>
            </div>
        </div>
    </footer>
</body>
</html>
