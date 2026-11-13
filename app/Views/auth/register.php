<section class="auth-panel">
    <p class="eyebrow">Pet House / Tài khoản</p>
    <h1>Tạo tài khoản khách hàng</h1>
    <p class="page-description">Đăng ký để chuẩn bị sử dụng các chức năng mua hàng trong những bước tiếp theo.</p>

    <?php if ($errors !== []): ?>
        <div class="form-errors" role="alert">
            <?php foreach ($errors as $error): ?>
                <p><?= e($error) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form class="auth-form" method="post" action="/register">
        <input type="hidden" name="_csrf_token" value="<?= e(csrf_token()) ?>">
        <label for="name">Họ và tên</label>
        <input id="name" name="name" type="text" autocomplete="name" minlength="2" maxlength="100" required value="<?= e($name) ?>">

        <label for="email">Email</label>
        <input id="email" name="email" type="email" autocomplete="email" maxlength="190" required value="<?= e($email) ?>">

        <label for="password">Mật khẩu (ít nhất 10 ký tự)</label>
        <input id="password" name="password" type="password" autocomplete="new-password" minlength="10" maxlength="72" required>

        <label for="password_confirmation">Nhập lại mật khẩu</label>
        <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="10" maxlength="72" required>

        <button class="button" type="submit">Tạo tài khoản</button>
    </form>

    <p class="auth-alternative">Đã có tài khoản? <a href="/login">Đăng nhập</a></p>
</section>
