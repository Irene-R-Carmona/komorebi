<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Http\ResponseFactory;
use App\Core\View;
use App\Services\Contracts\SettingsServiceInterface;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Random\RandomException;

/**
 * Controlador de Configuración del Sistema
 *
 * Responsabilidad única: Gestión de configuraciones del sistema
 */
final class SystemController
{
    private SettingsServiceInterface $settingsService;
    private ResponseFactory $response;

    public function __construct(
        ?SettingsServiceInterface $settingsService = null,
        ?ResponseFactory $response = null
    ) {
        $this->settingsService = $settingsService ?? Container::make(SettingsServiceInterface::class);
        $this->response = $response ?? new ResponseFactory();
    }

    /**
     * GET /admin/settings
     * Vista principal de configuración
     * @throws JsonException
     * @throws RandomException
     */
    public function settings(): ?ResponseInterface
    {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && \strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            return $this->getSettingsData();
        }

        View::render('admin/settings/index', [
            'titulo' => 'Configuración del Sistema',
            'csrf_token' => Csrf::token(),
            'extraJs' => ['admin/admin-settings.js'],
        ], ['admin/admin-settings.css'], 'backoffice');

        return null;
    }

    /**
     * GET /admin/logs
     * Vista de logs del sistema
     * @throws JsonException
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

    /**
     * GET /admin/settings/data (AJAX)
     * Obtiene los datos de configuración
     * @throws JsonException
     */
    private function getSettingsData(): ResponseInterface
    {
        $settings = $this->settingsService->getAll();

        return $this->response->json(['ok' => true, 'data' => ['settings' => $settings]]);
    }
}
