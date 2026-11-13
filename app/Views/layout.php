<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Pet House - cửa hàng thức ăn dành cho chó và mèo.">
    <title><?= e($title ?? 'Pet House') ?></title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
    <header class="site-header">
        <a class="brand" href="/" aria-label="Pet House - Trang chủ">
            <span class="brand-mark" aria-hidden="true">P</span>
            <span>Pet House</span>
        </a>
        <p class="site-tagline">Thức ăn cho chó và mèo</p>
    </header>
    <main>
        <?= $content ?>
    </main>
    <footer class="site-footer">
        <p>Pet House <span aria-hidden="true">&middot;</span> Cửa hàng thức ăn cho thú cưng</p>
    </footer>
</body>
</html>
