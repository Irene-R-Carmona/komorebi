<?php

/**
 * ¿Qué pruebas aquí?
 * Verifica que Api/CookieController cumple el contrato PSR-7:
 * consent() unifica accept/reject/update, clearFilters y clearRecentlyViewed devuelven 204.
 *
 * ¿Qué me quieres demostrar?
 * Que consent() ramifica correctamente según el campo "consent",
 * que los campos faltantes devuelven 422, y que las operaciones DELETE devuelven 204.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si consent() deja de existir, si los status codes de clearFilters/clearRecentlyViewed cambian,
 * o si la validación de consent deja de rechazar valores inválidos.
 */

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Api;

use App\Core\Http\ResponseFactory;
use App\Http\Controllers\Api\V1\CookieController;
use App\Repositories\Contracts\CafeRepositoryInterface;
use App\Services\Contracts\RecentlyViewedServiceInterface;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Message\ResponseInterface;
use Tests\Support\ControllerTestCase;

#[CoversClass(CookieController::class)]
final class CookieControllerTest extends ControllerTestCase
{
    private function makeController(): CookieController
    {
        return new CookieController(
            new ResponseFactory(),
            $this->createStub(RecentlyViewedServiceInterface::class),
            $this->createStub(CafeRepositoryInterface::class),
        );
    }

    public function test_accept_returns_success_json_response(): void
    {
        $result = $this->makeController()->accept(
            new ServerRequest('POST', '/api/cookies/accept')
        );

        $this->assertInstanceOf(ResponseInterface::class, $result);
        $this->assertResponseIsJson($result, 200);

        $body = \json_decode((string) $result->getBody(), true);
        $this->assertTrue($body['ok']);
    }

    public function test_reject_returns_success_json_response(): void
    {
        $result = $this->makeController()->reject(
            new ServerRequest('POST', '/api/cookies/reject')
        );

        $this->assertInstanceOf(ResponseInterface::class, $result);
        $this->assertResponseIsJson($result, 200);

        $body = \json_decode((string) $result->getBody(), true);
        $this->assertTrue($body['ok']);
    }

    public function test_update_returns_422_when_body_not_array(): void
    {
        $request = new ServerRequest('POST', '/api/cookies/update')
            ->withParsedBody(null);

        $result = $this->makeController()->update($request);

        $this->assertInstanceOf(ResponseInterface::class, $result);
        $this->assertSame(422, $result->getStatusCode());
    }

    // --- consent() tests (TDD RED → GREEN) ---

    public function test_consent_returns_422_when_consent_field_missing(): void
    {
        $request = (new ServerRequest('PATCH', '/api/v1/cookies'))
            ->withParsedBody([]);

        $result = $this->makeController()->consent($request);

        $this->assertInstanceOf(ResponseInterface::class, $result);
        $this->assertSame(422, $result->getStatusCode());
    }

    public function test_consent_returns_422_when_invalid_consent_value(): void
    {
        $request = (new ServerRequest('PATCH', '/api/v1/cookies'))
            ->withParsedBody(['consent' => 'invalid']);

        $result = $this->makeController()->consent($request);

        $this->assertInstanceOf(ResponseInterface::class, $result);
        $this->assertSame(422, $result->getStatusCode());
    }

    public function test_consent_all_returns_200(): void
    {
        $request = (new ServerRequest('PATCH', '/api/v1/cookies'))
            ->withParsedBody(['consent' => 'all']);

        $result = $this->makeController()->consent($request);

        $this->assertInstanceOf(ResponseInterface::class, $result);
        $this->assertResponseIsJson($result, 200);
        $body = \json_decode((string) $result->getBody(), true);
        $this->assertTrue($body['ok']);
    }

    public function test_consent_none_returns_200(): void
    {
        $request = (new ServerRequest('PATCH', '/api/v1/cookies'))
            ->withParsedBody(['consent' => 'none']);

        $result = $this->makeController()->consent($request);

        $this->assertInstanceOf(ResponseInterface::class, $result);
        $this->assertResponseIsJson($result, 200);
        $body = \json_decode((string) $result->getBody(), true);
        $this->assertTrue($body['ok']);
    }

    public function test_consent_custom_returns_422_when_preference_fields_missing(): void
    {
        $request = (new ServerRequest('PATCH', '/api/v1/cookies'))
            ->withParsedBody(['consent' => 'custom']);

        $result = $this->makeController()->consent($request);

        $this->assertInstanceOf(ResponseInterface::class, $result);
        $this->assertSame(422, $result->getStatusCode());
    }

    public function test_consent_custom_returns_200_with_valid_preferences(): void
    {
        $request = (new ServerRequest('PATCH', '/api/v1/cookies'))
            ->withParsedBody([
                'consent'    => 'custom',
                'essential'  => true,
                'functional' => false,
                'analytics'  => false,
            ]);

        $result = $this->makeController()->consent($request);

        $this->assertInstanceOf(ResponseInterface::class, $result);
        $this->assertResponseIsJson($result, 200);
        $body = \json_decode((string) $result->getBody(), true);
        $this->assertTrue($body['ok']);
    }

    // --- clearFilters → 204 ---

    public function test_clear_filters_returns_204(): void
    {
        $request = new ServerRequest('DELETE', '/api/v1/cookies/filters');

        $result = $this->makeController()->clearFilters($request);

        $this->assertInstanceOf(ResponseInterface::class, $result);
        $this->assertSame(204, $result->getStatusCode());
    }

    // --- clearRecentlyViewed → 204 ---

    public function test_clear_recently_viewed_returns_204(): void
    {
        $request = new ServerRequest('DELETE', '/api/v1/cookies/recently-viewed');

        $result = $this->makeController()->clearRecentlyViewed($request);

        $this->assertInstanceOf(ResponseInterface::class, $result);
        $this->assertSame(204, $result->getStatusCode());
    }
}
