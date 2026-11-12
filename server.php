<?php

declare(strict_types=1);

$publicRoot = realpath(__DIR__ . '/public');
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$requestedFile = $publicRoot === false
    ? false
    : realpath($publicRoot . DIRECTORY_SEPARATOR . ltrim((string) $path, '/\\'));

if ($requestedFile !== false
    && str_starts_with($requestedFile, $publicRoot . DIRECTORY_SEPARATOR)
    && is_file($requestedFile)
    && basename($requestedFile) !== 'index.php') {
    return false;
}

require __DIR__ . '/public/index.php';
