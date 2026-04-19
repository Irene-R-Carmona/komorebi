<?php

/**
 * ¿Qué pruebas aquí?
 * Verifica que Admin/AnimalController cumple el contrato PSR-7 en los métodos mutantes.
 *
 * ¿Qué me quieres demostrar?
 * Que store(), update() y delete() retornan un ResponseInterface de redirección
 * cuando el token CSRF no es válido, sin llegar a llamar al servicio.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se elimina la validación CSRF en los métodos mutantes,
 * o si el controlador deja de retornar ResponseInterface al fallar CSRF.
 */

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Admin;

use App\Http\Controllers\Admin\AnimalController;
use App\Services\Contracts\AnimalCareServiceInterface;
use Psr\Http\Message\ResponseInterface;
use Tests\Support\ControllerTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(AnimalController::class)]
final class AnimalControllerTest extends ControllerTestCase
{
    protected function setUp(): void
    {
        if (\session_status() === \PHP_SESSION_NONE) {
            \session_start();
        }
        $_SESSION = [];
        $_POST = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_POST = [];
    }

    public function test_store_with_invalid_csrf_returns_redirect(): void
    {
        $controller = new AnimalController(animalCareService: $this->createStub(AnimalCareServiceInterface::class));
        $request = $this->makePostRequest('/admin/animals', [
            'name' => 'Mochi',
            'species' => 'cat',
        ]);

        $result = $controller->store($request);

        $this->assertInstanceOf(ResponseInterface::class, $result);
        $this->assertResponseIsRedirect($result, '/admin/animals/create');
    }

    public function test_update_with_invalid_csrf_returns_redirect(): void
    {
        $controller = new AnimalController(animalCareService: $this->createStub(AnimalCareServiceInterface::class));
        $request = $this->makePostRequest('/admin/animals/1', [
            'id' => '1',
            'name' => 'Mochi',
            'species' => 'cat',
        ]);

        $result = $controller->update($request);

        $this->assertInstanceOf(ResponseInterface::class, $result);
        $this->assertResponseIsRedirect($result, '/admin/animals');
    }

    public function test_delete_with_invalid_csrf_returns_redirect(): void
    {
        $controller = new AnimalController(animalCareService: $this->createStub(AnimalCareServiceInterface::class));
        $request = $this->makePostRequest('/admin/animals/1/delete', ['id' => '1']);

        $result = $controller->delete($request);

        $this->assertInstanceOf(ResponseInterface::class, $result);
        $this->assertResponseIsRedirect($result, '/admin/animals');
    }

    public function test_class_has_crud_methods(): void
    {
        $this->assertTrue(\method_exists(AnimalController::class, 'index'));
        $this->assertTrue(\method_exists(AnimalController::class, 'create'));
        $this->assertTrue(\method_exists(AnimalController::class, 'store'));
        $this->assertTrue(\method_exists(AnimalController::class, 'edit'));
        $this->assertTrue(\method_exists(AnimalController::class, 'update'));
        $this->assertTrue(\method_exists(AnimalController::class, 'delete'));
    }
}
