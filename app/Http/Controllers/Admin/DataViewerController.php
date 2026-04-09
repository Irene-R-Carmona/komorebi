<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class DataViewerController
{
    public function index(ServerRequestInterface $request): ?ResponseInterface
    {
        View::render('admin/data-viewer', [], [], 'backoffice');
        return null;
    }
}
