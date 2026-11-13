<section class="auth-panel">
    <p class="eyebrow">Pet House / Tài khoản</p>
    <h1>Đăng nhập</h1>
    <p class="page-description">Đăng nhập để quản lý tài khoản và sử dụng các chức năng mua hàng khi được triển khai.</p>

    <?php if ($errors !== []): ?>
        <div class="form-errors" role="alert">
            <?php foreach ($errors as $error): ?>
                <p><?= e($error) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form class="auth-form" method="post" action="/login">
        <input type="hidden" name="_csrf_token" value="<?= e(csrf_token()) ?>">
        <label for="email">Email</label>
        <input id="email" name="email" type="email" autocomplete="email" maxlength="190" required value="<?= e($email) ?>">

        <label for="password">Mật khẩu</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>

        <button class="button" type="submit">Đăng nhập</button>
    </form>

    <p class="auth-alternative">Chưa có tài khoản? <a href="/register">Tạo tài khoản</a></p>
</section>
