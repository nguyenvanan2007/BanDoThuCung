<section class="home-banner" aria-label="TPet - cửa hàng thức ăn cho thú cưng">
    <img
        src="/assets/images/tpet-home-banner.svg"
        alt="TPet - Bữa ngon mỗi ngày, bé vui khỏe dài lâu. Chọn thức ăn yêu thích cho người bạn bốn chân."
        width="1440"
        height="440"
    >
</section>

<section class="page-heading" id="catalog">
    <h1>Thức ăn cho chó và mèo</h1>
    <p class="page-description">
        <?= $isDemoMode
            ? 'Tìm kiếm và lọc các sản phẩm minh họa theo nhu cầu.'
            : 'Tìm kiếm và lọc thức ăn cho chó mèo theo nhu cầu.' ?>
    </p>
    <?php if ($isDemoMode): ?>
        <div class="demo-notice" role="note">
            <strong>Lưu ý:</strong>
            Tên sản phẩm, mô tả, giá và hình minh họa đều là dữ liệu mẫu, không phải hàng hóa thật.
        </div>
    <?php endif; ?>
</section>

<section class="catalog-layout" aria-label="Danh mục sản phẩm">
    <form class="filter-panel" method="get" action="/">
        <h2>Lọc sản phẩm</h2>
        <label for="search">Tìm theo tên hoặc mã sản phẩm</label>
        <input id="search" name="q" type="search" value="<?= e($filters['q']) ?>" placeholder="Ví dụ: thức ăn khô">

        <label for="species">Dành cho</label>
        <select id="species" name="species">
            <option value="">Chó và mèo</option>
            <option value="dog" <?= $filters['species'] === 'dog' ? 'selected' : '' ?>>Chó</option>
            <option value="cat" <?= $filters['species'] === 'cat' ? 'selected' : '' ?>>Mèo</option>
        </select>

        <label for="category">Loại thức ăn</label>
        <select id="category" name="category">
            <option value="">Tất cả loại</option>
            <option value="dry" <?= $filters['category'] === 'dry' ? 'selected' : '' ?>>Thức ăn khô</option>
            <option value="wet" <?= $filters['category'] === 'wet' ? 'selected' : '' ?>>Thức ăn ướt</option>
        </select>

        <label for="price"><?= $isDemoMode ? 'Khoảng giá minh họa' : 'Khoảng giá' ?></label>
        <select id="price" name="price">
            <option value="">Tất cả mức giá</option>
            <option value="under_100k" <?= $filters['priceRange'] === 'under_100k' ? 'selected' : '' ?>>Dưới 100.000đ</option>
            <option value="100k_200k" <?= $filters['priceRange'] === '100k_200k' ? 'selected' : '' ?>>100.000đ – 200.000đ</option>
            <option value="over_200k" <?= $filters['priceRange'] === 'over_200k' ? 'selected' : '' ?>>Trên 200.000đ</option>
        </select>

        <label for="sort">Sắp xếp</label>
        <select id="sort" name="sort">
            <option value="">Tên sản phẩm</option>
            <option value="price_asc" <?= $filters['sort'] === 'price_asc' ? 'selected' : '' ?>>Giá thấp đến cao</option>
            <option value="price_desc" <?= $filters['sort'] === 'price_desc' ? 'selected' : '' ?>>Giá cao đến thấp</option>
        </select>

        <button class="button filter-button" type="submit">Áp dụng bộ lọc</button>
        <a class="reset-link" href="/">Xóa bộ lọc</a>
    </form>

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
                    <a class="product-card" href="/products/<?= e($product['id']) ?>">
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
                            <h2><?= e($product['name']) ?></h2>
                            <div class="product-card-footer">
                                <strong><?= $product['isDemo'] ? 'Giá mẫu: ' : 'Giá: ' ?><?= e(format_vnd($product['price'])) ?></strong>
                                <span>Xem chi tiết&nbsp;→</span>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
