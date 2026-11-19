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
                    <a href="/admin/orders">Đơn hàng</a>
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
        <div class="footer-brand">
            <strong>TPet</strong>
            <span>Cửa hàng thức ăn cho thú cưng Địa chỉ: 123 Nguyễn Văn A, Hà Nội </span>
        </div>
        <address class="footer-contact">
            <a href="tel:0977365366">SĐT: 0977 365 366</a>
            <a href="mailto:abc@gmail.com">Email: abc@gmail.com</a>
        </address>
    </footer>
</body>
</html>
