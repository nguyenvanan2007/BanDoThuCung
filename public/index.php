<?php

declare(strict_types=1);

use App\Controllers\HomeController;
use App\Controllers\NotFoundController;
use App\Controllers\ProductController;
use App\Controllers\AuthController;
use App\Controllers\CartController;
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
        (new HomeController())->index();
        exit;
    }

    if ($route === '/products') {
        (new ProductController(ProductCatalogFactory::create()))->index();
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
