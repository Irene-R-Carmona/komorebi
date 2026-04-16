<?php

/**
 * ¿Qué pruebas aquí?
 * Clase base para tests de controllers PSR-7.
 *
 * ¿Qué me quieres demostrar?
 * Que los helpers makeGetRequest() y makePostRequest() crean requests PSR-7 válidos,
 * y que assertResponseIsRedirect() / assertResponseIsJson() verifican el contrato HTTP.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se cambia la implementación PSR-7 (nyholm → otra librería) sin actualizar los helpers.
 */

declare(strict_types=1);

namespace Tests\Support;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;

abstract class ControllerTestCase extends TestCase
{
    use ResultAssertions;
    protected function makeGetRequest(string $path = '/', array $queryParams = []): ServerRequestInterface
    {
        $request = new ServerRequest('GET', $path);
        if (!empty($queryParams)) {
            $request = $request->withQueryParams($queryParams);
        }

        return $request;
    }

    protected function makePostRequest(string $path = '/', array $body = []): ServerRequestInterface
    {
        $request = new ServerRequest('POST', $path);
        if (!empty($body)) {
            $request = $request->withParsedBody($body);
        }

        return $request;
    }

    protected function makeUploadedFile(
        string $content,
        string $filename,
        string $mimeType,
        int $error = \UPLOAD_ERR_OK
    ): UploadedFileInterface {
        $factory = new Psr17Factory();

        return $factory->createUploadedFile(
            $factory->createStream($content),
            \strlen($content),
            $error,
            $filename,
            $mimeType
        );
    }

    protected function asUser(int $userId = 1, string $role = 'user', ?int $cafeId = null): void
    {
        if (\session_status() === \PHP_SESSION_NONE) {
            \session_start();
        }
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_role'] = $role;
        $_SESSION['user_cafe_id'] = $cafeId;
    }

    /**
     * Decora un request PSR-7 con atributos de usuario autenticado.
     * Útil para controladores que leen el usuario desde request attributes.
     */
    protected function withAuthUser(
        ServerRequestInterface $request,
        int $userId = 1,
        string $role = 'user'
    ): ServerRequestInterface {
        return $request->withAttribute('user', ['id' => $userId, 'role' => $role]);
    }

    /**
     * Afirma que la respuesta es null (controlador usa View::render que hace echo).
     */
    protected function assertResponseIsOk(?ResponseInterface $response): void
    {
        $this->assertNull($response, 'Los controladores que llaman a View::render() deben retornar null');
    }

    protected function assertResponseIsRedirect(ResponseInterface $response, ?string $expectedPath = null): void
    {
        $this->assertContains($response->getStatusCode(), [301, 302, 303]);
        if ($expectedPath !== null) {
            $this->assertSame($expectedPath, $response->getHeaderLine('Location'));
        }
    }

    protected function assertResponseIsJson(ResponseInterface $response, int $expectedStatus = 200): void
    {
        $this->assertSame($expectedStatus, $response->getStatusCode());
        $this->assertStringContainsString('application/json', $response->getHeaderLine('Content-Type'));
    }
}
