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
    $cartItemCount = 0;
    $cartItemsForBadge = $_SESSION['cart_items'] ?? [];
    if (is_array($cartItemsForBadge)) {
        foreach ($cartItemsForBadge as $cartKey => $quantity) {
            if (preg_match('/\A[1-9][0-9]*(?::v[1-9][0-9]*)?\z/', (string) $cartKey) === 1
                && is_int($quantity)
                && $quantity > 0) {
                $cartItemCount = $quantity > PHP_INT_MAX - $cartItemCount
                    ? PHP_INT_MAX
                    : $cartItemCount + $quantity;
            }
        }
    }
    $headerAccountName = is_array($headerUser) && is_string($headerUser['name'] ?? null)
        ? trim($headerUser['name'])
        : '';
    ?>
    <header class="site-header">
        <a class="brand" href="/" aria-label="TPet - Trang chủ">
            <span class="brand-mark" aria-hidden="true">
                <img src="/assets/images/logo/tpet-logo.png" alt="">
            </span>
            <span>TPet</span>
        </a>
        <nav class="site-nav" aria-label="Điều hướng chính">
            <a href="/">Trang chủ</a>
            <?php if (!is_array($headerUser) || ($headerUser['role'] ?? '') !== 'admin'): ?>
                <a
                    class="nav-cart-link"
                    href="/cart"
                    aria-label="<?= e($cartItemCount > 0
                        ? 'Giỏ hàng, ' . $cartItemCount . ' sản phẩm'
                        : 'Giỏ hàng') ?>"
                >
                    Giỏ hàng
                    <span class="cart-count-badge" data-cart-count aria-hidden="true" <?= $cartItemCount > 0 ? '' : 'hidden' ?>>
                        <?= e($cartItemCount > 99 ? '99+' : (string) $cartItemCount) ?>
                    </span>
                </a>
            <?php endif; ?>
            <?php if (isset($_SESSION['user']) && is_array($_SESSION['user'])): ?>
                <?php if (($_SESSION['user']['role'] ?? '') === 'admin'): ?>
                    <a
                        class="nav-admin-link"
                        href="/admin"
                        aria-label="<?= e($pendingOrderCount > 0
                            ? 'Quản trị, ' . $pendingOrderCount . ' đơn chờ xác nhận'
                            : 'Quản trị') ?>"
                    >
                        Quản trị
                        <?php if ($pendingOrderCount > 0): ?>
                            <span class="nav-notification-badge" aria-hidden="true">
                                <?= e($pendingOrderCount > 99 ? '99+' : (string) $pendingOrderCount) ?>
                            </span>
                        <?php endif; ?>
                    </a>
                    <a href="/admin/categories">Danh mục</a>
                    <a href="/admin/inventory">Kho</a>
                    <a href="/admin/coupons">Mã giảm giá</a>
                    <a href="/admin/reports">Báo cáo</a>
                <?php else: ?>
                    <a href="/account">Tài khoản</a>
                    <a href="/orders">Đơn hàng</a>
                <?php endif; ?>
            <?php else: ?>
                <a href="/login">Đăng nhập</a>
                <a href="/register">Đăng ký</a>
            <?php endif; ?>
        </nav>
        <div class="header-account">
            <?php if ($headerAccountName !== ''): ?>
                <p class="site-tagline site-account-name">Xin chào, <?= e($headerAccountName) ?></p>
            <?php else: ?>
                <p class="site-tagline">Thức ăn cho chó và mèo</p>
            <?php endif; ?>
            <?php if (is_array($headerUser)): ?>
                <form class="header-logout-form" method="post" action="/logout">
                    <input type="hidden" name="_csrf_token" value="<?= e(csrf_token()) ?>">
                    <button class="header-logout-button" type="submit" aria-label="Đăng xuất" title="Đăng xuất">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M10 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h5" />
                            <path d="M17 16l4-4-4-4" />
                            <path d="M21 12H9" />
                        </svg>
                    </button>
                </form>
            <?php endif; ?>
        </div>
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
    <dialog class="quick-cart-dialog" id="quick-cart-dialog" aria-labelledby="quick-cart-title">
        <div class="quick-cart-panel">
            <button class="quick-cart-close" type="button" data-quick-cart-close aria-label="Đóng">×</button>
            <h2 id="quick-cart-title">Thêm vào giỏ hàng</h2>
            <div class="quick-cart-product">
                <div class="quick-cart-image">
                    <img data-quick-cart-image alt="" hidden>
                    <span class="quick-cart-image-icon" data-quick-cart-icon aria-hidden="true" hidden></span>
                </div>
                <div class="quick-cart-product-copy">
                    <strong data-quick-cart-name></strong>
                    <p data-quick-cart-price></p>
                </div>
            </div>
            <p class="quick-cart-error" data-quick-cart-error role="alert" hidden></p>
            <form class="quick-cart-form" data-quick-cart-form method="post" action="/cart/add">
                <input type="hidden" name="_csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="product_id">
                <div class="quick-cart-quantity-row">
                    <label for="quick-cart-quantity">Số lượng</label>
                    <div class="quick-cart-stepper">
                        <button type="button" data-quantity-decrease aria-label="Giảm số lượng">−</button>
                        <input id="quick-cart-quantity" name="quantity" type="number" min="1" value="1" required>
                        <button type="button" data-quantity-increase aria-label="Tăng số lượng">+</button>
                    </div>
                </div>
                <button class="button quick-cart-submit" type="submit">
                    <svg class="cart-button-icon" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M3 4h2l2.2 11.1a2 2 0 0 0 2 1.6h8.8a2 2 0 0 0 1.9-1.5L22 8H6" />
                        <circle cx="10" cy="20" r="1" />
                        <circle cx="18" cy="20" r="1" />
                    </svg>
                    Thêm vào giỏ hàng
                </button>
            </form>
        </div>
    </dialog>
    <p class="cart-toast" id="cart-toast" role="status" aria-live="polite" hidden></p>
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
    <script src="/assets/js/cart.js" defer></script>
</body>
</html>
