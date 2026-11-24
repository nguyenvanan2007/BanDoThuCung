<section class="page-heading">
    <p class="eyebrow">TPet / Thanh toán</p>
    <h1>Thông tin giao hàng</h1>
    <p class="page-description">Chọn phương thức thanh toán khi xác nhận đơn hàng.</p>
</section>

<section class="checkout-layout">
    <div class="checkout-main">
        <?php if ($errors !== []): ?>
            <div class="form-errors" role="alert">
                <?php foreach ($errors as $error): ?>
                    <p><?= e($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($items === []): ?>
            <div class="empty-state">
                <h2>Không có sản phẩm để thanh toán</h2>
                <p>Hãy quay lại giỏ hàng và chọn sản phẩm còn bán.</p>
                <a class="button" href="/cart">Quay lại giỏ hàng</a>
            </div>
        <?php elseif (!$isDemoMode && $errors === []): ?>
            <form class="auth-form checkout-form coupon-apply-form" method="get" action="/checkout">
                <label for="coupon-code">Mã giảm giá</label>
                <input id="coupon-code" name="coupon_code" type="text" maxlength="40" value="<?= e($couponCode) ?>">
                <button class="button secondary-button" type="submit">Áp dụng mã</button>
            </form>
            <form class="auth-form checkout-form" method="post" action="/checkout">
                <input type="hidden" name="_csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="coupon_code" value="<?= e($couponCode) ?>">
                <label for="recipient_name">Tên người nhận</label>
                <input id="recipient_name" name="recipient_name" type="text" minlength="2" maxlength="100" required value="<?= e($recipientName) ?>">

                <label for="phone">Số điện thoại</label>
                <input id="phone" name="phone" type="tel" autocomplete="tel" maxlength="20" required value="<?= e($phone) ?>">

                <label for="address">Địa chỉ giao hàng</label>
                <textarea id="address" name="address" rows="4" minlength="10" maxlength="500" required><?= e($address) ?></textarea>

                <fieldset>
                    <legend>Phương thức thanh toán</legend>
                    <label>
                        <input type="radio" name="payment_method" value="cod" <?= $paymentMethod === 'cod' ? 'checked' : '' ?>>
                        Tiền mặt khi nhận hàng (COD)
                    </label>
                    <label>
                        <input type="radio" name="payment_method" value="vnpay" <?= $paymentMethod === 'vnpay' ? 'checked' : '' ?>>
                        Thanh toán trực tuyến qua VNPAY
                    </label>
                </fieldset>
                <button class="button" type="submit">Xác nhận đặt hàng</button>
            </form>
        <?php elseif (!$isDemoMode && $items !== []): ?>
            <form class="auth-form checkout-form coupon-apply-form" method="get" action="/checkout">
                <label for="coupon-code">Mã giảm giá</label>
                <input id="coupon-code" name="coupon_code" type="text" maxlength="40" value="<?= e($couponCode) ?>">
                <button class="button secondary-button" type="submit">Áp dụng mã</button>
            </form>
            <a class="button" href="/cart">Quay lại cập nhật giỏ hàng</a>
        <?php else: ?>
            <a class="button" href="/cart">Quay lại cập nhật giỏ hàng</a>
        <?php endif; ?>
    </div>

    <?php if ($items !== []): ?>
        <aside class="cart-summary">
            <h2>Tóm tắt đơn hàng</h2>
            <?php foreach ($items as $item): ?>
                <div class="checkout-line">
                    <span>
                        <?= e($item['product']['name']) ?>
                        <?= $item['variant'] !== null ? ' · ' . e($item['variant']['name']) : '' ?>
                        × <?= e($item['quantity']) ?>
                    </span>
                    <strong><?= e(format_vnd($item['subtotal'])) ?></strong>
                </div>
            <?php endforeach; ?>
            <div class="checkout-line">
                <span>Tạm tính</span>
                <strong><?= $total === null ? 'Chưa thể xác định' : e(format_vnd($subtotal)) ?></strong>
            </div>
            <div class="checkout-line">
                <span>Phí giao hàng</span>
                <strong><?= e(format_vnd($shippingFee)) ?></strong>
            </div>
            <?php if ($discount > 0): ?>
                <div class="checkout-line">
                    <span>Giảm giá (<?= e($couponCode) ?>)</span>
                    <strong>-<?= e(format_vnd($discount)) ?></strong>
                </div>
            <?php endif; ?>
            <div class="checkout-total">
                <span>Tổng cộng</span>
                <strong><?= $total === null ? 'Xử lý lỗi để tính tổng' : e(format_vnd($total)) ?></strong>
            </div>
        </aside>
    <?php endif; ?>
</section>
