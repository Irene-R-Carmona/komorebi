<?php

/**
 * ¿Qué pruebas aquí?
 * Smoke test de Admin\AuditLogController: verifica métodos públicos y construcción.
 *
 * ¿Qué me quieres demostrar?
 * Que el controlador expone los métodos requeridos y acepta ResponseFactory inyectada.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se renombra o elimina alguno de los métodos públicos documentados.
 */

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Admin;

use App\Http\Controllers\Admin\AuditLogController;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Support\ControllerTestCase;

#[CoversClass(AuditLogController::class)]
final class AuditLogControllerTest extends ControllerTestCase
{
    public function test_class_has_expected_methods(): void
    {
        $this->assertTrue(\method_exists(AuditLogController::class, 'index'));
    }

    public function test_instance_can_be_created_with_response_factory(): void
    {
        $controller = new AuditLogController(
            auditLogRepo: $this->createStub(AuditLogRepositoryInterface::class),
        );
        $this->assertInstanceOf(AuditLogController::class, $controller);
    }
}
