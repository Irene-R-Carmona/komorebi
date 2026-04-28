<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * Los helpers de respuesta de AbstractApiController.
 *
 * ¿Qué me quieres demostrar?
 * Que created() retorna 201, noContent() retorna 204 sin cuerpo,
 * badRequest() retorna 400 Problem Details, y paginated() incluye
 * los metadatos de paginación en el envelope estándar.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se cambia el código HTTP de algún helper, el formato del envelope,
 * o la estructura de metadatos de paginación.
 */

namespace Tests\Unit\Http\Controllers\Api;

use App\Core\Http\ResponseFactory;
use App\Core\Pagination;
use App\Http\Controllers\Api\AbstractApiController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/** Subclase concreta mínima para ejercitar los helpers protegidos */
final class ConcreteApiController extends AbstractApiController
{
    public function callCreated(mixed $data = null): ResponseInterface
    {
        return $this->created($data);
    }

    public function callNoContent(): ResponseInterface
    {
        return $this->noContent();
    }

    public function callBadRequest(string $detail): ResponseInterface
    {
        return $this->badRequest($detail);
    }

    /** @param list<mixed> $items */
    public function callPaginated(array $items, Pagination $pagination): ResponseInterface
    {
        return $this->paginated($items, $pagination);
    }
}

#[CoversClass(AbstractApiController::class)]
final class AbstractApiControllerHelpersTest extends TestCase
{
    private ConcreteApiController $controller;

    protected function setUp(): void
    {
        $this->controller = new ConcreteApiController(new ResponseFactory());
    }

    public function test_created_returns_201(): void
    {
        $response = $this->controller->callCreated(['id' => 1]);

        $this->assertSame(201, $response->getStatusCode());
    }

    public function test_created_body_has_ok_true_and_data(): void
    {
        $response = $this->controller->callCreated(['id' => 99]);
        $body = \json_decode((string) $response->getBody(), true);

        $this->assertTrue($body['ok']);
        $this->assertSame(99, $body['data']['id']);
    }

    public function test_no_content_returns_204(): void
    {
        $response = $this->controller->callNoContent();

        $this->assertSame(204, $response->getStatusCode());
    }

    public function test_no_content_has_empty_body(): void
    {
        $response = $this->controller->callNoContent();

        $this->assertSame('', (string) $response->getBody());
    }

    public function test_bad_request_returns_400(): void
    {
        $response = $this->controller->callBadRequest('Campo inválido.');

        $this->assertSame(400, $response->getStatusCode());
    }

    public function test_bad_request_is_problem_json(): void
    {
        $response = $this->controller->callBadRequest('Falta el campo nombre.');
        $contentType = $response->getHeaderLine('Content-Type');

        $this->assertStringContainsString('application/problem+json', $contentType);
    }

    public function test_paginated_returns_200(): void
    {
        $pagination = Pagination::fromRequest(1, 10);
        $response   = $this->controller->callPaginated([['id' => 1], ['id' => 2]], $pagination);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_paginated_body_contains_items_and_meta(): void
    {
        $pagination = Pagination::fromRequest(2, 5);
        $items      = [['id' => 1], ['id' => 2], ['id' => 3]];
        $response   = $this->controller->callPaginated($items, $pagination);

        $body = \json_decode((string) $response->getBody(), true);

        $this->assertTrue($body['ok']);
        $this->assertArrayHasKey('items', $body['data']);
        $this->assertArrayHasKey('meta', $body['data']);
        $this->assertArrayHasKey('page', $body['data']['meta']);
        $this->assertSame(2, $body['data']['meta']['page']);
    }
}
