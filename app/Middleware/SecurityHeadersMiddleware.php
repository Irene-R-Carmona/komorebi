<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Middleware de Security Headers con soporte para CSP Nonces
 *
 * Implementa:
 * - Content Security Policy con nonces dinámicos para scripts inline
 * - HSTS con preload
 * - Headers de protección (X-Frame-Options, X-Content-Type-Options, etc.)
 * - Cache-Control para contenido dinámico
 */
final class SecurityHeadersMiddleware implements MiddlewareInterface
{
    private string $nonce;

    public function __construct()
    {
        // Generar nonce único para esta request
        $this->nonce = base64_encode(random_bytes(16));

        // Hacer nonce disponible globalmente para vistas
        $GLOBALS['cspNonce'] = $this->nonce;
    }

    #[\Override]
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        // Procesar request
        $response = $handler->handle($request);

        // Content Security Policy con nonce dinámico
        $csp = implode('; ', [
            "default-src 'self'",
            // Scripts: self, CDN Bootstrap/Alpine, nonce para inline, unsafe-eval para Alpine.js
            // Usamos nonce dinámico para permitir scripts inline legítimos y mantenemos CSP estricta.
            "script-src 'self' https://cdn.jsdelivr.net 'nonce-{$this->nonce}' 'unsafe-eval'",
            // Estilos: self, CDNs, unsafe-inline necesario para estilos inline en SVG/componentes
            "style-src 'self' https://cdn.jsdelivr.net https://fonts.googleapis.com https://cdnjs.cloudflare.com 'unsafe-inline'",
            // Element styles: permitir hojas de estilo externas en elementos (preload/onload patterns)
            "style-src-elem 'self' https://fonts.googleapis.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com 'unsafe-inline'",
            // Fuentes: self, Google Fonts, Bootstrap Icons y CDNs (FontAwesome)
            "font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com data:",
            // Imágenes: self, data URIs, blobs, y hosts externos usados por perfiles/testimonials
            "img-src 'self' data: blob: https://randomuser.me",
            // Conexiones: self (API) y CDNs map requests (sourcemaps / charting libs)
            "connect-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com",
            // No permitir embeds del sitio
            "frame-ancestors 'none'",
            // Base URI restringido
            "base-uri 'self'",
            // Form actions solo a mismo origen
            "form-action 'self'",
            // Upgrade insecure requests
            "upgrade-insecure-requests"
        ]);

        // Aplicar headers de seguridad
        $response = $response
            ->withHeader('Content-Security-Policy', $csp)
            ->withHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload')
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('X-Frame-Options', 'DENY')
            ->withHeader('X-XSS-Protection', '1; mode=block')
            ->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->withHeader('Permissions-Policy', 'geolocation=(), microphone=(), camera=(), payment=()')
            // Cache-Control por defecto para HTML dinámico
            ->withHeader('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->withHeader('Pragma', 'no-cache')
            ->withHeader('Expires', '0');

        // Remover header Server por seguridad
        $response = $response->withoutHeader('Server');

        return $response;
    }

    /**
     * Obtiene el nonce CSP actual
     */
    public static function getNonce(): string
    {
        return $GLOBALS['cspNonce'] ?? '';
    }
}
