<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Http\ExceptionRendererRegistry;
use App\Core\Http\ResponseFactory;
use App\Http\ExceptionRenderers\AuthenticationExceptionRenderer;
use App\Http\ExceptionRenderers\AuthorizationExceptionRenderer;
use App\Http\ExceptionRenderers\BusinessRuleExceptionRenderer;
use App\Http\ExceptionRenderers\DatabaseExceptionRenderer;
use App\Http\ExceptionRenderers\FallbackExceptionRenderer;
use App\Http\ExceptionRenderers\NotFoundExceptionRenderer;
use App\Http\ExceptionRenderers\RateLimitExceptionRenderer;
use App\Http\ExceptionRenderers\ValidationExceptionRenderer;
use App\Http\Middleware\ApiAuthMiddleware;
use App\Http\Middleware\CorsMiddleware;
use App\Services\Contracts\ApiTokenServiceInterface;
use App\Http\Middleware\ApiMiddleware;
use App\Http\Middleware\ApiRoleMiddleware;
use App\Http\Middleware\AuthMiddleware;
use App\Http\Middleware\AuthorizationMiddleware;
use App\Http\Middleware\CafeScopeMiddleware;
use App\Http\Middleware\CsrfMiddleware;
use App\Http\Middleware\ErrorHandlerMiddleware;
use App\Http\Middleware\GuestMiddleware;
use App\Http\Middleware\HttpRateLimitMiddleware;
use App\Http\Middleware\PayloadSizeMiddleware;
use App\Http\Middleware\RequestLogMiddleware;
use App\Http\Middleware\RoleMiddleware;
use App\Middleware\SecurityHeadersMiddleware;
use App\Services\RateLimitingService;
use Psr\Http\Server\MiddlewareInterface;

/**
 * Factory para crear middlewares PSR-15 con sintaxis simplificada.
 *
 * Elimina la necesidad de instanciar middlewares manualmente en routes.php.
 */
final class MiddlewareFactory
{
    private ResponseFactory $response;

    public function __construct(ResponseFactory $response)
    {
        $this->response = $response;
    }

    public function auth(): AuthMiddleware
    {
        return new AuthMiddleware($this->response);
    }

    public function csrf(): CsrfMiddleware
    {
        return new CsrfMiddleware($this->response);
    }

    public function guest(string $redirectTo = '/'): MiddlewareInterface
    {
        return new GuestMiddleware($this->response, $redirectTo);
    }

    public function api(): MiddlewareInterface
    {
        return new ApiMiddleware($this->response);
    }

    /**
     * Autenticación para rutas de API — devuelve JSON 401, nunca redirige.
     * Soporta sesión (web) y Bearer token opaco (clientes externos).
     */
    public function apiAuth(): ApiAuthMiddleware
    {
        try {
            $tokenService = Container::make(ApiTokenServiceInterface::class);
        } catch (\Throwable) {
            $tokenService = null;
        }

        return new ApiAuthMiddleware($this->response, $tokenService);
    }

    /**
     * Control de roles para rutas de API — devuelve JSON 403, nunca redirige.
     *
     * @param array<string>|string $roles
     */
    public function apiRole(array|string $roles): ApiRoleMiddleware
    {
        return new ApiRoleMiddleware($this->response, $roles);
    }

    /**
     * @param array<string>|string $roles
     */
    public function role(array|string $roles): MiddlewareInterface
    {
        $rolesArray = \is_array($roles) ? $roles : [$roles];

        return new RoleMiddleware($this->response, $rolesArray);
    }

    /**
     * Middleware de autorización granular (RBAC).
     *
     * @param string $permission Código del permiso requerido (ej: 'cafe.edit')
     */
    public function can(string $permission): MiddlewareInterface
    {
        return new AuthorizationMiddleware($this->response, $permission);
    }

    /**
     * Middleware de validación de ownership sobre café.
     *
     * Valida que el Manager tenga un café asignado (user.cafe_id).
     * Uso: en rutas /manager/cafe/*
     */
    public function ownsCafe(): MiddlewareInterface
    {
        return new CafeScopeMiddleware($this->response);
    }

    /**
     * Aplica headers de seguridad OWASP a todas las respuestas.
     * Incluye CSP, HSTS, X-Frame-Options, Referrer-Policy, etc.
     */
    public function securityHeaders(): SecurityHeadersMiddleware
    {
        return new SecurityHeadersMiddleware();
    }

    /**
     * Rechaza peticiones cuyo cuerpo supere el límite en KB.
     */
    public function maxPayload(int $kb = 256): PayloadSizeMiddleware
    {
        return new PayloadSizeMiddleware($this->response, $kb);
    }

    /**
     * Construye CorsMiddleware configurado via CORS_* env vars.
     */
    public function cors(): CorsMiddleware
    {
        $raw         = (string) ($_ENV['CORS_ALLOWED_ORIGINS'] ?? '');
        $origins     = array_values(array_filter(array_map('trim', explode(',', $raw))));
        $allowed     = $origins !== [] ? $origins : ['http://localhost:8080'];
        $credentials = filter_var($_ENV['CORS_CREDENTIALS'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
        $maxAge      = (int) ($_ENV['CORS_MAX_AGE'] ?? 7200);

        return new CorsMiddleware($this->response, $allowed, $credentials, $maxAge);
    }

    /**
     * Aplica rate limiting por IP usando RateLimitingService.
     *
     * Los parámetros $max y $window son aceptados para compatibilidad de API;
     * los límites efectivos están definidos en RateLimitingService::CONFIG.
     *
     * @param string $action  Nombre de la acción (login, registration, etc.)
     * @param int    $max     Máximo de intentos (no usado — ver CONFIG en servicio)
     * @param int    $window  Ventana en segundos (no usado — ver CONFIG en servicio)
     */
    public function rateLimit(string $action, int $max = 5, int $window = 60): HttpRateLimitMiddleware
    {
        return new HttpRateLimitMiddleware($this->response, new RateLimitingService(new \App\Services\CacheService()), $action);
    }

    /**
     * Middleware de logging de requests.
     * Genera request_id, popula LogContext y loguea method/path/status/duration.
     * Colocar después de SecurityHeaders y antes de errorHandler.
     */
    public function requestLog(): RequestLogMiddleware
    {
        return new RequestLogMiddleware();
    }

    /**
     * Middleware global de manejo de errores.
     * Construye un ExceptionRendererRegistry completo con todos los renderers.
     * Debe colocarse al inicio del pipeline (después de SecurityHeaders).
     */
    public function errorHandler(): ErrorHandlerMiddleware
    {
        $registry = new ExceptionRendererRegistry();
        $registry->register(new ValidationExceptionRenderer($this->response));
        $registry->register(new NotFoundExceptionRenderer($this->response));
        $registry->register(new AuthenticationExceptionRenderer($this->response));
        $registry->register(new AuthorizationExceptionRenderer($this->response));
        $registry->register(new BusinessRuleExceptionRenderer($this->response));
        $registry->register(new RateLimitExceptionRenderer($this->response));
        $registry->register(new DatabaseExceptionRenderer($this->response));
        $registry->register(new FallbackExceptionRenderer($this->response));

        return new ErrorHandlerMiddleware($registry, $this->response);
    }
}
