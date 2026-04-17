<?php

/**
 * ¿Qué pruebas aquí?
 * Verifica que Admin/UserController cumple el contrato PSR-7.
 *
 * ¿Qué me quieres demostrar?
 * Que getUsersList() retorna ResponseInterface con JSON,
 * leyendo datos desde el repositorio inyectado (no desde $_POST ni BD directo).
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si getUsersList() deja de usar $this->userRepo,
 * o si el formato de la respuesta JSON cambia.
 */

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Admin;

use App\Core\Http\ResponseFactory;
use App\Http\Controllers\Admin\UserController;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\UserManagementService;
use Psr\Http\Message\ResponseInterface;
use Tests\Support\ControllerTestCase;

final class UserControllerTest extends ControllerTestCase
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

    public function test_get_users_list_returns_json_response(): void
    {
        $repoStub = $this->createMock(UserRepositoryInterface::class);
        $repoStub->method('getActiveUsersList')->willReturn([
            ['id' => 1, 'name' => 'Ana', 'email' => 'ana@example.com'],
            ['id' => 2, 'name' => 'Juan', 'email' => 'juan@example.com'],
        ]);

        $controller = new UserController(
            userManagementService: new UserManagementService($this->createMock(\PDO::class)),
            userRepo: $repoStub,
            response: new ResponseFactory()
        );

        $result = $controller->getUsersList($this->makeGetRequest('/admin/users/list'));

        $this->assertInstanceOf(ResponseInterface::class, $result);
        $this->assertResponseIsJson($result, 200);

        $body = \json_decode((string) $result->getBody(), true);
        $this->assertTrue($body['ok']);
        $this->assertArrayHasKey('users', $body['data']);
        $this->assertCount(2, $body['data']['users']);
    }

    public function test_get_users_list_returns_empty_array_when_no_users(): void
    {
        $repoStub = $this->createMock(UserRepositoryInterface::class);
        $repoStub->method('getActiveUsersList')->willReturn([]);

        $controller = new UserController(
            userManagementService: new UserManagementService($this->createMock(\PDO::class)),
            userRepo: $repoStub,
            response: new ResponseFactory()
        );

        $result = $controller->getUsersList($this->makeGetRequest('/admin/users/list'));

        $this->assertInstanceOf(ResponseInterface::class, $result);
        $body = \json_decode((string) $result->getBody(), true);
        $this->assertCount(0, $body['data']['users']);
    }

    public function test_class_has_crud_methods(): void
    {
        $this->assertTrue(\method_exists(UserController::class, 'index'));
        $this->assertTrue(\method_exists(UserController::class, 'getUsersList'));
        $this->assertTrue(\method_exists(UserController::class, 'create'));
        $this->assertTrue(\method_exists(UserController::class, 'update'));
        $this->assertTrue(\method_exists(UserController::class, 'delete'));
        $this->assertTrue(\method_exists(UserController::class, 'toggleActive'));
    }
}
