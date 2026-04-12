<?php

declare(strict_types=1);

namespace App\Core;

// Cargar helpers globales
require_once __DIR__ . '/Helpers.php';

use RuntimeException;

/**
 * Sistema de renderizado de vistas.
 *
 * Provee utilidades para renderizar vistas, layouts y componentes, con
 * escapado automático para prevenir XSS y validación de paths para evitar
 * path traversal.
 */
final class View
{
    private static ?string $viewsDir = null;

    // ─────────────────────────────────────────────────────────────
    // Renderizado principal
    // ─────────────────────────────────────────────────────────────

    /**
     * Retorna el array de security headers (testeable sin side-effects).
     * @return array<string, string>
     */
    public static function getSecurityHeaders(): array
    {
        return [
            'Content-Security-Policy'   => "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:; connect-src 'self'; frame-ancestors 'none'",
            'X-Frame-Options'           => 'DENY',
            'X-Content-Type-Options'    => 'nosniff',
            'Referrer-Policy'           => 'strict-origin-when-cross-origin',
            'Permissions-Policy'        => 'geolocation=(), microphone=(), camera=()',
        ];
    }


    /**
     * Renderiza una vista con layout opcional.
     *
     * Todos los valores de $data se escapan automáticamente (XSS). Para pasar HTML o JSON
     * sin escapar usa {@see Raw::html()} o {@see Raw::json()}. No se admiten objetos —
     * los DTOs deben llamar a toViewArray() antes de pasarlos.
     *
     * @param string                                                            $view     Ruta de la plantilla relativa a views/ (p. ej. 'public/cafes/show')
     * @param array<string, array<mixed>|string|integer|float|boolean|null|Raw> $data     Variables de vista. Sin objetos — los DTOs deben llamar a toViewArray() antes.
     * @param array<string>                                                     $extraCss Archivos CSS adicionales a incluir
     * @param string|null                                                       $layout   Nombre del layout (null = sin layout, default: 'main')
     */
    public static function render(
        string $view,
        array $data = [],
        array $extraCss = [],
        ?string $layout = 'main'
    ): void {
        // 1) Extraer extraCss y extraJs del array de datos ANTES de escapar
        // El extraCss puede venir como parámetro o dentro de $data
        if (!empty($data['extraCss'])) {
            $extraCss = array_merge($extraCss, (array)$data['extraCss']);
            unset($data['extraCss']);
        }

        $extraJs = $data['extraJs'] ?? [];
        unset($data['extraJs']);

        // 2) Ahora escapar datos (previene XSS)
        $data = self::escapeData($data);

        // 3) Renderizar contenido de la vista
        $content = self::capture($view, $data);

        // 4) Sin layout: devolver vista directamente
        if ($layout === null) {
            echo $content;

            return;
        }

        // 5) Con layout: renderizar layout con el contenido
        $layoutData = \array_merge($data, [
            'content' => new Raw($content),  // El contenido ya está escapado
            'extraCss' => $extraCss,
            'extraJs' => $extraJs,
        ]);

        echo self::capture("layouts/$layout", $layoutData);
    }

    /**
     * Renderiza una vista y la devuelve como string (sin output).
     */
    public static function renderToString(
        string $view,
        array $data = [],
        array $extraCss = [],
        ?string $layout = 'main'
    ): string {
        \ob_start();
        self::render($view, $data, $extraCss, $layout);

        return \ob_get_clean() ?: '';
    }

    /**
     * Renderiza un componente/partial.
     * No usa layout, ideal para fragmentos reutilizables.
     *
     * @param string $component Ej: "components/card", "partials/nav"
     * @param array  $data      Variables para el componente
     */
    public static function component(string $component, array $data = []): void
    {
        echo self::capture($component, self::escapeData($data));
    }

    /**
     * Renderiza un componente y lo devuelve como string.
     */
    public static function componentToString(string $component, array $data = []): string
    {
        return self::capture($component, self::escapeData($data));
    }

