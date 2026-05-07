<?php

/**
 * ¿Qué pruebas aquí?
 * Verifica el contrato PSR-7 de Api/LoyaltyController.
 *
 * ¿Qué me quieres demostrar?
 * Que redeem() devuelve error de autenticación cuando no hay sesión activa,
 * y que use() también requiere sesión.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se elimina la comprobación de autenticación en redeem() o use().
 */

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Api;

use App\Core\Http\ResponseFactory;
use App\Http\Controllers\Api\V1\LoyaltyController;
use App\Repositories\Contracts\LoyaltyRepositoryInterface;
use App\Services\LoyaltyService;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Support\ControllerTestCase;

#[CoversClass(LoyaltyController::class)]
final class LoyaltyControllerTest extends ControllerTestCase
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
        $service = new LoyaltyService(
            loyaltyRepo: $this->createStub(LoyaltyRepositoryInterface::class),
        );

        return new LoyaltyController(new ResponseFactory(), $service);
    }

    public function test_redeem_returns_error_when_not_authenticated(): void
    {
        $result = $this->makeController()->redeem(
            new ServerRequest('POST', '/api/loyalty/redeem')
        );

        $this->assertSame(401, $result->getStatusCode());
    }

    public function test_use_returns_422_when_code_is_missing(): void
    {
        $result = $this->makeController()->use(
            new ServerRequest('POST', '/api/loyalty/use')
        );

        $this->assertSame(422, $result->getStatusCode());
        $this->assertStringContainsString('application/problem+json', $result->getHeaderLine('Content-Type'));
    }

    public function test_class_has_expected_methods(): void
    {
        $this->assertTrue(\method_exists(LoyaltyController::class, 'redeem'));
        $this->assertTrue(\method_exists(LoyaltyController::class, 'validateCode'));
        $this->assertTrue(\method_exists(LoyaltyController::class, 'use'));
    }
}
