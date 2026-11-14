<?php
$formAction = $isEditing ? '/admin/products/' . rawurlencode((string) $product['id']) : '/admin/products';
$selectedImage = is_string($product['imagePath'] ?? null) ? $product['imagePath'] : '';
?>
<section class="page-heading">
    <p class="eyebrow"><a href="/admin">Quản trị</a> / <a href="/admin/products">Sản phẩm</a></p>
    <h1><?= $isEditing ? 'Sửa sản phẩm' : 'Thêm sản phẩm' ?></h1>
    <p class="page-description">Chỉ chọn ảnh có sẵn trong thư mục ảnh sản phẩm của dự án.</p>
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

    <form class="admin-product-form" method="post" action="<?= e($formAction) ?>">
        <input type="hidden" name="_csrf_token" value="<?= e(csrf_token()) ?>">

        <label for="sku">Mã sản phẩm</label>
        <input id="sku" name="sku" type="text" maxlength="64" pattern="[A-Za-z0-9_\-]+" value="<?= e($product['sku']) ?>" required>

        <label for="name">Tên sản phẩm</label>
        <input id="name" name="name" type="text" maxlength="180" value="<?= e($product['name']) ?>" required>

        <div class="admin-form-row">
            <div>
                <label for="species">Dành cho</label>
                <select id="species" name="species" required>
                    <option value="dog" <?= $product['species'] === 'dog' ? 'selected' : '' ?>>Chó</option>
                    <option value="cat" <?= $product['species'] === 'cat' ? 'selected' : '' ?>>Mèo</option>
                </select>
            </div>
            <div>
                <label for="food_type">Loại thức ăn</label>
                <select id="food_type" name="food_type" required>
                    <option value="dry" <?= $product['foodType'] === 'dry' ? 'selected' : '' ?>>Khô</option>
                    <option value="wet" <?= $product['foodType'] === 'wet' ? 'selected' : '' ?>>Ướt</option>
                </select>
            </div>
        </div>

        <label for="description">Mô tả</label>
        <textarea id="description" name="description" rows="4" maxlength="5000"><?= e($product['description']) ?></textarea>

        <label for="image_path">Ảnh sản phẩm</label>
        <select id="image_path" name="image_path">
            <option value="">Không dùng ảnh</option>
            <?php foreach ($images as $image): ?>
                <option value="<?= e($image) ?>" <?= $selectedImage === $image ? 'selected' : '' ?>><?= e(basename($image)) ?></option>
            <?php endforeach; ?>
        </select>
        <?php if ($selectedImage !== ''): ?>
            <img class="admin-image-preview" src="<?= e($selectedImage) ?>" alt="Ảnh xem trước sản phẩm">
        <?php endif; ?>

        <div class="admin-form-row">
            <div>
                <label for="price_vnd">Giá bán (đồng)</label>
                <input id="price_vnd" name="price_vnd" type="number" min="1" step="1" value="<?= e($product['priceVnd']) ?>" required>
            </div>
            <div>
                <label for="stock_quantity">Tồn kho</label>
                <input id="stock_quantity" name="stock_quantity" type="number" min="0" max="1000000000" step="1" value="<?= e($product['stockQuantity']) ?>" required>
            </div>
        </div>

        <label class="admin-checkbox-label">
            <input name="is_active" type="checkbox" value="1" <?= $product['isActive'] ? 'checked' : '' ?>>
            Đang hiển thị và cho phép bán
        </label>

        <div class="admin-form-actions">
            <button class="button" type="submit">Lưu sản phẩm</button>
            <a class="reset-link" href="/admin/products">Hủy</a>
        </div>
    </form>
</section>
