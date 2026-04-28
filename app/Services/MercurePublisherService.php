<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use Throwable;

/**
 * Publica eventos SSE en el Mercure Hub integrado de FrankenPHP.
 *
 * Casos de uso:
 *   - KDS:       nuevas órdenes      → topic kds/{cafeId}/orders
 *   - Recepción: nuevas reservas     → topic reception/{cafeId}/reservations
 *   - Waitlist:  actualizaciones     → topic waitlist/{cafeId}
 *
 * No requiere librería JWT externa; genera tokens HS256 en PHP puro.
 * Si MERCURE_JWT_SECRET no está configurado, los eventos se omiten silenciosamente.
 */
final class MercurePublisherService
{
    /** URL interna del Mercure Hub (PHP → FrankenPHP dentro del mismo proceso/contenedor) */
    private const string HUB_INTERNAL_URL = 'http://localhost/.well-known/mercure';
    private const int    PUBLISH_TIMEOUT  = 3;

    /**
     * Publica un evento SSE en el Mercure Hub.
     *
     * @param string               $topic   URI del topic (ej: 'kds/1/orders')
     * @param array<string, mixed> $data    Datos del evento (serializado a JSON)
     * @param bool                 $private Si true, solo suscriptores autenticados
     */
    public static function publish(string $topic, array $data, bool $private = false): bool
    {
        $secret = $_ENV['MERCURE_JWT_SECRET'] ?? null;
        if ($secret === null) {
            Logger::debug('[Mercure] MERCURE_JWT_SECRET no configurado; evento omitido', ['topic' => $topic]);
            return false;
        }

        try {
            $jwt = self::generatePublisherJwt($secret);

            $postFields = \http_build_query(\array_filter([
                'topic'   => $topic,
                'data'    => \json_encode($data, \JSON_THROW_ON_ERROR),
                'private' => $private ? '1' : null,
            ]));

            $context = \stream_context_create([
                'http' => [
                    'method'        => 'POST',
                    'header'        => "Content-Type: application/x-www-form-urlencoded\r\n"
                        . "Authorization: Bearer {$jwt}\r\n"
                        . "Content-Length: " . \strlen($postFields) . "\r\n",
                    'content'       => $postFields,
                    'timeout'       => self::PUBLISH_TIMEOUT,
                    'ignore_errors' => true,
                ],
            ]);

            $response = \file_get_contents(self::HUB_INTERNAL_URL, false, $context);
            $ok = ($response !== false);

            if ($ok) {
                Logger::debug('[Mercure] Evento publicado', ['topic' => $topic]);
            } else {
                Logger::warning('[Mercure] Fallo al publicar; hub no disponible', ['topic' => $topic]);
            }

            return $ok;
        } catch (Throwable $e) {
            Logger::error('[Mercure] Error publicando evento', [
                'topic' => $topic,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Genera un JWT HS256 con permiso para publicar en cualquier topic.
     *
     * @param non-empty-string $secret Clave HMAC-SHA256
     */
    private static function generatePublisherJwt(string $secret): string
    {
        $header  = self::base64UrlEncode(
            \json_encode(['alg' => 'HS256', 'typ' => 'JWT'], \JSON_THROW_ON_ERROR)
        );
        $payload = self::base64UrlEncode(
            \json_encode(['mercure' => ['publish' => ['*']]], \JSON_THROW_ON_ERROR)
        );
        $signature = self::base64UrlEncode(
            \hash_hmac('sha256', "{$header}.{$payload}", $secret, true)
        );

        return "{$header}.{$payload}.{$signature}";
    }

    private static function base64UrlEncode(string $data): string
    {
        return \rtrim(\strtr(\base64_encode($data), '+/', '-_'), '=');
    }
}
