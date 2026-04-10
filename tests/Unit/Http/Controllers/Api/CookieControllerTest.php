<?php

/**
 * ¿Qué pruebas aquí?
 * Verifica que Api/CookieController cumple el contrato PSR-7.
 *
 * ¿Qué me quieres demostrar?
 * Que accept() y reject() retornan ResponseInterface con JSON de éxito,
 * sin necesitar sesión ni BD.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si accept() o reject() dejan de retornar ResponseInterface,
 * o si el formato de la respuesta cambia.
 */

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Api;

use App\Core\Http\ResponseFactory;
use App\Http\Controllers\Api\CookieController;
use Nyholm\Psr7\ServerRequest;
use Tests\Support\ControllerTestCase;
use Psr\Http\Message\ResponseInterface;

final class CookieControllerTest extends ControllerTestCase
{
    private function makeController(): CookieController
    {
        return new CookieController(new ResponseFactory());
    }

    public function test_accept_returns_success_json_response(): void
    {
        $result = $this->makeController()->accept(
            new ServerRequest('POST', '/api/cookies/accept')
        );

        $this->assertInstanceOf(ResponseInterface::class, $result);
        $this->assertResponseIsJson($result, 200);

        $body = json_decode((string) $result->getBody(), true);
        $this->assertTrue($body['ok']);
    }

    public function test_reject_returns_success_json_response(): void
    {
        $result = $this->makeController()->reject(
            new ServerRequest('POST', '/api/cookies/reject')
        );

        $this->assertInstanceOf(ResponseInterface::class, $result);
        $this->assertResponseIsJson($result, 200);

        $body = json_decode((string) $result->getBody(), true);
        $this->assertTrue($body['ok']);
    }

    public function test_update_returns_422_when_body_not_array(): void
    {
        $request = (new ServerRequest('POST', '/api/cookies/update'))
            ->withParsedBody(null);

        $result = $this->makeController()->update($request);

        $this->assertInstanceOf(ResponseInterface::class, $result);
        $this->assertSame(422, $result->getStatusCode());
    }
}
