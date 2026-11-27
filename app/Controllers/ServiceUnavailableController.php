<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

final class ServiceUnavailableController extends Controller
{
    public function show(): void
    {
        http_response_code(503);
        $this->render('errors/service-unavailable', [
            'title' => 'Dịch vụ tạm thời không khả dụng | TPet',
        ]);
    }
}
