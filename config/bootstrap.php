<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
require $projectRoot . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

Dotenv\Dotenv::createImmutable($projectRoot)->safeLoad();
