<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

class Controller
{
    protected function redirect(string $location): never
    {
        header('Location: ' . $location, true, 303);
        exit;
    }

    protected function render(string $view, array $data = []): void
    {
        $viewsDirectory = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Views';
        $viewPath = $viewsDirectory . DIRECTORY_SEPARATOR . $view . '.php';
        $layoutPath = $viewsDirectory . DIRECTORY_SEPARATOR . 'layout.php';

        if (!is_file($viewPath) || !is_file($layoutPath)) {
            throw new RuntimeException('The requested view or layout does not exist.');
        }

        extract($data, EXTR_SKIP);

        ob_start();
        require $viewPath;
        $content = (string) ob_get_clean();

        require $layoutPath;
    }
}
