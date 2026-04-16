<?php

declare(strict_types=1);

namespace App\Core;

use Psr\Http\Message\ServerRequestInterface;
use Random\RandomException;

/**
 * Protección CSRF basada en sesión.
 *
 * Proporciona generación, rotación y validación de tokens CSRF para formularios
 * HTML y peticiones AJAX/JSON. Incluye helpers para insertar el token en
 * formularios (`field`) y para exponerlo a JavaScript (`meta`).
 *
 * @package App\Core
 */
final class Csrf
{
    private const string SESSION_KEY = '_csrf_token';
    private const string HEADER_NAME = 'HTTP_X_CSRF_TOKEN';
    private const string FIELD_NAME = 'csrf_token';

    // ─────────────────────────────────────────────────────────────
    // Generación y obtención de tokens
    // ─────────────────────────────────────────────────────────────

    /**
     * Inicializa el token CSRF si no existe.
     *
     * @throws RandomException Si el generador de números aleatorios falla
     */
    public static function init(): void
    {
        Session::start();

        if (empty($_SESSION[self::SESSION_KEY])) {
            self::regenerate();
        }
    }

    /**
     * Genera un nuevo token (rotación).
     * Llamar después de login/logout o cambios de privilegios.
     * @throws RandomException
     */
    public static function regenerate(): void
    {
        Session::start();
        $_SESSION[self::SESSION_KEY] = \bin2hex(\random_bytes(32));
    }

    /**
     * Obtiene el token actual.
     *
     * @return string Token CSRF (cadena vacía si no existe)
     */
    public static function token(): string
    {
        // Asegurar que el token existe (lazy-init)
        try {
            self::init();
        } catch (\Throwable) {
            return '';
        }

        return $_SESSION[self::SESSION_KEY] ?? '';
    }

    // ─────────────────────────────────────────────────────────────
    // Helpers para formularios
    // ─────────────────────────────────────────────────────────────

    /**
     * Genera input hidden para formularios.
     *
     * @throws RandomException
     * @return string HTML del input hidden
     */
    public static function field(): string
    {
        self::init();

        return \sprintf(
            '<input type="hidden" name="%s" value="%s">',
            self::FIELD_NAME,
            \htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8')
        );
    }

    /**
     * Genera meta tag para JavaScript/AJAX.
     *
     * @throws RandomException
     * @return string Meta tag HTML
     */
    public static function meta(): string
    {
        self::init();

        return \sprintf(
            '<meta name="csrf-token" content="%s">',
            \htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8')
        );
    }

    // ─────────────────────────────────────────────────────────────
    // Validación
    // ─────────────────────────────────────────────────────────────

    /**
     * Valida el token CSRF.
     *
     * @param ServerRequestInterface|null $request Request PSR-7 (opcional, usa $_POST si null)
     * @return boolean True si válido, false si inválido.
     * @throws RandomException
     * @throws \JsonException
     */
    public static function validate(?ServerRequestInterface $request = null): bool
    {
        self::init();

        $token = self::extractToken($request);
        $sessionToken = self::token();

        // Validación timing-safe
        $ok = \is_string($token)
            && $token !== ''
            && $sessionToken !== ''
            && \hash_equals($sessionToken, $token);

        if (!$ok) {
            // Log diagnóstico para desarrollo: no revelar tokens completos en logs
            $masked = static function (?string $t): string {
                if ($t === null || $t === '') {
                    return '<empty>';
                }

                return \substr($t, 0, 6) . '...' . \strlen($t);
            };

            $msg = \sprintf(
                '[Csrf::validate] mismatch - extracted=%s session=%s request=%s',
                $masked($token),
                $masked($sessionToken),
                $_SERVER['REQUEST_URI'] ?? 'N/A'
            );

            Logger::warning($msg);
        }

        return $ok;
    }

    /**
     * Valida y aborta con respuesta 419 si falla.
     *
     * @param ServerRequestInterface|null $request Request PSR-7 (opcional, usa $_POST si null)
     * @throws RandomException
     * @throws \JsonException
     */
    public static function verify(?ServerRequestInterface $request = null): void
    {
        if (self::validate($request)) {
            return;
        }

        self::abort419();
    }

    // ─────────────────────────────────────────────────────────────
    // Métodos privados
    // ─────────────────────────────────────────────────────────────

    /**
     * Extrae el token de la petición (POST, Header, o JSON body).
     *
     * @param ServerRequestInterface|null $request Request PSR-7 (opcional, usa $_POST si null)
     * @return string|null
     * @throws \JsonException
     */
    private static function extractToken(?ServerRequestInterface $request = null): ?string
    {
        // Si hay request PSR-7, usarlo preferentemente
        if ($request !== null) {
            // 1. Formularios POST (parsed body)
            $parsedBody = $request->getParsedBody();
            if (\is_array($parsedBody) && isset($parsedBody[self::FIELD_NAME])) {
                return $parsedBody[self::FIELD_NAME];
            }

            // 2. Header X-CSRF-TOKEN (AJAX/Fetch)
            $headerValue = $request->getHeaderLine('X-CSRF-Token');
            if ($headerValue !== '') {
                return $headerValue;
            }

            // 3. Body JSON
            $contentType = $request->getHeaderLine('Content-Type');
            if (\str_contains($contentType, 'application/json')) {
                return self::extractFromJsonBody();
            }

            return null;
        }

        // Fallback legacy usando superglobals
        // 1. Formularios POST
        if (isset($_POST[self::FIELD_NAME])) {
            return $_POST[self::FIELD_NAME];
        }

        // 2. Header X-CSRF-TOKEN (AJAX/Fetch)
        if (isset($_SERVER[self::HEADER_NAME])) {
            return $_SERVER[self::HEADER_NAME];
        }

        // 3. Body JSON
        if (self::isJsonRequest()) {
            return self::extractFromJsonBody();
        }

        return null;
    }

    /**
     * Detecta si es una petición JSON.
     *
     * @return boolean
     */
    private static function isJsonRequest(): bool
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        return \str_contains($contentType, 'application/json');
    }

    /**
     * Detecta si espera respuesta JSON.
     *
     * @return boolean
     */
    private static function expectsJson(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';

        return self::isJsonRequest() || \str_contains($accept, 'application/json');
    }

    /**
     * Extrae token del body JSON.
     * @return string|null
     * @throws \JsonException
     */
    private static function extractFromJsonBody(): ?string
    {
        $raw = \file_get_contents('php://input');

        if ($raw === false || \trim($raw) === '') {
            return null;
        }

        $decoded = \json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

        return \is_array($decoded) ? ($decoded[self::FIELD_NAME] ?? null) : null;
    }

    /**
     * Responde con error 419 (Page Expired).
     * Formato según tipo de petición (JSON o HTML).
     *
     * @throws \JsonException
     */
    private static function abort419(): never
    {
        if (!\headers_sent()) {
            @\http_response_code(419);

            if (self::expectsJson()) {
                \header('Content-Type: application/json; charset=UTF-8');
                echo \json_encode(
                    ['error' => 'Token CSRF inválido o sesión expirada.'],
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE
                );
            } else {
                // Redirigir a página de error genérica
                // El Router/ErrorController manejará el 419
                \header('Location: /error/419');
            }
        } else {
            Logger::error('[Csrf::abort419] headers already sent; cannot set response or redirect', []);
        }

        exit;
    }
}
