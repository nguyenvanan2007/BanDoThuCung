<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Pet House - cửa hàng thức ăn dành cho chó và mèo.">
    <title><?= e($title ?? 'Pet House') ?></title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
    <header class="site-header">
        <a class="brand" href="/" aria-label="Pet House - Trang chủ">
            <span class="brand-mark" aria-hidden="true">P</span>
            <span>Pet House</span>
        </a>
        <nav class="site-nav" aria-label="Điều hướng chính">
            <a href="/">Trang chủ</a>
            <a href="/products">Sản phẩm</a>
            <a href="/cart">Giỏ hàng</a>
            <?php if (isset($_SESSION['user']) && is_array($_SESSION['user'])): ?>
                <a href="/account">Tài khoản</a>
                <a href="/orders">Đơn hàng</a>
                <form class="logout-form" method="post" action="/logout">
                    <input type="hidden" name="_csrf_token" value="<?= e(csrf_token()) ?>">
                    <button class="nav-button" type="submit">Đăng xuất</button>
                </form>
            <?php else: ?>
                <a href="/login">Đăng nhập</a>
                <a href="/register">Đăng ký</a>
            <?php endif; ?>
        </nav>
        <p class="site-tagline">Thức ăn cho chó và mèo</p>
    </header>
    <main>
        <?php $flash = take_flash(); ?>
        <?php if ($flash !== null): ?>
            <div class="flash-message flash-<?= e($flash['type'] ?? 'success') ?>" role="status">
                <?= e($flash['message'] ?? '') ?>
            </div>
        <?php endif; ?>
        <?= $content ?>
    </main>
    <footer class="site-footer">
        <p>Pet House <span aria-hidden="true">&middot;</span> Cửa hàng thức ăn cho thú cưng</p>
    </footer>
</body>
</html>
