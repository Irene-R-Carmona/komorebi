<?php

/**
 * ¿Qué pruebas aquí?
 * Verifica que Admin\SystemController acepta SettingsService y EmailService inyectados.
 *
 * ¿Qué me quieres demostrar?
 * Que el controlador expone los métodos de configuración del sistema esperados.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se renombran los métodos o las dependencias dejan de ser opcionales.
 */

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Admin;

use App\Core\Http\ResponseFactory;
use App\Http\Controllers\Admin\SystemController;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use App\Services\Contracts\EmailServiceInterface;
use App\Services\Contracts\SettingsServiceInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Support\ControllerTestCase;

#[CoversClass(SystemController::class)]
final class SystemControllerTest extends ControllerTestCase
{
    private function makeController(): SystemController
    {
        return new SystemController(
            settingsService: $this->createStub(SettingsServiceInterface::class),
            emailService: $this->createStub(EmailServiceInterface::class),
            auditLogRepo: $this->createStub(AuditLogRepositoryInterface::class),
            response: new ResponseFactory(),
        );
    }

    public function test_class_has_expected_methods(): void
    {
        $this->assertTrue(\method_exists(SystemController::class, 'settings'));
        $this->assertTrue(\method_exists(SystemController::class, 'clearCache'));
        $this->assertTrue(\method_exists(SystemController::class, 'testEmail'));
    }

    public function test_instance_can_be_created_with_stubs(): void
    {
        $this->assertInstanceOf(SystemController::class, $this->makeController());
    }
}
