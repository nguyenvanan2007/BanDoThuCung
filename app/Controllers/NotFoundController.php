<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

final class NotFoundController extends Controller
{
    public function show(): void
    {
        http_response_code(404);
        $this->render('not-found', [
            'title' => 'Không tìm thấy trang | TPet',
        ]);
    }
}
