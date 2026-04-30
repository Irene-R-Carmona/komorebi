<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Core\Cache;
use App\Core\Http\ResponseFactory;
use App\Core\Session;
use App\Exceptions\ValidationException;
use App\Http\Controllers\Api\AbstractApiController;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use App\Services\Contracts\EmailServiceInterface;
use App\Services\Contracts\SettingsServiceInterface;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Random\RandomException;

/**
 * API REST: Configuración del sistema y utilidades (Admin)
 *
 * Rutas:
 * - GET  /api/v1/admin/settings              → getSettingsData()
 * - PUT  /api/v1/admin/settings/{group}      → updateSettingsGroup()
 * - POST /api/v1/admin/settings/test-email   → testEmail()
 * - POST /api/v1/admin/cache/clear           → clearCache()
 */
final class SystemApiController extends AbstractApiController
{
    public function __construct(
        ResponseFactory $response,
        private readonly SettingsServiceInterface $settingsService,
        private readonly EmailServiceInterface $emailService,
        private readonly AuditLogRepositoryInterface $auditLogRepo,
    ) {
        parent::__construct($response);
    }

    /**
     * GET /api/v1/admin/settings → 200
     *
     * @throws JsonException
     */
    public function getSettingsData(): ResponseInterface
    {
        $settings = $this->settingsService->getAll();

        return $this->success(['settings' => $settings]);
    }

    /**
     * PUT /api/v1/admin/settings/{group} → 200
     *
     * @throws JsonException
     * @throws RandomException
     * @throws ValidationException
     */
    public function updateSettingsGroup(ServerRequestInterface $request, string $group): ResponseInterface
    {
        $input = (array) ($request->getParsedBody() ?? []);
        $settings = $input['settings'] ?? [];

        if (empty($settings)) {
            throw ValidationException::withMessage('No se proporcionaron configuraciones', 422);
        }

        $updated = $this->settingsService->updateBulk($settings, $group, Session::userId());

        return $this->success([
            'message' => "Se actualizaron $updated configuraciones",
            'updated' => $updated,
        ]);
    }

    /**
     * POST /api/v1/admin/settings/test-email → 200
     *
     * @throws JsonException
     * @throws RandomException
     * @throws ValidationException
     */
    public function testEmail(): ResponseInterface
    {
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

        return $this->success([
            'message' => "Email de prueba enviado exitosamente a $adminEmail",
            'recipient' => $adminEmail,
        ]);
    }

    /**
     * POST /api/v1/admin/cache/clear → 200
     *
     * @throws JsonException
     */
    public function clearCache(): ResponseInterface
    {
        Cache::flush();

        return $this->success(['message' => 'Caché limpiada correctamente']);
    }
}
