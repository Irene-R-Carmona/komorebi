<?php

/**
 * ¿Qué pruebas aquí?
 * Contrato de Public/LoyaltyController: instanciación y que card() redirige a /login
 * cuando no hay sesión iniciada.
 *
 * ¿Qué me quieres demostrar?
 * Que la guardia de autenticación en card() funciona: sin user_id en sesión
 * el controlador devuelve un redirect 302 a /login.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se elimina la guardia de autenticación en card() o se cambia la ruta de redirección.
 */

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Public;

use App\Core\Http\ResponseFactory;
use App\Http\Controllers\Public\LoyaltyController;
use App\Services\Contracts\LoyaltyServiceInterface;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LoyaltyController::class)]
final class LoyaltyControllerTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    private function makeController(): LoyaltyController
    {
        return new LoyaltyController(
            loyaltyService: $this->createStub(LoyaltyServiceInterface::class),
            response: new ResponseFactory(),
        );
    }

    public function test_class_exists(): void
    {
        $this->assertTrue(\class_exists(LoyaltyController::class));
    }

    public function test_expected_methods_exist(): void
    {
        $this->assertTrue(\method_exists(LoyaltyController::class, 'card'));
    }

    public function test_can_be_instantiated(): void
    {
        $this->assertInstanceOf(LoyaltyController::class, $this->makeController());
    }

    public function test_card_redirects_to_login_when_not_authenticated(): void
    {
        // Sin user_id en sesión → debe redirigir a /login
        $result = $this->makeController()->card(
            new ServerRequest('GET', '/loyalty/card')
        );

        $this->assertNotNull($result);
        $this->assertSame(302, $result->getStatusCode());
        $this->assertStringContainsString('/login', $result->getHeaderLine('Location'));
    }
}
