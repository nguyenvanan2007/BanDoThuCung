<section class="page-heading admin-page-heading">
    <div>
        <p class="eyebrow"><a href="/admin">Quản trị</a> / Kho</p>
        <h1>Quản lý tồn kho</h1>
        <p class="page-description">Nhập hàng hoặc điều chỉnh số lượng; mọi thay đổi được ghi lịch sử.</p>
    </div>
</section>

<?php if ($errors !== []): ?>
    <div class="form-errors" role="alert">
        <?php foreach ($errors as $error): ?><p><?= e($error) ?></p><?php endforeach; ?>
    </div>
<?php endif; ?>

<section class="admin-content-card">
    <h2>Tồn kho hiện tại</h2>
    <?php if ($products === []): ?>
        <p>Chưa có sản phẩm.</p>
    <?php else: ?>
        <div class="orders-table-wrap admin-inventory-table-wrap">
            <table class="orders-table admin-table admin-inventory-table admin-inventory-current-table">
                <thead>
                    <tr>
                        <th>Sản phẩm / SKU</th>
                        <th>Tồn kho</th>
                        <th>Cảnh báo</th>
                        <th>Nhập / điều chỉnh</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product): ?>
                        <?php $label = $product['productName']
                            . ($product['variantName'] === null ? '' : ' · ' . $product['variantName']); ?>
                        <tr>
                            <td>
                                <div class="admin-inventory-product">
                                    <?php if ($product['imagePath'] !== null): ?>
                                        <img class="admin-product-thumb" src="<?= e($product['imagePath']) ?>" alt="" loading="lazy" decoding="async">
                                    <?php else: ?>
                                        <span class="admin-inventory-no-image">Chưa có ảnh</span>
                                    <?php endif; ?>
                                    <div class="admin-inventory-product-details">
                                        <strong><?= e($label) ?></strong>
                                        <small><?= e($product['variantSku'] ?? $product['productSku']) ?></small>
                                    </div>
                                </div>
                            </td>
                            <td><?= e($product['stockQuantity']) ?></td>
                            <td>
                                <?php if ($product['stockQuantity'] === 0): ?>
                                    <strong>Hết hàng</strong>
                                <?php elseif ($product['stockQuantity'] <= $product['lowStockThreshold']): ?>
                                    <strong>Sắp hết (≤ <?= e($product['lowStockThreshold']) ?>)</strong>
                                <?php else: ?>
                                    Bình thường
                                <?php endif; ?>
                            </td>
                            <td>
                                <form class="admin-inventory-form" method="post" action="/admin/inventory">
                                    <input type="hidden" name="_csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="product_id" value="<?= e($product['productId']) ?>">
                                    <input type="hidden" name="variant_id" value="<?= e($product['variantId'] ?? '') ?>">
                                    <label>
                                        Kiểu
                                        <select name="movement_type">
                                            <option value="restock">Nhập hàng</option>
                                            <option value="adjustment">Điều chỉnh</option>
                                        </select>
                                    </label>
                                    <label>
                                        Thay đổi (+/-)
                                        <input name="quantity_delta" type="number" min="-1000000000" max="1000000000" required>
                                    </label>
                                    <label>
                                        Lý do
                                        <input name="reason" type="text" maxlength="255" required>
                                    </label>
                                    <button class="button secondary-button" type="submit">Lưu tồn kho</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="admin-content-card">
    <h2>Lịch sử nhập / xuất / điều chỉnh</h2>
    <?php if ($movements === []): ?>
        <p>Chưa có thay đổi tồn kho.</p>
    <?php else: ?>
        <div class="orders-table-wrap admin-inventory-table-wrap">
            <table class="orders-table admin-table admin-inventory-table admin-inventory-history-table">
                <thead>
                    <tr>
                        <th>Thời gian</th>
                        <th>Sản phẩm</th>
                        <th>Loại</th>
                        <th>Thay đổi</th>
                        <th>Tồn trước → sau</th>
                        <th>Lý do / đơn hàng</th>
                        <th>Người thực hiện</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($movements as $movement): ?>
                        <tr>
                            <td><?= e($movement['created_at']) ?></td>
                            <td>
                                <?= e($movement['product_name']) ?>
                                <?= $movement['variant_name'] !== null ? ' · ' . e($movement['variant_name']) : '' ?>
                            </td>
                            <td><?= e(match ($movement['movement_type']) {
                                'restock' => 'Nhập hàng',
                                'adjustment' => 'Điều chỉnh',
                                'order' => 'Đặt hàng',
                                'cancellation' => 'Hủy / hoàn kho',
                                default => 'Khác',
                            }) ?></td>
                            <td><?= e($movement['quantity_delta']) ?></td>
                            <td><?= e($movement['quantity_before']) ?> → <?= e($movement['quantity_after']) ?></td>
                            <td>
                                <?= e($movement['reason']) ?>
                                <?= $movement['order_number'] !== null ? ' · ' . e($movement['order_number']) : '' ?>
                            </td>
                            <td><?= e($movement['changed_by_name'] ?? 'Hệ thống') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
