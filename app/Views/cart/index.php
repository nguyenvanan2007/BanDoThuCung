<section class="page-heading">
    <p class="eyebrow">TPet / Giỏ hàng</p>
    <h1>Giỏ hàng của bạn</h1>
    <p class="page-description">Có thể thêm sản phẩm trước khi đăng nhập. Giỏ hàng được lưu trong phiên trình duyệt này.</p>
</section>

<section class="cart-page">
    <?php if ($items === []): ?>
        <div class="empty-state">
            <h2>Giỏ hàng đang trống</h2>
            <p>Chọn thức ăn cho chó hoặc mèo để thêm vào giỏ.</p>
            <a class="button" href="/">Xem sản phẩm</a>
        </div>
    <?php else: ?>
        <div class="cart-items">
            <?php foreach ($items as $item): ?>
                <?php $product = $item['product']; ?>
                <article class="cart-item">
                    <div class="cart-item-art" aria-hidden="true">
                        <?php if ($product !== null && $product['imagePath'] !== null): ?>
                            <img src="<?= e($product['imagePath']) ?>" alt="">
                        <?php else: ?>
                            <?= $product === null ? '📦' : e($product['icon']) ?>
                        <?php endif; ?>
                    </div>
                    <div class="cart-item-details">
                        <?php if ($product === null): ?>
                            <h2>Sản phẩm không còn khả dụng</h2>
                            <p>Sản phẩm có thể đã bị ẩn hoặc xóa khỏi cửa hàng.</p>
                        <?php else: ?>
                            <span class="product-label">
                                <?= e($product['speciesLabel']) ?> · <?= e($product['categoryLabel']) ?>
                                <?= $product['isDemo'] ? ' · DEMO' : '' ?>
                            </span>
                            <h2><?= e($product['name']) ?></h2>
                            <p><?= $product['isDemo'] ? 'Giá mẫu: ' : 'Giá: ' ?><?= e(format_vnd($product['price'])) ?></p>
                            <?php if ($product['stockQuantity'] < 1): ?>
                                <p class="stock-warning">Sản phẩm hiện đã hết hàng.</p>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>

                    <?php if ($product !== null && $product['stockQuantity'] > 0): ?>
                        <form class="cart-quantity-form" method="post" action="/cart/update">
                            <input type="hidden" name="_csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="product_id" value="<?= e($item['id']) ?>">
                            <label for="quantity-<?= e($item['id']) ?>">Số lượng</label>
                            <input
                                id="quantity-<?= e($item['id']) ?>"
                                name="quantity"
                                type="number"
                                min="1"
                                max="<?= e($product['stockQuantity']) ?>"
                                value="<?= e($item['quantity']) ?>"
                                required
                            >
                            <button class="text-button" type="submit">Cập nhật</button>
                        </form>
                    <?php else: ?>
                        <div class="cart-quantity-display">SL: <?= e($item['quantity']) ?></div>
                    <?php endif; ?>

                    <div class="cart-item-total">
                        <?php if ($product !== null): ?>
                            <strong><?= e(format_vnd($item['subtotal'])) ?></strong>
                        <?php else: ?>
                            <strong>—</strong>
                        <?php endif; ?>
                        <form method="post" action="/cart/remove">
                            <input type="hidden" name="_csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="product_id" value="<?= e($item['id']) ?>">
                            <button class="text-button remove-button" type="submit">Xóa</button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <aside class="cart-summary">
            <h2>Tóm tắt giỏ hàng</h2>
            <div class="checkout-line">
                <span>Số lượng sản phẩm</span>
                <strong><?= e(array_sum(array_column($items, 'quantity'))) ?></strong>
            </div>
            <div class="checkout-total">
                <span>Tạm tính</span>
                <strong><?= e(format_vnd($total)) ?></strong>
            </div>
            <?php if (!$catalogDemoMode && !$hasDemoItems): ?>
                <a class="button checkout-button" href="/checkout">Tiến hành thanh toán</a>
            <?php else: ?>
                <button class="button checkout-button" type="button" disabled>Đặt hàng không khả dụng với dữ liệu mẫu</button>
            <?php endif; ?>
            <p class="cart-summary-note">Phí giao hàng sẽ được hiển thị ở bước thanh toán.</p>
            <a class="reset-link" href="/">Tiếp tục xem sản phẩm</a>
        </aside>
    <?php endif; ?>
</section>