    // ─────────────────────────────────────────────────────────────
    // Respuestas especiales
    // ─────────────────────────────────────────────────────────────

    /**
     * Respuesta JSON.
     *
     * @param mixed   $data   Datos a serializar
     * @param integer $status Código HTTP
     * @return never
     * @throws \JsonException
     */
    public static function json(mixed $data, int $status = 200): never
    {
        if (!\headers_sent()) {
            @\http_response_code($status);
            \header('Content-Type: application/json; charset=UTF-8');
        } else {
            Logger::error('[View::json] headers already sent; skipping \header() and http_response_code()');
        }
        echo \json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        exit;
    }

    /**
     * Respuesta JSON de éxito.
     * @throws \JsonException
     */
    public static function jsonSuccess(mixed $data = null, string $message = 'OK'): never
    {
        self::json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ]);
    }

    /**
     * Respuesta JSON de error.
     * @throws \JsonException
     */
    public static function jsonError(string $message, int $status = 400, array $errors = []): never
    {
        self::json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $status);
    }

    /**
     * Redirección.
     */
    /**
     * Redirige a una URL y termina la ejecución.
     *
     * @param string  $url    URL de destino
     * @param integer $status Código HTTP de redirección
     * @return never
     */
    public static function redirect(string $url, int $status = 302): never
    {
        if (!\headers_sent()) {
            @\http_response_code($status);
            \header("Location: $url");
        } else {
            Logger::error('[View::redirect] headers already sent; cannot redirect to ' . $url);
        }
        exit;
    }

    /**
     * Redirección con mensaje flash.
     */
    /**
     * Redirige a `url` y establece un mensaje flash antes de salir.
     *
     * @param string $url     URL de destino
     * @param string $type    Tipo de mensaje (success|error|info|warning)
     * @param string $message Texto del mensaje
     * @return never
     */
    public static function redirectWith(string $url, string $type, string $message): never
    {
        Flash::set($type, $message);
        self::redirect($url);
    }

    /**
     * Volver a la página anterior.
     */
    /**
     * Redirige a la página anterior usando `HTTP_REFERER`.
     *
     * @return never
     */
    public static function back(): never
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? '/';
        self::redirect($referer);
    }

    // ─────────────────────────────────────────────────────────────
    // HTML Helpers (Performance & Accessibility)
    // ─────────────────────────────────────────────────────────────

    /**
     * Genera una etiqueta <img> con lazy loading y atributos accesibles.
     *
     * Por defecto usa loading="lazy" para mejorar performance (LCP/TTI).
     * Para imágenes críticas above-the-fold, pasar 'loading' => 'eager'.
     *
     * @param string $src   URL de la imagen (sin escapar)
     * @param string $alt   Texto alternativo (sin escapar, se escapará aquí)
     * @param array  $attrs Atributos adicionales: width, height, class, loading, etc.
     * @return string HTML de la etiqueta <img>
     *
     * @example
     * ```php
     * // Lazy loading por defecto (para imágenes below-the-fold)
     * echo View::img('/images/logo.png', 'Komorebi Café Logo');
     *
     * // Eager loading para hero images (above-the-fold)
     * echo View::img('/images/hero.jpg', 'Hero', ['loading' => 'eager', 'class' => 'hero-img']);
     *
     * // Con dimensiones explícitas (mejora CLS)
     * echo View::img('/images/product.jpg', 'Producto', ['width' => 400, 'height' => 300]);
     * ```
     */
    public static function img(string $src, string $alt, array $attrs = []): string
    {
        // Escapar src y alt para seguridad
        $escapedSrc = htmlspecialchars($src, ENT_QUOTES, 'UTF-8');
        $escapedAlt = htmlspecialchars($alt, ENT_QUOTES, 'UTF-8');

        // Default loading strategy: lazy (performance)
        $loading = $attrs['loading'] ?? 'lazy';
        unset($attrs['loading']);

        // Build attributes string
        $attrsString = '';
        foreach ($attrs as $key => $value) {
            if ($value === null || $value === false) {
                continue; // Skip null/false attributes
            }

            $escapedKey = htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
            $escapedValue = htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
            $attrsString .= " {$escapedKey}=\"{$escapedValue}\"";
        }

        return "<img src=\"{$escapedSrc}\" alt=\"{$escapedAlt}\" loading=\"{$loading}\"{$attrsString}>";
    }

    // ─────────────────────────────────────────────────────────────
    // Métodos privados
    // ─────────────────────────────────────────────────────────────

    /**
     * Captura el output de una vista.
     *
     * @param string $view     Identificador de la vista (p.ej. 'auth/login')
     * @param array  $data     Variables a extraer en la vista
     * @param array  $extraCss CSS adicional esperado por el layout
     * @return string Contenido renderizado
     */
    /**
     * Renderiza una vista en un buffer y la devuelve como string.
     *
     * @param string $view Ruta de vista relativa (sin .php)
     * @param array  $data Variables para la vista
     *
     * @return string Contenido renderizado
     */
    private static function capture(string $view, array $data = []): string
    {
        $viewFile = self::resolvePath($view);

        $scope = new class {
            public array $sections = [];
            private ?string $current = null;
            public ?string $layout = null;

            public function extend(string $layout): void
            {
                $this->layout = $layout;
            }

            public function start(string $name): void
            {
                $this->current = $name;
                \ob_start();
            }

            public function end(): void
            {
                if ($this->current === null) {
                    return;
                }

                $this->sections[$this->current] = (string) \ob_get_clean();
                $this->current = null;
            }

            public function flash(string $type): mixed
            {
                return Flash::get($type);
            }
        };

        $renderer = function () use ($viewFile, $data) {
            if (\is_array($data)) {
                \extract($data, EXTR_SKIP);
            }
            require $viewFile;
        };

        \ob_start();
        // Ejecutar la closure con $this apuntando a $scope
        $renderer = $renderer->bindTo($scope, $scope);
        $renderer();
        $output = \ob_get_clean() ?: '';

        // Si la vista definió una sección `content`, devolverla en prioridad
        if (!empty($scope->sections['content'])) {
            return $scope->sections['content'];
        }

        return $output;
    }

    /**
     * Resuelve y valida la ruta de una vista.
     *
     * @param string $view
     * @return string
     */
    private static function resolvePath(string $view): string
    {
        $viewsDir = self::getViewsDir();

        // Validar identificador (previene path traversal)
        if (!\preg_match('#^[a-zA-Z0-9/_-]+$#', $view)) {
            throw new RuntimeException("Identificador de vista inválido: $view");
        }

        $viewFile = \realpath($viewsDir . $view . '.php');

        // Verificar que existe y esté dentro del directorio de vistas
        if ($viewFile === false || !\str_starts_with($viewFile, $viewsDir)) {
            throw new RuntimeException("Vista no encontrada: $view");
        }

        return $viewFile;
    }

    /**
     * Obtiene el directorio de vistas (con cache).
     *
     * @return string Directorio absoluto donde están las vistas (terminado en /)
     */
    private static function getViewsDir(): string
    {
        if (self::$viewsDir === null) {
            self::$viewsDir = \dirname(__DIR__, 2) . '/resources/views/';
        }

        return self::$viewsDir;
    }

    /**
     * Escapa datos recursivamente para prevenir XSS.
     *
     * - Raw: se devuelve tal cual (ya es seguro)
     * - string: htmlspecialchars
     * - array: recursivo
     * - otros: sin cambio
     * @param mixed $data
     * @return mixed
     */
    private static function escapeData(mixed $data): mixed
    {
        // Objetos Raw no se escapan
        if ($data instanceof Raw) {
            return $data;
        }

        // Arrays: escapar recursivamente
        if (\is_array($data)) {
            return \array_map([self::class, 'escapeData'], $data);
        }

        // Strings: escapar HTML
        if (\is_string($data)) {
            return \htmlspecialchars($data, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        // Otros tipos (int, bool, null, objects): sin cambio
        return $data;
    }
}
