<?php

/**
 * ¿Qué pruebas aquí?
 * Verifica que Api\V1\SupervisorController delega a SupervisorAssignmentService.
 *
 * ¿Qué me quieres demostrar?
 * Que assign() retorna 403 a roles sin permisos y delega al servicio cuando el rol es correcto.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se amplía o restringe la guard de roles en assign().
 */

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Api\V1;

use App\Core\Http\ResponseFactory;
use App\Core\Result;
use App\Http\Controllers\Api\V1\SupervisorController;
use App\Services\Contracts\SupervisorAssignmentServiceInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Support\ControllerTestCase;

#[CoversClass(SupervisorController::class)]
final class SupervisorControllerTest extends ControllerTestCase
{
    protected function setUp(): void
    {
        if (\session_status() === \PHP_SESSION_NONE) {
            \session_start();
        }
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    private function makeController(?SupervisorAssignmentServiceInterface $service = null): SupervisorController
    {
        return new SupervisorController(
            new ResponseFactory(),
            $service ?? $this->createMock(SupervisorAssignmentServiceInterface::class)
        );
    }

    public function test_assign_returns_403_for_unprivileged_role(): void
    {
        $this->asUser(userId: 1, role: 'user');

        $response = $this->makeController()->assign(
            $this->makePostRequest('/api/v1/supervisor/assign', [])
        );

        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_assign_returns_200_when_service_succeeds(): void
    {
        $this->asUser(userId: 1, role: 'supervisor');

        $service = $this->createMock(SupervisorAssignmentServiceInterface::class);
        $service->method('createFromRequest')->willReturn(Result::ok(['id' => 1, 'table_code' => 'A1']));

        $response = $this->makeController($service)->assign(
            $this->makePostRequest('/api/v1/supervisor/assign', [])
        );

        $this->assertSame(200, $response->getStatusCode());
        $body = \json_decode((string) $response->getBody(), true);
        $this->assertTrue($body['ok']);
    }

    public function test_assign_returns_422_when_service_fails_with_validation_error(): void
    {
        $this->asUser(userId: 1, role: 'supervisor');

        $service = $this->createMock(SupervisorAssignmentServiceInterface::class);
        $service->method('createFromRequest')->willReturn(Result::fail('Datos inválidos', 'validation_error'));

        $response = $this->makeController($service)->assign(
            $this->makePostRequest('/api/v1/supervisor/assign', [])
        );

        $this->assertSame(422, $response->getStatusCode());
    }

    public function test_class_has_expected_methods(): void
    {
        $this->assertTrue(\method_exists(SupervisorController::class, 'assign'));
        $this->assertTrue(\method_exists(SupervisorController::class, 'list'));
    }
}
