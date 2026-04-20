<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Cache;
use App\Core\Container;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\Http\ResponseFactory;
use App\Core\Session;
use App\Core\View;
use App\Exceptions\DatabaseException;
use App\Exceptions\ValidationException;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use App\Services\Contracts\EmailServiceInterface;
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
    private EmailServiceInterface $emailService;
    private AuditLogRepositoryInterface $auditLogRepo;
    private ResponseFactory $response;

    public function __construct(
        ?SettingsServiceInterface $settingsService = null,
        ?EmailServiceInterface $emailService = null,
        ?AuditLogRepositoryInterface $auditLogRepo = null,
        ?ResponseFactory $response = null
    ) {
        $this->settingsService = $settingsService ?? Container::make(SettingsServiceInterface::class);
        $this->emailService = $emailService ?? Container::make(EmailServiceInterface::class);
        $this->auditLogRepo = $auditLogRepo ?? Container::make(AuditLogRepositoryInterface::class);
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
     * POST /admin/cache/clear
     * Limpiar caché del sistema
     */
    public function clearCache(): ResponseInterface
    {
        Cache::flush();
        Flash::success('Caché limpiada correctamente.');

        return $this->response->redirect('/admin/settings');
    }

    /**
     * GET /admin/settings/data (AJAX)
     * Obtiene los datos de configuración
     * @throws JsonException
     */
    public function getSettingsData(): ResponseInterface
    {
        $settings = $this->settingsService->getAll();

        return $this->response->json(['ok' => true, 'data' => ['settings' => $settings]]);
    }

    /**
     * POST /admin/settings/group/{group}
     * Actualiza múltiples configuraciones de un grupo
     * @param string $group
     * @throws JsonException
     * @throws RandomException
     * @throws ValidationException
     * @throws DatabaseException
     */
    public function updateSettingsGroup(string $group): ResponseInterface
    {
        if (!Csrf::validate()) {
            throw ValidationException::withMessage('Token de seguridad inválido', 419);
        }

        $input = \json_decode((string) \file_get_contents('php://input'), true);
        $settings = $input['settings'] ?? [];

        if (empty($settings)) {
            throw ValidationException::withMessage('No se proporcionaron configuraciones', 422);
        }

        $updated = $this->settingsService->updateBulk($settings, $group, Session::userId());

        return $this->response->json(['ok' => true, 'data' => [
            'message' => "Se actualizaron $updated configuraciones",
            'updated' => $updated,
        ]]);
    }

    /**
     * POST /admin/settings/{key}
     * Actualiza una configuración individual
     * @param string $key
     * @throws DatabaseException
     * @throws JsonException
     * @throws RandomException
     * @throws ValidationException
     */
    public function updateSetting(string $key): ResponseInterface
    {
        if (!Csrf::validate()) {
            throw ValidationException::withMessage('Token de seguridad inválido', 419);
        }

        $input = \json_decode((string) \file_get_contents('php://input'), true);
        $value = $input['value'] ?? null;

        if ($value === null) {
            throw ValidationException::withMessage('Valor requerido', 422);
        }

        $this->settingsService->update($key, $value, Session::userId());

        return $this->response->json(['ok' => true, 'data' => ['message' => 'Configuración actualizada correctamente']]);
    }

    /**
     * POST /admin/settings/test-email
     * Envía un email de prueba para verificar configuración SMTP
     * @throws JsonException
     * @throws RandomException
     * @throws ValidationException
     */
    public function testEmail(): ResponseInterface
    {
        if (!Csrf::validate()) {
            throw ValidationException::withMessage('Token de seguridad inválido', 419);
        }

        if (!$this->settingsService->isSmtpEnabled()) {
            throw ValidationException::withMessage('El envío de emails está desactivado', 400);
        }

        $user = Session::get('user');
        $adminEmail = $user['email'] ?? null;
        $adminName = $user['name'] ?? 'Administrador';

        if (!$adminEmail) {
            throw ValidationException::withMessage('No se pudo obtener el email del usuario', 400);
        }

        $sent = $this->emailService->sendTestEmail($adminEmail, $adminName);

        if (!$sent) {
            throw ValidationException::withMessage('Error al enviar el email. Revisa la configuración SMTP y los logs del servidor.', 500);
        }

        $this->auditLogRepo->log(
            'test_email',
            'setting',
            null,
            null,
            ['recipient' => $adminEmail, 'success' => true],
            Session::userId()
        );

        return $this->response->json(['ok' => true, 'data' => [
            'message' => "Email de prueba enviado exitosamente a $adminEmail",
            'recipient' => $adminEmail,
        ]]);
    }
}
