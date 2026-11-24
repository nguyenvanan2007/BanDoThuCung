<section class="page-heading admin-page-heading">
    <div>
        <p class="eyebrow"><a href="/admin">Quản trị</a> / Mã giảm giá</p>
        <h1>Quản lý mã giảm giá</h1>
        <p class="page-description">Mã được kiểm tra và giữ lượt sử dụng cùng transaction tạo đơn hàng.</p>
    </div>
</section>

<?php if ($errors !== []): ?>
    <div class="form-errors" role="alert">
        <strong>Vui lòng kiểm tra:</strong>
        <ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<form class="admin-content-card admin-coupon-form" method="post" action="/admin/coupons">
    <h2><?= $form['id'] === '' ? 'Tạo mã mới' : 'Sửa mã ' . e($form['code']) ?></h2>
    <input type="hidden" name="_csrf_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="id" value="<?= e($form['id']) ?>">
    <div class="admin-form-row">
        <div>
            <label for="coupon-code">Mã</label>
            <input id="coupon-code" name="code" type="text" maxlength="40" value="<?= e($form['code']) ?>" required>
        </div>
        <div>
            <label for="discount-type">Kiểu giảm</label>
            <select id="discount-type" name="discount_type">
                <option value="percent" <?= $form['discount_type'] === 'percent' ? 'selected' : '' ?>>Phần trăm</option>
                <option value="fixed" <?= $form['discount_type'] === 'fixed' ? 'selected' : '' ?>>Số tiền (VND)</option>
            </select>
        </div>
        <div>
            <label for="discount-value">Mức giảm</label>
            <input id="discount-value" name="discount_value" type="number" min="1" step="1" value="<?= e($form['discount_value']) ?>" required>
        </div>
        <div>
            <label for="min-order">Đơn tối thiểu (VND)</label>
            <input id="min-order" name="min_order_vnd" type="number" min="0" step="1" value="<?= e($form['min_order_vnd']) ?>" required>
        </div>
    </div>
    <div class="admin-form-row">
        <div>
            <label for="starts-at">Bắt đầu</label>
            <input id="starts-at" name="starts_at" type="datetime-local" value="<?= e($form['starts_at']) ?>" required>
        </div>
        <div>
            <label for="ends-at">Kết thúc</label>
            <input id="ends-at" name="ends_at" type="datetime-local" value="<?= e($form['ends_at']) ?>" required>
        </div>
        <div>
            <label for="max-uses">Giới hạn lượt dùng (để trống nếu không giới hạn)</label>
            <input id="max-uses" name="max_uses" type="number" min="1" step="1" value="<?= e($form['max_uses']) ?>">
        </div>
    </div>
    <label class="admin-checkbox-label">
        <input type="checkbox" name="allow_stackable" value="1" <?= $form['allow_stackable'] ? 'checked' : '' ?>>
        Cho phép kết hợp khuyến mãi khác
    </label>
    <label class="admin-checkbox-label">
        <input type="checkbox" name="is_active" value="1" <?= $form['is_active'] ? 'checked' : '' ?>>
        Đang kích hoạt
    </label>
    <div class="admin-form-actions">
        <button class="button" type="submit">Lưu mã giảm giá</button>
        <?php if ($form['id'] !== ''): ?><a class="reset-link" href="/admin/coupons">Hủy sửa</a><?php endif; ?>
    </div>
</form>

<section class="admin-content-card">
    <h2>Mã giảm giá hiện có</h2>
    <?php if ($coupons === []): ?>
        <p>Chưa có mã giảm giá.</p>
    <?php else: ?>
        <div class="orders-table-wrap">
            <table class="orders-table admin-table">
                <thead>
                    <tr>
                        <th>Mã</th>
                        <th>Mức giảm</th>
                        <th>Thời gian</th>
                        <th>Đơn tối thiểu</th>
                        <th>Lượt dùng</th>
                        <th>Trạng thái</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($coupons as $coupon): ?>
                        <tr>
                            <td><strong><?= e($coupon['code']) ?></strong></td>
                            <td>
                                <?= $coupon['discount_type'] === 'percent'
                                    ? e($coupon['discount_value']) . '%'
                                    : e(format_vnd((int) $coupon['discount_value'])) ?>
                            </td>
                            <td><?= e($coupon['starts_at']) ?><br>đến <?= e($coupon['ends_at']) ?></td>
                            <td><?= e(format_vnd((int) $coupon['min_order_vnd'])) ?></td>
                            <td><?= e($coupon['used_count']) ?> / <?= $coupon['max_uses'] === null ? '∞' : e($coupon['max_uses']) ?></td>
                            <td>
                                <?= (int) $coupon['is_active'] === 1 ? 'Đang bật' : 'Đã tạm ngưng' ?>
                                <?= strtotime((string) $coupon['ends_at']) < time() ? ' · Hết hạn' : '' ?>
                            </td>
                            <td>
                                <a href="/admin/coupons?edit=<?= e($coupon['id']) ?>">Sửa</a>
                                <form method="post" action="/admin/coupons/<?= e($coupon['id']) ?>/toggle">
                                    <input type="hidden" name="_csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="is_active" value="<?= (int) $coupon['is_active'] === 1 ? '0' : '1' ?>">
                                    <button class="text-button" type="submit">
                                        <?= (int) $coupon['is_active'] === 1 ? 'Tạm ngưng' : 'Kích hoạt' ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
