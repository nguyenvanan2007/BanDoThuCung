<section class="page-heading admin-page-heading">
    <div>
        <p class="eyebrow"><a href="/admin">Quản trị</a> / Sản phẩm</p>
        <h1>Quản lý sản phẩm</h1>
        <p class="page-description">Ảnh được chọn từ các file trong thư mục ảnh sản phẩm của dự án.</p>
    </div>
    <a class="button" href="/admin/products/new">Thêm sản phẩm</a>
</section>

<section class="admin-content-card">
    <?php if ($products === []): ?>
        <div class="empty-state">
            <h2>Chưa có sản phẩm thật</h2>
            <p>Sản phẩm DEMO không xuất hiện trong quản trị và không thể đặt hàng.</p>
            <a class="button" href="/admin/products/new">Thêm sản phẩm đầu tiên</a>
        </div>
    <?php else: ?>
        <div class="orders-table-wrap">
            <table class="orders-table admin-table">
                <thead>
                    <tr>
                        <th>Ảnh</th>
                        <th>Sản phẩm</th>
                        <th>Loại</th>
                        <th>Giá</th>
                        <th>Tồn kho</th>
                        <th>Trạng thái</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product): ?>
                        <tr>
                            <td>
                                <?php if ($product['imagePath'] !== null): ?>
                                    <img class="admin-product-thumb" src="<?= e($product['imagePath']) ?>" alt="" loading="lazy" decoding="async">
                                <?php else: ?>
                                    <span class="admin-no-image">Chưa có ảnh</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?= e($product['name']) ?></strong>
                                <small><?= e($product['sku']) ?> · <?= e($product['speciesLabel']) ?></small>
                            </td>
                            <td><?= e($product['foodTypeLabel']) ?></td>
                            <td><?= e(format_vnd($product['priceVnd'])) ?></td>
                            <td><?= e($product['stockQuantity']) ?></td>
                            <td><?= $product['isActive'] ? 'Đang bán' : 'Đã ẩn' ?></td>
                            <td><a href="/admin/products/<?= e($product['id']) ?>">Sửa</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
