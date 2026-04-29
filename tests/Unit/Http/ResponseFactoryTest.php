<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * ResponseFactory: creación de respuestas JSON, HTML, redirect,
 * createResponse, createStream y problem details.
 *
 * ¿Qué me quieres demostrar?
 * Que la factory genera PSR-7 responses con status y headers correctos.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se modifica el Content-Type, status code u otros valores por defecto.
 */

namespace Tests\Unit\Http;

use App\Core\Http\ResponseFactory;
use App\Core\Result;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ResponseFactory::class)]
final class ResponseFactoryTest extends TestCase
{
    private ResponseFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new ResponseFactory();
    }

    public function testCreateResponseDefaultsTo200(): void
    {
        $response = $this->factory->createResponse();

        self::assertSame(200, $response->getStatusCode());
    }

    public function testCreateResponseWithCustomCode(): void
    {
        $response = $this->factory->createResponse(404);

        self::assertSame(404, $response->getStatusCode());
    }

    public function testJsonResponseHasCorrectStatusAndContentType(): void
    {
        $response = $this->factory->json(['ok' => true]);

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('application/json', $response->getHeaderLine('Content-Type'));
    }

    public function testJsonResponseBodyContainsEncodedData(): void
    {
        $response = $this->factory->json(['key' => 'valor']);

        $body = (string) $response->getBody();
        self::assertStringContainsString('"key"', $body);
        self::assertStringContainsString('"valor"', $body);
    }

    public function testJsonResponseWithCustomStatus(): void
    {
        $response = $this->factory->json(['error' => 'not found'], 404);

        self::assertSame(404, $response->getStatusCode());
    }

    public function testJsonResponseWithExtraHeaders(): void
    {
        $response = $this->factory->json(['ok' => true], 200, ['X-Custom' => 'test']);

        self::assertSame('test', $response->getHeaderLine('X-Custom'));
    }

    public function testHtmlResponseHasCorrectStatusAndContentType(): void
    {
        $response = $this->factory->html('<p>Hello</p>');

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('text/html', $response->getHeaderLine('Content-Type'));
    }

    public function testHtmlResponseBodyContainsContent(): void
    {
        $response = $this->factory->html('<h1>Título</h1>');

        self::assertStringContainsString('<h1>Título</h1>', (string) $response->getBody());
    }

    public function testHtmlResponseWithCustomStatusAndHeaders(): void
    {
        $response = $this->factory->html('Not Found', 404, ['X-Reason' => 'missing']);

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('missing', $response->getHeaderLine('X-Reason'));
    }

    public function testRedirectResponseHas302AndLocationHeader(): void
    {
        $response = $this->factory->redirect('/dashboard');

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/dashboard', $response->getHeaderLine('Location'));
    }

    public function testRedirectResponseWithCustomStatus(): void
    {
        $response = $this->factory->redirect('/login', 301);

        self::assertSame(301, $response->getStatusCode());
        self::assertSame('/login', $response->getHeaderLine('Location'));
    }

    public function testCreateStreamReturnsStreamWithContent(): void
    {
        $stream = $this->factory->createStream('hello');

        self::assertSame('hello', (string) $stream);
    }

    public function testCreateStreamDefaultsToEmpty(): void
    {
        $stream = $this->factory->createStream();

        self::assertSame('', (string) $stream);
    }

    public function testProblemResponseHasProblemJsonContentType(): void
    {
        $result = Result::fail('Recurso no encontrado', 'not_found');
        $response = $this->factory->problem($result, 404);

        self::assertSame(404, $response->getStatusCode());
        self::assertStringContainsString('application/problem+json', $response->getHeaderLine('Content-Type'));
    }

    public function testProblemResponseBodyContainsErrorInfo(): void
    {
        $result = Result::fail('Sin permiso', 'forbidden');
        $response = $this->factory->problem($result, 403);

        $body = (string) $response->getBody();
        self::assertStringContainsString('Sin permiso', $body);
    }
}
