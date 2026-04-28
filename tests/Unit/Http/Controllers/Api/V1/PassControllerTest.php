<?php

/**
 * ¿Qué pruebas aquí?
 * Verifica que Api\V1\PassController cumple el contrato PSR-7 de la API pública.
 *
 * ¿Qué me quieres demostrar?
 * Que index() devuelve 200 con items/total, respeta Cache-Control y ETag
 * con 304 cuando el cliente envía el ETag correcto.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se elimina ETag/Cache-Control, si cambia la estructura de la respuesta,
 * o si se deja de usar AvailabilityService.
 */

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Api\V1;

use App\Core\Http\ResponseFactory;
use App\Http\Controllers\Api\V1\PassController;
use App\Services\Contracts\AvailabilityServiceInterface;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Support\ControllerTestCase;

#[CoversClass(PassController::class)]
final class PassControllerTest extends ControllerTestCase
{
    private function makeController(?AvailabilityServiceInterface $availability = null): PassController
    {
        $service = $availability ?? $this->createStub(AvailabilityServiceInterface::class);

        return new PassController(new ResponseFactory(), $service);
    }

    // ─────────────────────────────────────────────────────────────
    // index()
    // ─────────────────────────────────────────────────────────────

    public function test_index_returns_200_with_items_and_total(): void
    {
        $availability = $this->createStub(AvailabilityServiceInterface::class);
        $availability->method('getAvailablePassesForReservation')->willReturn([
            ['id' => 1, 'name' => 'Pase 1 hora', 'price' => 12.0],
            ['id' => 2, 'name' => 'Pase 2 horas', 'price' => 20.0],
        ]);

        $response = $this->makeController($availability)->index(
            $this->makeGetRequest('/api/v1/passes')
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotEmpty($response->getHeaderLine('ETag'));
        $this->assertStringContainsString('public', $response->getHeaderLine('Cache-Control'));
        $this->assertStringContainsString('max-age=300', $response->getHeaderLine('Cache-Control'));

        $body = \json_decode((string) $response->getBody(), true);
        $this->assertTrue($body['ok']);
        $this->assertArrayHasKey('items', $body['data']);
        $this->assertArrayHasKey('total', $body['data']);
        $this->assertSame(2, $body['data']['total']);
    }

    public function test_index_returns_304_when_etag_matches(): void
    {
        $passes = [['id' => 1, 'name' => 'Pase 1 hora', 'price' => 12.0]];

        $availability = $this->createStub(AvailabilityServiceInterface::class);
        $availability->method('getAvailablePassesForReservation')->willReturn($passes);

        $controller = $this->makeController($availability);

        // Primera petición para obtener ETag
        $first = $controller->index($this->makeGetRequest('/api/v1/passes'));
        $etag  = $first->getHeaderLine('ETag');

        // Segunda petición con If-None-Match
        $request = (new ServerRequest('GET', '/api/v1/passes'))
            ->withHeader('If-None-Match', $etag);

        $response = $controller->index($request);

        $this->assertSame(304, $response->getStatusCode());
    }

    public function test_index_returns_empty_list_when_no_passes(): void
    {
        $availability = $this->createStub(AvailabilityServiceInterface::class);
        $availability->method('getAvailablePassesForReservation')->willReturn([]);

        $response = $this->makeController($availability)->index(
            $this->makeGetRequest('/api/v1/passes')
        );

        $this->assertSame(200, $response->getStatusCode());
        $body = \json_decode((string) $response->getBody(), true);
        $this->assertSame(0, $body['data']['total']);
        $this->assertIsArray($body['data']['items']);
    }

    public function test_class_has_expected_methods(): void
    {
        $this->assertTrue(\method_exists(PassController::class, 'index'));
    }
}
