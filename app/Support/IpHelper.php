<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Env;

/**
 * Resolución de IP de cliente con soporte para proxies de confianza.
 *
 * En Railway (y cualquier entorno con proxy reverso), REMOTE_ADDR contiene
 * la IP del proxy, no la del cliente real. Este helper lee X-Forwarded-For
 * solo cuando REMOTE_ADDR coincide con el proxy de confianza configurado.
 */
final class IpHelper
{
    /**
     * Resuelve la IP real del cliente a partir de los parámetros de servidor.
     *
     * Si TRUSTED_PROXY_IP está configurado y REMOTE_ADDR coincide con él,
     * extrae la primera IP de X-Forwarded-For y la valida. En caso contrario,
     * devuelve REMOTE_ADDR directamente.
     *
     * @param array<string, mixed> $serverParams $_SERVER o $request->getServerParams()
     */
    public static function resolve(array $serverParams): string
    {
        $remoteAddr = (string) ($serverParams['REMOTE_ADDR'] ?? '0.0.0.0');
        $trustedProxy = Env::get('TRUSTED_PROXY_IP', '');

        if ($trustedProxy !== '' && $remoteAddr === $trustedProxy) {
            $forwarded = (string) ($serverParams['HTTP_X_FORWARDED_FOR'] ?? '');
            $candidate = \trim(\explode(',', $forwarded)[0]);

            if ($candidate !== '' && \filter_var($candidate, FILTER_VALIDATE_IP) !== false) {
                return $candidate;
            }
        }

        return $remoteAddr;
    }
}
