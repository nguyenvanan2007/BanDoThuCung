<section class="page-heading admin-page-heading">
    <div>
        <p class="eyebrow"><a href="/admin">Quản trị</a> / Tài khoản</p>
        <h1>Quản lý tài khoản</h1>
        <p class="page-description">Tạo tài khoản và quản lý tài khoản khách hàng đã đăng ký.</p>
    </div>
</section>

<?php if ($errors !== []): ?>
    <div class="form-errors" role="alert">
        <ul>
            <?php foreach ($errors as $error): ?>
                <li><?= e($error) ?></li>
            <?php endforeach ?>
        </ul>
    </div>
<?php endif ?>

<section class="admin-user-layout">
    <article class="admin-content-card admin-user-create">
        <h2>Tạo tài khoản mới</h2>
        <form class="admin-product-form" method="post" action="/admin/users">
            <input type="hidden" name="_csrf_token" value="<?= e(csrf_token()) ?>">
            <label>
                Họ và tên
                <input name="name" type="text" required minlength="2" maxlength="100">
            </label>
            <label>
                Email
                <input name="email" type="email" required maxlength="190">
            </label>
            <label>
                Mật khẩu
                <input name="password" type="password" required minlength="6" maxlength="255">
            </label>
            <label>
                Vai trò
                <select name="role">
                    <option value="customer">Khách hàng</option>
                    <option value="admin">Admin</option>
                </select>
            </label>
            <button class="button" type="submit">Tạo tài khoản</button>
        </form>
    </article>

    <article class="admin-content-card admin-user-list">
        <h2>Tài khoản đã đăng ký</h2>
        <?php if ($users === []): ?>
            <p>Chưa có tài khoản nào.</p>
        <?php else: ?>
            <table class="orders-table admin-table">
                <thead>
                    <tr>
                        <th>Họ tên</th>
                        <th>Email</th>
                        <th>Vai trò</th>
                        <th>Trạng thái</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $account): ?>
                        <?php $isAdmin = (string) $account['role'] === 'admin'; ?>
                        <tr>
                            <td><?= e($account['name']) ?></td>
                            <td><?= e($account['email']) ?></td>
                            <td><?= $isAdmin ? 'Admin' : 'Khách hàng' ?></td>
                            <td>
                                <?php if ($isAdmin): ?>
                                    <span class="status-badge status-completed">Admin bảo vệ</span>
                                <?php elseif ((int) $account['is_active'] === 1): ?>
                                    <span class="status-badge status-completed">Đang hoạt động</span>
                                <?php else: ?>
                                    <span class="status-badge status-cancelled">Đã khóa</span>
                                <?php endif ?>
                            </td>
                            <td>
                                <?php if (!$isAdmin): ?>
                                    <form class="admin-user-action" method="post" action="/admin/users/<?= e($account['id']) ?>/status">
                                        <input type="hidden" name="_csrf_token" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="is_active" value="<?= (int) $account['is_active'] === 1 ? '0' : '1' ?>">
                                        <button class="link-button" type="submit">
                                            <?= (int) $account['is_active'] === 1 ? 'Khóa' : 'Mở khóa' ?>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="admin-protected-label">Không thể thao tác</span>
                                <?php endif ?>
                            </td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        <?php endif ?>
    </article>
</section>
