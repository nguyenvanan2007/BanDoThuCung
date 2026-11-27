<section class="page-heading admin-page-heading">
    <div>
        <p class="eyebrow"><a href="/admin">Quản trị</a> / Danh mục</p>
        <h1>Quản lý danh mục</h1>
        <p class="page-description">Phân nhóm sản phẩm theo thú cưng và loại thức ăn.</p>
    </div>
    <a class="button" href="/admin/products">Quản lý sản phẩm</a>
</section>

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

<section class="admin-category-layout">
    <form class="admin-content-card admin-category-form" method="post" action="/admin/categories">
        <h2>Thêm danh mục</h2>
        <input type="hidden" name="_csrf_token" value="<?= e(csrf_token()) ?>">
        <label for="category-name">Tên danh mục</label>
        <input id="category-name" name="name" type="text" maxlength="120" required value="<?= e($categoryName) ?>">

        <label for="category-species">Dành cho</label>
        <select id="category-species" name="species" required>
            <option value="dog" <?= $categorySpecies === 'dog' ? 'selected' : '' ?>>Chó</option>
            <option value="cat" <?= $categorySpecies === 'cat' ? 'selected' : '' ?>>Mèo</option>
        </select>

        <label for="category-food-type">Loại thức ăn</label>
        <select id="category-food-type" name="food_type" required>
            <option value="dry" <?= $categoryFoodType === 'dry' ? 'selected' : '' ?>>Thức ăn khô</option>
            <option value="wet" <?= $categoryFoodType === 'wet' ? 'selected' : '' ?>>Thức ăn ướt</option>
        </select>
        <button class="button" type="submit">Thêm danh mục</button>
    </form>

    <section class="admin-content-card">
        <h2>Danh mục hiện có</h2>
        <?php if ($categories === []): ?>
            <p>Chưa có danh mục nào.</p>
        <?php else: ?>
            <div class="orders-table-wrap">
                <table class="orders-table admin-table">
                    <thead>
                        <tr>
                            <th>Danh mục</th>
                            <th>Thú cưng</th>
                            <th>Loại thức ăn</th>
                            <th>Trạng thái</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $category): ?>
                            <tr>
                                <td><strong><?= e($category['name']) ?></strong></td>
                                <td><?= $category['species'] === 'dog' ? 'Chó' : 'Mèo' ?></td>
                                <td><?= $category['foodType'] === 'dry' ? 'Khô' : 'Ướt' ?></td>
                                <td><?= $category['isActive'] ? 'Đang hiển thị' : 'Đã ẩn' ?></td>
                                <td>
                                    <form method="post" action="/admin/categories/<?= e($category['id']) ?>/toggle">
                                        <input type="hidden" name="_csrf_token" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="is_active" value="<?= $category['isActive'] ? '0' : '1' ?>">
                                        <button class="text-button" type="submit">
                                            <?= $category['isActive'] ? 'Ẩn' : 'Hiển thị' ?>
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
</section>
