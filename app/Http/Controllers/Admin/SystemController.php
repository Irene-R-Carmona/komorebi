<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Csrf;
use App\Core\View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Random\RandomException;

/**
 * Controlador del Sistema (Admin) — SSR únicamente.
 * Las consultas JSON de settings y mutaciones (updateSettingsGroup, testEmail, clearCache)
 * están en Api\V1\Admin\SystemApiController.
 */
final class SystemController
{
    /**
     * GET /admin/settings
     * @throws RandomException
     */
    public function settings(): ?ResponseInterface
    {
        View::render('admin/settings/index', [
            'titulo' => 'Configuración del Sistema',
            'csrf_token' => Csrf::token(),
            'extraJs' => ['admin/admin-settings.js'],
        ], ['admin/admin-settings.css'], 'backoffice');

        return null;
    }

    /**
     * GET /admin/logs
     * @throws RandomException
     */
    public function logs(ServerRequestInterface $request): ?ResponseInterface
    {
        View::render('admin/logs/index', [
            'titulo' => 'Logs del Sistema',
            'csrf_token' => Csrf::token(),
        ], [], 'backoffice');

        return null;
    }
}
