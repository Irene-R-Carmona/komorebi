<?php

declare(strict_types=1);

namespace App\Core\Http;

use Psr\Http\Message\ResponseInterface;

/**
 * Emitter que envía una Response PSR-7 al cliente.
 *
 * Se encarga de enviar headers HTTP y el body al output buffer,
 * siguiendo el estándar PSR-7.
 */
final class ResponseEmitter
{
    /**
     * Emite la respuesta PSR-7 al cliente.
     *
     * @param ResponseInterface $response    Respuesta a enviar
     * @param boolean           $withoutBody Si true, no envía el body (útil para HEAD requests)
     */
    public function emit(ResponseInterface $response, bool $withoutBody = false): void
    {
        // Enviar status line
        $this->emitStatusLine($response);

        // Enviar headers
        $this->emitHeaders($response);

        // Enviar body si corresponde
        if (!$withoutBody) {
            $this->emitBody($response);
        }
    }

    /**
     * Envía la línea de status HTTP.
     */
    private function emitStatusLine(ResponseInterface $response): void
    {
        $statusCode = $response->getStatusCode();
        $reasonPhrase = $response->getReasonPhrase();
        $protocolVersion = $response->getProtocolVersion();

        header(
            sprintf('HTTP/%s %d%s', $protocolVersion, $statusCode, $reasonPhrase ? ' ' . $reasonPhrase : ''),
            true,
            $statusCode
        );
    }

    /**
     * Envía los headers HTTP.
     */
    private function emitHeaders(ResponseInterface $response): void
    {
        foreach ($response->getHeaders() as $name => $values) {
            $first = true;
            foreach ($values as $value) {
                header(
                    sprintf('%s: %s', $name, $value),
                    $first
                );
                $first = false;
            }
        }
    }

    /**
     * Envía el body de la respuesta.
     */
    private function emitBody(ResponseInterface $response): void
    {
        $body = $response->getBody();

        if ($body->isSeekable()) {
            $body->rewind();
        }

        $chunkSize = 8192; // 8KB chunks

        while (!$body->eof()) {
            echo $body->read($chunkSize);
        }
    }
}
