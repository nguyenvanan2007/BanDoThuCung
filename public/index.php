<?php

declare(strict_types=1);

use App\Controllers\HomeController;

$projectRoot = dirname(__DIR__);
require $projectRoot
    . DIRECTORY_SEPARATOR . 'config'
    . DIRECTORY_SEPARATOR . 'bootstrap.php';

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$requestPath = is_string($requestPath) ? $requestPath : '/';

$applicationUrl = $_ENV['APP_URL'] ?? '';
$basePath = parse_url(is_string($applicationUrl) ? $applicationUrl : '', PHP_URL_PATH);
$basePath = is_string($basePath) ? rtrim($basePath, '/') : '';

if ($basePath !== '' && str_starts_with($requestPath, $basePath)) {
    $requestPath = substr($requestPath, strlen($basePath));
}

$route = '/' . trim($requestPath, '/');

if ($route !== '/') {
    http_response_code(404);
    echo '<!doctype html><html lang="vi"><meta charset="utf-8"><title>Không tìm thấy</title>'
        . '<h1>Không tìm thấy trang</h1></html>';
    exit;
}

(new HomeController())->index();
