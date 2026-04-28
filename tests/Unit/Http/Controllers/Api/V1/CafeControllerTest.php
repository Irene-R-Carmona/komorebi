<?php

/**
 * ¿Qué pruebas aquí?
 * Verifica que Api\V1\CafeController cumple el contrato PSR-7 de la API pública.
 *
 * ¿Qué me quieres demostrar?
 * Que index() devuelve 200 con lista de cafés, respeta ETag con 304,
 * y que show() retorna 200/404/422 según el slug y la existencia del café.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se elimina ETag/Cache-Control, si cambial validación de slug,
 * o si cambia la estructura de respuesta de collection().
 */

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Api\V1;

use App\Core\Http\ResponseFactory;
use App\Http\Controllers\Api\V1\CafeController;
use App\Http\Transformers\CafeTransformer;
use App\Repositories\Contracts\CafeRepositoryInterface;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Support\ControllerTestCase;

#[CoversClass(CafeController::class)]
final class CafeControllerTest extends ControllerTestCase
{
    private function makeController(?CafeRepositoryInterface $cafeRepo = null): CafeController
    {
        $repo = $cafeRepo ?? $this->createStub(CafeRepositoryInterface::class);

        return new CafeController(new ResponseFactory(), $repo, new CafeTransformer());
    }

    // ─────────────────────────────────────────────────────────────
    // index()
    // ─────────────────────────────────────────────────────────────

    public function test_index_returns_200_with_items_and_cache_headers(): void
    {
        $cafeRepo = $this->createStub(CafeRepositoryInterface::class);
        $cafeRepo->method('findActive')->willReturn([
            ['id' => 1, 'slug' => 'neko-cat', 'name' => 'Neko Cat Café', 'is_active' => true],
        ]);

        $response = $this->makeController($cafeRepo)->index(
            $this->makeGetRequest('/api/v1/cafes')
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotEmpty($response->getHeaderLine('ETag'));
        $this->assertStringContainsString('public', $response->getHeaderLine('Cache-Control'));
        $this->assertStringContainsString('max-age=3600', $response->getHeaderLine('Cache-Control'));

        $body = \json_decode((string) $response->getBody(), true);
        $this->assertTrue($body['ok']);
        $this->assertArrayHasKey('items', $body['data']);
    }

    public function test_index_returns_304_when_etag_matches(): void
    {
        $cafes = [
            ['id' => 1, 'slug' => 'neko-cat', 'name' => 'Neko Cat Café', 'is_active' => true],
        ];

        $cafeRepo = $this->createStub(CafeRepositoryInterface::class);
        $cafeRepo->method('findActive')->willReturn($cafes);

        $controller = $this->makeController($cafeRepo);

        // Primera petición para obtener ETag real
        $first = $controller->index($this->makeGetRequest('/api/v1/cafes'));
        $etag  = $first->getHeaderLine('ETag');

        // Segunda petición con If-None-Match
        $request = (new ServerRequest('GET', '/api/v1/cafes'))
            ->withHeader('If-None-Match', $etag);

        $response = $controller->index($request);

        $this->assertSame(304, $response->getStatusCode());
    }

    public function test_index_returns_empty_items_when_no_cafes(): void
    {
        $cafeRepo = $this->createStub(CafeRepositoryInterface::class);
        $cafeRepo->method('findActive')->willReturn([]);

        $response = $this->makeController($cafeRepo)->index(
            $this->makeGetRequest('/api/v1/cafes')
        );

        $this->assertSame(200, $response->getStatusCode());
        $body = \json_decode((string) $response->getBody(), true);
        $this->assertIsArray($body['data']['items']);
        $this->assertCount(0, $body['data']['items']);
    }

    // ─────────────────────────────────────────────────────────────
    // show()
    // ─────────────────────────────────────────────────────────────

    public function test_show_returns_200_when_cafe_found(): void
    {
        $cafeRepo = $this->createStub(CafeRepositoryInterface::class);
        $cafeRepo->method('findBySlug')->willReturn([
            'id' => 1, 'slug' => 'neko-cat', 'name' => 'Neko Cat Café', 'is_active' => true,
        ]);

        $request = $this->makeGetRequest('/api/v1/cafes/neko-cat')
            ->withAttribute('slug', 'neko-cat');

        $response = $this->makeController($cafeRepo)->show($request);

        $this->assertSame(200, $response->getStatusCode());
        $body = \json_decode((string) $response->getBody(), true);
        $this->assertTrue($body['ok']);
    }

    public function test_show_returns_404_when_cafe_not_found(): void
    {
        $cafeRepo = $this->createStub(CafeRepositoryInterface::class);
        $cafeRepo->method('findBySlug')->willReturn(null);

        $request = $this->makeGetRequest('/api/v1/cafes/inexistente')
            ->withAttribute('slug', 'inexistente');

        $response = $this->makeController($cafeRepo)->show($request);

        $this->assertSame(404, $response->getStatusCode());
    }

    public function test_show_returns_422_when_slug_attribute_is_empty(): void
    {
        $response = $this->makeController()->show(
            $this->makeGetRequest('/api/v1/cafes/')
        );

        $this->assertSame(422, $response->getStatusCode());
    }

    public function test_class_has_expected_methods(): void
    {
        $this->assertTrue(\method_exists(CafeController::class, 'index'));
        $this->assertTrue(\method_exists(CafeController::class, 'show'));
    }
}
