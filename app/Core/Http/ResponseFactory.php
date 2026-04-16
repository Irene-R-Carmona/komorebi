<?php

declare(strict_types=1);

namespace App\Core\Http;

use App\Core\Result;
use JsonException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Factory para crear Response PSR-7.
 *
 * Wrapper sobre Nyholm PSR-17 Factory para simplificar
 * la creación de respuestas HTTP estándar.
 */
final class ResponseFactory
{
    private Psr17Factory $factory;

    public function __construct()
    {
        $this->factory = new Psr17Factory();
    }

    /**
     * Crea una respuesta vacía con status code.
     */
    public function createResponse(int $code = 200, string $reasonPhrase = ''): ResponseInterface
    {
        return $this->factory->createResponse($code, $reasonPhrase);
    }

    /**
     * Crea una respuesta JSON.
     * @throws JsonException
     */
    public function json(array $data, int $status = 200, array $headers = []): ResponseInterface
    {
        $json = \json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        $response = $this->createResponse($status)
            ->withHeader('Content-Type', 'application/json; charset=utf-8');

        foreach ($headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        $response->getBody()->write($json);

        return $response;
    }

    /**
     * Crea una respuesta HTML.
     */
    public function html(string $content, int $status = 200, array $headers = []): ResponseInterface
    {
        $response = $this->createResponse($status)
            ->withHeader('Content-Type', 'text/html; charset=utf-8');

        foreach ($headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        $response->getBody()->write($content);

        return $response;
    }

    /**
     * Crea una respuesta de redirección.
     */
    public function redirect(string $url, int $status = 302): ResponseInterface
    {
        return $this->createResponse($status)
            ->withHeader('Location', $url);
    }

    /**
     * Crea un stream desde contenido string.
     */
    public function createStream(string $content = ''): StreamInterface
    {
        return $this->factory->createStream($content);
    }

    /**
     * Crea una respuesta RFC 9457 Problem Details.
     *
     * @throws JsonException
     */
    public function problem(Result $result, int $status): ResponseInterface
    {
        $json = \json_encode(
            ProblemDetails::fromResult($result, $status),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE,
        );

        $response = $this->createResponse($status)
            ->withHeader('Content-Type', 'application/problem+json');

        $response->getBody()->write($json);

        return $response;
    }
}
