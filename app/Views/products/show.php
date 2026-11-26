<section class="page-heading">
    <p class="eyebrow"><a href="/">Trang chủ</a> / Chi tiết sản phẩm</p>
</section>

<article class="product-detail">
    <div class="product-detail-art">
        <?php if ($product['images'] !== []): ?>
            <div class="product-gallery">
                <img class="product-gallery-main" src="<?= e($product['images'][0]) ?>" alt="<?= e($product['name']) ?>">
                <?php if (count($product['images']) > 1): ?>
                    <div class="product-gallery-thumbnails" aria-label="Các ảnh sản phẩm">
                        <?php foreach ($product['images'] as $image): ?>
                            <button class="product-gallery-thumbnail" type="button" data-image="<?= e($image) ?>" aria-label="Xem ảnh sản phẩm">
                                <img src="<?= e($image) ?>" alt="" loading="lazy" decoding="async">
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <span aria-hidden="true"><?= e($product['icon']) ?></span>
            <small>Chưa có ảnh sản phẩm</small>
        <?php endif; ?>
    </div>
    <div class="product-detail-copy">
        <span class="product-label"><?= e($product['categoryLabel']) ?></span>
        <?php if (!$product['isDemo']): ?>
            <span class="product-stock-status <?= $product['stockQuantity'] > 0 ? 'in-stock' : 'out-of-stock' ?>">
                <?= $product['stockQuantity'] > 0 ? 'Còn hàng' : 'Hết hàng' ?>
            </span>
        <?php endif; ?>
        <h1><?= e($product['name']) ?></h1>
        <p class="detail-price" id="detail-price">
            <?= $product['isDemo'] ? 'Giá mẫu: ' : ($product['hasVariants'] ? 'Giá từ: ' : 'Giá: ') ?>
            <?= e(format_vnd($product['price'])) ?>
        </p>
        <p>Mã sản phẩm: <?= e($product['sku']) ?></p>
        <?php if ($product['stockQuantity'] > 0): ?>
            <form class="add-to-cart-form" method="post" action="/cart/add">
                <input type="hidden" name="_csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="product_id" value="<?= e($product['id']) ?>">
                <?php if ($product['variants'] !== []): ?>
                    <label for="product-variant">Quy cách</label>
                    <select id="product-variant" name="variant_id" required>
                        <?php foreach ($product['variants'] as $index => $variant): ?>
                            <option
                                value="<?= e($variant['id']) ?>"
                                data-price="<?= e($variant['price']) ?>"
                                data-stock="<?= e($variant['stockQuantity']) ?>"
                                <?= $variant['stockQuantity'] < 1 ? 'disabled' : '' ?>
                                <?= $variant['stockQuantity'] > 0 && !array_filter(array_slice($product['variants'], 0, $index), static fn (array $previous): bool => $previous['stockQuantity'] > 0) ? 'selected' : '' ?>
                            >
                                <?= e($variant['name']) ?> — <?= e(format_vnd($variant['price'])) ?>
                                <?= $variant['stockQuantity'] > 0 ? '(Còn ' . e($variant['stockQuantity']) . ')' : '(Hết hàng)' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
                <label for="product-quantity">Số lượng</label>
                <input
                    id="product-quantity"
                    name="quantity"
                    type="number"
                    min="1"
                    max="<?= e($product['stockQuantity']) ?>"
                    value="1"
                    required
                >
                <button class="button cart-action-button" type="submit">
                    <svg class="cart-button-icon" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M3 4h2l2.2 11.1a2 2 0 0 0 2 1.6h8.8a2 2 0 0 0 1.9-1.5L22 8H6" />
                        <circle cx="10" cy="20" r="1" />
                        <circle cx="18" cy="20" r="1" />
                    </svg>
                    Thêm vào giỏ hàng
                </button>
            </form>
        <?php else: ?>
            <p class="stock-warning">Sản phẩm hiện đã hết hàng.</p>
        <?php endif; ?>
        <a class="button" href="/">Quay lại danh sách sản phẩm</a>
    </div>
</article>
<?php if ($product['description'] !== ''): ?>
    <section class="product-description" aria-labelledby="product-description-title">
        <h2 id="product-description-title">Mô tả sản phẩm</h2>
        <p><?= e($product['description']) ?></p>
    </section>
<?php endif; ?>
<?php if (count($product['images']) > 1 || $product['variants'] !== []): ?>
    <script>
        (() => {
            const mainImage = document.querySelector('.product-gallery-main');
            document.querySelectorAll('.product-gallery-thumbnail').forEach((button) => {
                button.addEventListener('click', () => {
                    mainImage.src = button.dataset.image;
                });
            });

            const variantSelect = document.getElementById('product-variant');
            if (variantSelect) {
                const price = document.getElementById('detail-price');
                const quantity = document.getElementById('product-quantity');
                const updateVariant = () => {
                    const selected = variantSelect.selectedOptions[0];
                    if (!selected) {
                        return;
                    }
                    price.textContent = `Giá: ${new Intl.NumberFormat('vi-VN').format(Number(selected.dataset.price))} ₫`;
                    quantity.max = selected.dataset.stock;
                };
                variantSelect.addEventListener('change', updateVariant);
                updateVariant();
            }
        })();
    </script>
<?php endif; ?>
