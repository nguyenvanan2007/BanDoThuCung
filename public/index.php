<?php

declare(strict_types=1);

use App\Controllers\NotFoundController;
use App\Controllers\ProductController;
use App\Controllers\AuthController;
use App\Controllers\Admin\AdminController;
use App\Controllers\CartController;
use App\Controllers\CheckoutController;
use App\Controllers\OrderController;
use App\Controllers\PaymentController;
use App\Controllers\ServiceUnavailableController;
use App\Models\ProductCatalogFactory;

$projectRoot = dirname(__DIR__);
require $projectRoot
    . DIRECTORY_SEPARATOR . 'config'
    . DIRECTORY_SEPARATOR . 'bootstrap.php';

try {
    $requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $requestPath = is_string($requestPath) ? $requestPath : '/';

    $applicationUrl = $_ENV['APP_URL'] ?? '';
    $basePath = parse_url(is_string($applicationUrl) ? $applicationUrl : '', PHP_URL_PATH);
    $basePath = is_string($basePath) ? rtrim($basePath, '/') : '';

    if ($basePath !== ''
        && ($requestPath === $basePath || str_starts_with($requestPath, $basePath . '/'))) {
        $requestPath = substr($requestPath, strlen($basePath));
    }

    $route = '/' . trim($requestPath, '/');
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

    if ($route === '/admin') {
        if ($method === 'GET') {
            (new AdminController())->dashboard();
            exit;
        }

        http_response_code(405);
        header('Allow: GET');
        exit;
    }

    if ($route === '/admin/users') {
        $adminController = new AdminController();
        if ($method === 'GET') {
            $adminController->users();
            exit;
        }
        if ($method === 'POST') {
            $adminController->createUser();
            exit;
        }
        http_response_code(405);
        header('Allow: GET, POST');
        exit;
    }

    if (preg_match('~\A/admin/users/([1-9][0-9]*)/status\z~', $route, $matches) === 1) {
        if ($method === 'POST') {
            (new AdminController())->updateUserStatus($matches[1]);
            exit;
        }
        http_response_code(405);
        header('Allow: POST');
        exit;
    }

    if ($route === '/admin/products') {
        $adminController = new AdminController();
        if ($method === 'GET') {
            $adminController->products();
            exit;
        }
        if ($method === 'POST') {
            $adminController->createProduct();
            exit;
        }

        http_response_code(405);
        header('Allow: GET, POST');
        exit;
    }

    if ($route === '/admin/categories') {
        $adminController = new AdminController();
        if ($method === 'GET') {
            $adminController->categories();
            exit;
        }
        if ($method === 'POST') {
            $adminController->createCategory();
            exit;
        }

        http_response_code(405);
        header('Allow: GET, POST');
        exit;
    }

    if (preg_match('~\A/admin/categories/([1-9][0-9]*)/toggle\z~', $route, $matches) === 1) {
        if ($method === 'POST') {
            (new AdminController())->toggleCategory($matches[1]);
            exit;
        }

        http_response_code(405);
        header('Allow: POST');
        exit;
    }

    if ($route === '/admin/products/new') {
        if ($method === 'GET') {
            (new AdminController())->newProduct();
            exit;
        }

        http_response_code(405);
        header('Allow: GET');
        exit;
    }

    if (preg_match('~\A/admin/products/([1-9][0-9]*)/delete\z~', $route, $matches) === 1) {
        if ($method === 'POST') {
            (new AdminController())->deleteProduct($matches[1]);
            exit;
        }

        http_response_code(405);
        header('Allow: POST');
        exit;
    }

    if (preg_match('~\A/admin/products/([1-9][0-9]*)\z~', $route, $matches) === 1) {
        $adminController = new AdminController();
        if ($method === 'GET') {
            $adminController->editProduct($matches[1]);
            exit;
        }
        if ($method === 'POST') {
            $adminController->updateProduct($matches[1]);
            exit;
        }

        http_response_code(405);
        header('Allow: GET, POST');
        exit;
    }

    if ($route === '/admin/orders') {
        if ($method === 'GET') {
            (new AdminController())->orders();
            exit;
        }

        http_response_code(405);
        header('Allow: GET');
        exit;
    }

    if (preg_match('~\A/admin/orders/([1-9][0-9]*)/cod-paid\z~', $route, $matches) === 1) {
        if ($method === 'POST') {
            (new AdminController())->recordCodPayment($matches[1]);
            exit;
        }
        http_response_code(405);
        header('Allow: POST');
        exit;
    }

    if (preg_match('~\A/admin/orders/([1-9][0-9]*)/refund\z~', $route, $matches) === 1) {
        if ($method === 'POST') {
            (new AdminController())->recordRefund($matches[1]);
            exit;
        }
        http_response_code(405);
        header('Allow: POST');
        exit;
    }

    if (preg_match('~\A/admin/orders/([1-9][0-9]*)/status\z~', $route, $matches) === 1) {
        if ($method === 'POST') {
            (new AdminController())->updateOrderStatus($matches[1]);
            exit;
        }

        http_response_code(405);
        header('Allow: POST');
        exit;
    }

    if (preg_match('~\A/admin/orders/([1-9][0-9]*)\z~', $route, $matches) === 1) {
        if ($method === 'GET') {
            (new AdminController())->showOrder($matches[1]);
            exit;
        }

        http_response_code(405);
        header('Allow: GET');
        exit;
    }

    if ($route === '/admin/inventory') {
        $adminController = new AdminController();
        if ($method === 'GET') {
            $adminController->inventory();
            exit;
        }
        if ($method === 'POST') {
            $adminController->adjustInventory();
            exit;
        }
        http_response_code(405);
        header('Allow: GET, POST');
        exit;
    }

    if ($route === '/admin/coupons') {
        $adminController = new AdminController();
        if ($method === 'GET') {
            $adminController->coupons();
            exit;
        }
        if ($method === 'POST') {
            $adminController->saveCoupon();
            exit;
        }
        http_response_code(405);
        header('Allow: GET, POST');
        exit;
    }

    if (preg_match('~\A/admin/coupons/([1-9][0-9]*)/toggle\z~', $route, $matches) === 1) {
        if ($method === 'POST') {
            (new AdminController())->toggleCoupon($matches[1]);
            exit;
        }
        http_response_code(405);
        header('Allow: POST');
        exit;
    }

    if ($route === '/admin/reports') {
        if ($method === 'GET') {
            (new AdminController())->reports();
            exit;
        }
        http_response_code(405);
        header('Allow: GET');
        exit;
    }

    if (str_starts_with($route, '/admin/')) {
        (new NotFoundController())->show();
        exit;
    }

    if ($route === '/payment/vnpay/return' && $method === 'GET') {
        (new PaymentController())->vnpayReturn();
        exit;
    }

    if ($route === '/payment/vnpay/ipn' && $method === 'GET') {
        (new PaymentController())->vnpayIpn();
        exit;
    }

    if ($route === '/checkout') {
        if (!in_array($method, ['GET', 'POST'], true)) {
            http_response_code(405);
            header('Allow: GET, POST');
            echo '<!doctype html><html lang="vi"><meta charset="utf-8"><title>Phương thức không được hỗ trợ</title>'
                . '<h1>Phương thức không được hỗ trợ</h1></html>';
            exit;
        }

        if (!isset($_SESSION['user']) || !is_array($_SESSION['user'])
            || ($_SESSION['user']['role'] ?? '') !== 'customer') {
            $_SESSION['_flash'] = [
                'type' => 'error',
                'message' => 'Vui lòng đăng nhập tài khoản khách hàng để thanh toán.',
            ];
            header('Location: /login', true, 303);
            exit;
        }

        $checkoutController = new CheckoutController(ProductCatalogFactory::create());

        if ($method === 'GET') {
            $checkoutController->show();
            exit;
        }

        if ($method === 'POST') {
            $checkoutController->submit();
            exit;
        }

    }

    if ($route === '/orders' && $method === 'GET') {
        (new OrderController())->index();
        exit;
    }

    if (preg_match('~\A/orders/([1-9][0-9]*)/cancel\z~', $route, $matches) === 1) {
        if ($method === 'POST') {
            (new OrderController())->cancel($matches[1]);
            exit;
        }

        http_response_code(405);
        header('Allow: POST');
        exit;
    }

    if (preg_match('~\A/orders/([1-9][0-9]*)\z~', $route, $matches) === 1 && $method === 'GET') {
        (new OrderController())->show($matches[1]);
        exit;
    }

    if (in_array($route, ['/cart', '/cart/add', '/cart/update', '/cart/remove'], true)) {
        $cartController = new CartController(ProductCatalogFactory::create());

        if ($route === '/cart' && $method === 'GET') {
            $cartController->index();
            exit;
        }

        if ($route === '/cart/add' && $method === 'POST') {
            $cartController->add();
            exit;
        }

        if ($route === '/cart/update' && $method === 'POST') {
            $cartController->update();
            exit;
        }

        if ($route === '/cart/remove' && $method === 'POST') {
            $cartController->remove();
            exit;
        }

        http_response_code(405);
        header('Allow: ' . ($route === '/cart' ? 'GET' : 'POST'));
        echo '<!doctype html><html lang="vi"><meta charset="utf-8"><title>Phương thức không được hỗ trợ</title>'
            . '<h1>Phương thức không được hỗ trợ</h1></html>';
        exit;
    }

    if (in_array($route, ['/login', '/register', '/logout', '/account'], true)) {
        $authController = new AuthController();

        if ($route === '/login' && $method === 'GET') {
            $authController->loginForm();
            exit;
        }

        if ($route === '/login' && $method === 'POST') {
            $authController->login();
            exit;
        }

        if ($route === '/register' && $method === 'GET') {
            $authController->registerForm();
            exit;
        }

        if ($route === '/register' && $method === 'POST') {
            $authController->register();
            exit;
        }

        if ($route === '/logout' && $method === 'POST') {
            $authController->logout();
            exit;
        }

        if ($route === '/account' && $method === 'GET') {
            $authController->account();
            exit;
        }

        http_response_code(405);
        header('Allow: ' . ($route === '/logout' ? 'POST' : ($route === '/account' ? 'GET' : 'GET, POST')));
        echo '<!doctype html><html lang="vi"><meta charset="utf-8"><title>Phương thức không được hỗ trợ</title>'
            . '<h1>Phương thức không được hỗ trợ</h1></html>';
        exit;
    }

    if ($route === '/') {
        (new ProductController(ProductCatalogFactory::create()))->index();
        exit;
    }

    if ($route === '/products') {
        $query = http_build_query($_GET);
        header('Location: /' . ($query === '' ? '' : '?' . $query), true, 302);
        exit;
    }

    if (preg_match('~\A/products/([1-9][0-9]*)\z~', $route, $matches) === 1) {
        (new ProductController(ProductCatalogFactory::create()))->show($matches[1]);
        exit;
    }

    (new NotFoundController())->show();
} catch (\PDOException $exception) {
    $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());
    $driverCode = (string) ($exception->errorInfo[1] ?? 'unknown');
    error_log(sprintf('Database request failed (SQLSTATE %s, driver code %s).', $sqlState, $driverCode));

    (new ServiceUnavailableController())->show();
}
