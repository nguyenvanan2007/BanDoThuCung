<section class="home-banner" aria-label="TPet - cửa hàng thức ăn cho thú cưng">
    <img
        src="/assets/images/banners/tpet-home-banner.svg"
        alt="TPet - Bữa ngon mỗi ngày, bé vui khỏe dài lâu. Chọn thức ăn yêu thích cho người bạn bốn chân."
        width="1440"
        height="440"
    >
</section>

<section class="catalog-search" aria-label="Tìm kiếm và lọc sản phẩm">
    <form class="catalog-search-form" method="get" action="/">
        <label class="catalog-filter-field catalog-filter-search" for="catalog-search">
            <span>Tìm sản phẩm</span>
            <input
                id="catalog-search"
                name="q"
                type="search"
                value="<?= e($filters['q']) ?>"
                placeholder="Tên hoặc mã sản phẩm"
            >
        </label>

        <label class="catalog-filter-field" for="catalog-species">
            <span>Dành cho</span>
            <select id="catalog-species" name="species">
                <option value="">Chó và mèo</option>
                <option value="dog" <?= $filters['species'] === 'dog' ? 'selected' : '' ?>>Chó</option>
                <option value="cat" <?= $filters['species'] === 'cat' ? 'selected' : '' ?>>Mèo</option>
            </select>
        </label>

        <label class="catalog-filter-field" for="catalog-category">
            <span>Danh mục</span>
            <select id="catalog-category" name="category">
                <option value="">Tất cả loại</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= e($category['slug']) ?>" <?= $filters['category'] === $category['slug'] ? 'selected' : '' ?>>
                        <?= e($category['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label class="catalog-filter-field" for="catalog-price">
            <span><?= $isDemoMode ? 'Khoảng giá minh họa' : 'Khoảng giá' ?></span>
            <select id="catalog-price" name="price">
                <option value="">Tất cả mức giá</option>
                <option value="under_100k" <?= $filters['priceRange'] === 'under_100k' ? 'selected' : '' ?>>Dưới 100.000đ</option>
                <option value="100k_200k" <?= $filters['priceRange'] === '100k_200k' ? 'selected' : '' ?>>100.000đ – 200.000đ</option>
                <option value="over_200k" <?= $filters['priceRange'] === 'over_200k' ? 'selected' : '' ?>>Trên 200.000đ</option>
            </select>
        </label>

        <label class="catalog-filter-field" for="catalog-sort">
            <span>Sắp xếp</span>
            <select id="catalog-sort" name="sort">
                <option value="">Tên sản phẩm</option>
                <option value="price_asc" <?= $filters['sort'] === 'price_asc' ? 'selected' : '' ?>>Giá thấp đến cao</option>
                <option value="price_desc" <?= $filters['sort'] === 'price_desc' ? 'selected' : '' ?>>Giá cao đến thấp</option>
            </select>
        </label>

        <div class="catalog-filter-actions">
            <button class="button catalog-search-button" type="submit">Áp dụng lọc</button>
            <a class="catalog-reset-link" href="/">Xóa lọc</a>
        </div>
    </form>
</section>

<section class="page-heading" id="catalog">
    <h1>Thức ăn cho chó và mèo</h1>
</section>

<section class="shop-categories" aria-labelledby="shop-categories-title">
    <div class="shop-categories-heading">
        <h2 id="shop-categories-title">Chọn theo thú cưng</h2>
        <a href="#catalog">Xem tất cả sản phẩm</a>
    </div>
    <div class="shop-category-grid">
        <a class="shop-category-card" href="/?species=dog#catalog">
            <span class="shop-category-icon" aria-hidden="true">🐶</span>
            <span class="shop-category-copy">
                <strong>Thức ăn cho chó</strong>
                <small>Khám phá sản phẩm dành cho chó</small>
            </span>
            <span class="shop-category-arrow" aria-hidden="true">→</span>
        </a>
        <a class="shop-category-card" href="/?species=cat#catalog">
            <span class="shop-category-icon" aria-hidden="true">🐱</span>
            <span class="shop-category-copy">
                <strong>Thức ăn cho mèo</strong>
                <small>Khám phá sản phẩm dành cho mèo</small>
            </span>
            <span class="shop-category-arrow" aria-hidden="true">→</span>
        </a>
    </div>
</section>

<section class="catalog-layout" aria-label="Danh mục sản phẩm">
    <div class="catalog-results">
        <p class="result-count">
            <?= e($productCount) ?> <?= $isDemoMode ? 'sản phẩm minh họa' : 'sản phẩm' ?>
        </p>
        <?php if ($products === []): ?>
            <div class="empty-state">
                <h2>Không tìm thấy sản phẩm phù hợp</h2>
                <p>Thử đổi từ khóa hoặc xóa bớt bộ lọc.</p>
                <a class="button" href="/">
                    <?= $isDemoMode ? 'Xem tất cả sản phẩm mẫu' : 'Xem tất cả sản phẩm' ?>
                </a>
            </div>
        <?php else: ?>
            <div class="product-grid">
                <?php foreach ($products as $product): ?>
                    <?php
                    $cartItemsForProduct = $_SESSION['cart_items'] ?? [];
                    $quantityInCart = is_array($cartItemsForProduct)
                        ? ($cartItemsForProduct[$product['id']] ?? 0)
                        : 0;
                    $quantityInCart = is_int($quantityInCart) && $quantityInCart > 0 ? $quantityInCart : 0;
                    $remainingCartStock = max(0, $product['stockQuantity'] - $quantityInCart);
                    ?>
                    <article class="product-card">
                        <a class="product-card-link" href="/products/<?= e($product['id']) ?>">
                            <div class="product-art">
                                <?php if ($product['imagePath'] !== null): ?>
                                    <img src="<?= e($product['imagePath']) ?>" alt="<?= e($product['name']) ?>" loading="lazy" decoding="async">
                                <?php else: ?>
                                    <span aria-hidden="true"><?= e($product['icon']) ?></span>
                                    <small>Chưa có ảnh sản phẩm</small>
                                <?php endif; ?>
                            </div>
                            <div class="product-card-body">
                                <span class="product-label">
                                    <?= e($product['speciesLabel']) ?> · <?= e($product['categoryLabel']) ?>
                                    <?= $product['isDemo'] ? ' · DEMO' : '' ?>
                                </span>
                                <?php if (!$product['isDemo']): ?>
                                    <span class="product-stock-status <?= $product['stockQuantity'] > 0 ? 'in-stock' : 'out-of-stock' ?>">
                                        <?= $product['stockQuantity'] > 0 ? 'Còn hàng' : 'Hết hàng' ?>
                                    </span>
                                <?php endif; ?>
                                <h2><?= e($product['name']) ?></h2>
                                <div class="product-card-footer">
                                    <strong>
                                        <?= $product['isDemo'] ? 'Giá mẫu: ' : ($product['hasVariants'] ? 'Giá từ: ' : 'Giá: ') ?>
                                        <?= e(format_vnd($product['price'])) ?>
                                    </strong>
                                    <span>Xem chi tiết&nbsp;→</span>
                                </div>
                            </div>
                        </a>
                        <?php if (!$product['isDemo'] && $remainingCartStock > 0 && !$product['hasVariants']): ?>
                            <form class="product-buy-form" method="post" action="/cart/add">
                                <input type="hidden" name="_csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="product_id" value="<?= e($product['id']) ?>">
                                <input type="hidden" name="quantity" value="1">
                                <button
                                    class="button product-buy-button"
                                    type="submit"
                                    data-quick-cart-trigger
                                    data-product-id="<?= e($product['id']) ?>"
                                    data-product-name="<?= e($product['name']) ?>"
                                    data-product-image="<?= e($product['imagePath'] ?? '') ?>"
                                    data-product-icon="<?= e($product['icon']) ?>"
                                    data-product-price="<?= e(format_vnd($product['price'])) ?>"
                                    data-product-stock="<?= e($remainingCartStock) ?>"
                                >
                                    <svg class="cart-button-icon" viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M3 4h2l2.2 11.1a2 2 0 0 0 2 1.6h8.8a2 2 0 0 0 1.9-1.5L22 8H6" />
                                        <circle cx="10" cy="20" r="1" />
                                        <circle cx="18" cy="20" r="1" />
                                    </svg>
                                    Thêm vào giỏ
                                </button>
                            </form>
                        <?php elseif (!$product['isDemo'] && $product['stockQuantity'] > 0 && !$product['hasVariants']): ?>
                            <button class="button product-buy-button" type="button" disabled>Đã thêm đủ số lượng tồn kho</button>
                        <?php elseif (!$product['isDemo'] && $product['stockQuantity'] > 0 && $product['hasVariants']): ?>
                            <a class="button product-buy-button" href="/products/<?= e($product['id']) ?>">Chọn quy cách</a>
                        <?php elseif (!$product['isDemo']): ?>
                            <button class="button product-buy-button" type="button" disabled>Hết hàng</button>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
