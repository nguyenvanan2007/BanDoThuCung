<section class="page-heading">
    <p class="eyebrow"><a href="/products">Sản phẩm</a> / Chi tiết sản phẩm</p>
    <?php if ($product['isDemo']): ?>
        <div class="demo-notice" role="note">
            Sản phẩm, thông tin và giá trên trang này chỉ là dữ liệu minh họa, không dùng để đặt hàng.
        </div>
    <?php endif; ?>
</section>

<article class="product-detail">
    <div class="product-detail-art" aria-hidden="true">
        <span><?= e($product['icon']) ?></span>
        <small>Biểu tượng minh họa</small>
    </div>
    <div class="product-detail-copy">
        <span class="product-label"><?= e($product['speciesLabel']) ?> · <?= e($product['categoryLabel']) ?></span>
        <h1><?= e($product['name']) ?></h1>
        <?php if ($product['isDemo']): ?>
            <p class="detail-price">Giá mẫu: <?= e(format_vnd($product['price'])) ?></p>
        <?php else: ?>
            <p class="detail-price">Giá: <?= e(format_vnd($product['price'])) ?></p>
        <?php endif; ?>
        <p>Mã sản phẩm: <?= e($product['sku']) ?></p>
        <?php if ($product['description'] !== ''): ?>
            <p><?= e($product['description']) ?></p>
        <?php endif; ?>
        <?php if ($product['stockQuantity'] > 0): ?>
            <form class="add-to-cart-form" method="post" action="/cart/add">
                <input type="hidden" name="_csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="product_id" value="<?= e($product['id']) ?>">
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
                <button class="button" type="submit">Thêm vào giỏ hàng</button>
            </form>
        <?php else: ?>
            <p class="stock-warning">Sản phẩm hiện đã hết hàng.</p>
        <?php endif; ?>
        <a class="button" href="/products">Quay lại danh sách sản phẩm</a>
    </div>
</article>
