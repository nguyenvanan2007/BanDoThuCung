<?php
$formAction = $isEditing ? '/admin/products/' . rawurlencode((string) $product['id']) : '/admin/products';
$selectedImages = is_array($product['images'] ?? null) ? $product['images'] : [];
$variantRows = is_array($product['variants'] ?? null) ? $product['variants'] : [];
if ($variantRows === []) {
    $variantRows[] = ['id' => '', 'sku' => '', 'name' => '', 'priceVnd' => '', 'stockQuantity' => ''];
}
?>
<section class="page-heading">
    <p class="eyebrow"><a href="/admin">Quản trị</a> / <a href="/admin/products">Sản phẩm</a></p>
    <h1><?= $isEditing ? 'Sửa sản phẩm' : 'Thêm sản phẩm' ?></h1>
    <p class="page-description">Chọn tối đa 10 ảnh có sẵn hoặc tải thêm một ảnh JPG, PNG hay WebP (tối đa 5 MB).</p>
</section>

<section class="admin-form-card">
    <?php if ($errors !== []): ?>
        <div class="form-errors" role="alert">
            <strong>Vui lòng kiểm tra lại:</strong>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form class="admin-product-form" method="post" action="<?= e($formAction) ?>" enctype="multipart/form-data">
        <input type="hidden" name="_csrf_token" value="<?= e(csrf_token()) ?>">

        <label for="sku">Mã sản phẩm</label>
        <input id="sku" name="sku" type="text" maxlength="64" pattern="[A-Za-z0-9_\-]+" value="<?= e($product['sku']) ?>" required>

        <label for="name">Tên sản phẩm</label>
        <input id="name" name="name" type="text" maxlength="180" value="<?= e($product['name']) ?>" required>

        <label for="category_id">Danh mục</label>
        <select id="category_id" name="category_id" required>
            <?php foreach ($categories as $category): ?>
                <option
                    value="<?= e($category['id']) ?>"
                    <?= (string) $product['categoryId'] === (string) $category['id'] ? 'selected' : '' ?>
                    <?= !$category['isActive'] ? 'disabled' : '' ?>
                >
                    <?= e($category['name']) ?><?= !$category['isActive'] ? ' (đã ẩn)' : '' ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if ($categories === []): ?>
            <p class="form-hint">Chưa có danh mục hoạt động. <a href="/admin/categories">Tạo danh mục</a> trước khi thêm sản phẩm.</p>
        <?php endif; ?>

        <label for="description">Mô tả</label>
        <textarea id="description" name="description" rows="4" maxlength="5000"><?= e($product['description']) ?></textarea>

        <fieldset class="admin-image-picker">
            <legend>Ảnh sản phẩm (tối đa 10 ảnh)</legend>
            <?php if ($images === []): ?>
                <p class="form-hint">Thư mục ảnh sản phẩm chưa có ảnh. Thêm file vào public/assets/images/products/.</p>
            <?php else: ?>
                <div class="admin-image-grid">
                    <?php foreach ($images as $image): ?>
                        <label class="admin-image-option">
                            <input type="checkbox" name="images[]" value="<?= e($image) ?>" <?= in_array($image, $selectedImages, true) ? 'checked' : '' ?>>
                            <img src="<?= e($image) ?>" alt="" loading="lazy" decoding="async">
                            <span><?= e(basename($image)) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </fieldset>

        <label for="image_file">Tải thêm ảnh từ thiết bị (không bắt buộc)</label>
        <input id="image_file" name="image_file" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
        <small>Ảnh tải lên sẽ trở thành ảnh đại diện và được thêm vào thư viện ảnh của sản phẩm.</small>

        <fieldset class="admin-variants">
            <legend>Biến thể (không bắt buộc)</legend>
            <p class="form-hint">Mỗi biến thể có SKU, tên/khối lượng, giá và tồn kho riêng. Giá và tồn kho sản phẩm sẽ tự tổng hợp từ các biến thể đang hoạt động.</p>
            <div class="variant-rows">
                <?php foreach ($variantRows as $index => $variant): ?>
                    <div class="variant-row">
                        <input type="hidden" name="variants[<?= e($index) ?>][id]" value="<?= e($variant['id'] ?? '') ?>">
                        <label>
                            SKU biến thể
                            <input name="variants[<?= e($index) ?>][sku]" type="text" maxlength="64" value="<?= e($variant['sku'] ?? '') ?>" placeholder="SKU-500G">
                        </label>
                        <label>
                            Tên / quy cách
                            <input name="variants[<?= e($index) ?>][name]" type="text" maxlength="120" value="<?= e($variant['name'] ?? '') ?>" placeholder="500g">
                        </label>
                        <label>
                            Giá (đồng)
                            <input name="variants[<?= e($index) ?>][price_vnd]" type="number" min="1" step="1" value="<?= e($variant['priceVnd'] ?? '') ?>">
                        </label>
                        <label>
                            Tồn kho
                            <input name="variants[<?= e($index) ?>][stock_quantity]" type="number" min="0" max="1000000000" step="1" value="<?= e($variant['stockQuantity'] ?? '') ?>">
                        </label>
                        <button class="text-button remove-variant" type="button">Xóa biến thể</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button class="button secondary-button" id="add-variant" type="button">Thêm biến thể</button>
        </fieldset>

        <div class="admin-form-row">
            <div>
                <label for="price_vnd">Giá bán mặc định (đồng)</label>
                <input id="price_vnd" name="price_vnd" type="number" min="1" step="1" value="<?= e($product['priceVnd']) ?>" required>
            </div>
            <div>
                <label for="stock_quantity">Tồn kho mặc định</label>
                <input id="stock_quantity" name="stock_quantity" type="number" min="0" max="1000000000" step="1" value="<?= e($product['stockQuantity']) ?>" required>
            </div>
        </div>
        <p class="form-hint">Giá và tồn kho mặc định chỉ áp dụng khi sản phẩm không dùng biến thể.</p>

        <label class="admin-checkbox-label">
            <input name="is_active" type="checkbox" value="1" <?= $product['isActive'] ? 'checked' : '' ?>>
            Đang hiển thị và cho phép bán
        </label>

        <div class="admin-form-actions">
            <button class="button" type="submit" <?= $categories === [] ? 'disabled' : '' ?>>Lưu sản phẩm</button>
            <a class="reset-link" href="/admin/products">Hủy</a>
        </div>
    </form>
