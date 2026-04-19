<?php

/**
 * ¿Qué pruebas aquí?
 * Verifica que NewsletterApiController valida email antes de llamar al servicio.
 *
 * ¿Qué me quieres demostrar?
 * Que subscribe() retorna 422 sin email y 200 cuando el servicio confirma suscripción.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se elimina la validación de email vacío o cambia el formato de respuesta de subscribe().
 */

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Api;

use App\Core\Http\ResponseFactory;
use App\Core\Result;
use App\Http\Controllers\Api\V1\NewsletterApiController;
use App\Services\Contracts\NewsletterServiceInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use Tests\Support\ControllerTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(NewsletterApiController::class)]
final class NewsletterApiControllerTest extends ControllerTestCase
{
    private function makeController(?NewsletterServiceInterface $service = null): NewsletterApiController
    {
        if ($service === null) {
            $service = $this->createMock(NewsletterServiceInterface::class);
            $service->method('subscribe')->willReturn(Result::ok(['message' => 'Suscrito correctamente']));
        }

        return new NewsletterApiController($service, new ResponseFactory());
    }

    private function makeJsonRequest(string $path, array $data): ServerRequest
    {
        $factory = new Psr17Factory();

        return new ServerRequest('POST', $path)
            ->withBody($factory->createStream((string) \json_encode($data)));
    }

    public function test_subscribe_returns_422_when_body_is_empty(): void
    {
        $response = $this->makeController()->subscribe(
            $this->makePostRequest('/api/newsletter/subscribe', [])
        );

        $this->assertSame(422, $response->getStatusCode());
    }

    public function test_subscribe_returns_200_when_service_succeeds(): void
    {
        $response = $this->makeController()->subscribe(
            $this->makeJsonRequest('/api/newsletter/subscribe', ['email' => 'test@example.com'])
        );

        $this->assertSame(200, $response->getStatusCode());
        $body = \json_decode((string) $response->getBody(), true);
        $this->assertTrue($body['ok']);
    }

    public function test_subscribe_returns_400_when_service_fails(): void
    {
        $service = $this->createMock(NewsletterServiceInterface::class);
        $service->method('subscribe')->willReturn(Result::fail('Ya suscrito'));

        $response = $this->makeController($service)->subscribe(
            $this->makeJsonRequest('/api/newsletter/subscribe', ['email' => 'test@example.com'])
        );

        $this->assertSame(400, $response->getStatusCode());
    }
}