</section>

<?php if ($isEditing): ?>
    <form
        class="admin-delete-product"
        method="post"
        action="/admin/products/<?= e($product['id']) ?>/delete"
    >
        <input type="hidden" name="_csrf_token" value="<?= e(csrf_token()) ?>">
        <label>
            <input name="confirm_delete" type="checkbox" value="1" required>
            Tôi xác nhận xóa sản phẩm này
        </label>
        <button class="button danger-button" type="submit">Xóa sản phẩm</button>
        <p class="form-hint">Sản phẩm đã có trong đơn hàng sẽ không thể xóa để giữ nguyên lịch sử; hãy ẩn sản phẩm thay thế.</p>
    </form>
<?php endif; ?>

<template id="variant-row-template">
    <div class="variant-row">
        <input type="hidden" data-name="id" value="">
        <label>
            SKU biến thể
            <input data-name="sku" type="text" maxlength="64" placeholder="SKU-500G">
        </label>
        <label>
            Tên / quy cách
            <input data-name="name" type="text" maxlength="120" placeholder="500g">
        </label>
        <label>
            Giá (đồng)
            <input data-name="price_vnd" type="number" min="1" step="1">
        </label>
        <label>
            Tồn kho
            <input data-name="stock_quantity" type="number" min="0" max="1000000000" step="1">
        </label>
        <button class="text-button remove-variant" type="button">Xóa biến thể</button>
    </div>
</template>
<script>
    (() => {
        const rows = document.querySelector('.variant-rows');
        const template = document.getElementById('variant-row-template');
        const addButton = document.getElementById('add-variant');
        let nextIndex = rows.querySelectorAll('.variant-row').length;

        addButton.addEventListener('click', () => {
            const row = template.content.firstElementChild.cloneNode(true);
            row.querySelectorAll('[data-name]').forEach((input) => {
                input.name = `variants[${nextIndex}][${input.dataset.name}]`;
                input.removeAttribute('data-name');
            });
            nextIndex += 1;
            rows.appendChild(row);
        });

        rows.addEventListener('click', (event) => {
            if (event.target.closest('.remove-variant')) {
                event.target.closest('.variant-row').remove();
            }
        });
    })();
</script>
