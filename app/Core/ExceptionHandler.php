<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Http\ProblemDetails;
use App\Exceptions\AuthenticationException;
use App\Exceptions\AuthorizationException;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\ConfigurationException;
use App\Exceptions\DatabaseException;
use App\Exceptions\ExternalServiceException;
use App\Exceptions\MiddlewareException;
use App\Exceptions\NotFoundException;
use App\Exceptions\PhpErrorException;
use App\Exceptions\RateLimitException;
use App\Exceptions\RouterException;
use App\Exceptions\RouterParameterException;
use App\Exceptions\ValidationException;
use ErrorException;
use JsonException;
use Throwable;

/**
 * Manejador Global de Excepciones
 *
 * Convierte excepciones en respuestas HTTP apropiadas (JSON o HTML)
 * según el contexto de la petición y el tipo de excepción.
 */
final class ExceptionHandler
{
    /**
     * Maneja una excepción y devuelve respuesta apropiada
     *
     * @param Throwable $exception Excepción a manejar
     * @param string|null $context Contexto adicional (nombre del controlador/servicio)
     *
     * @return void
     * @throws JsonException
     */
    public static function handle(Throwable $exception, ?string $context = null): void
    {
        // Registrar la excepción
        ExceptionLogger::log($exception, $context);
        ExceptionLogger::recordMetrics($exception);

        if (\class_exists(\Sentry\State\HubInterface::class)) {
            \Sentry\captureException($exception);
        }

        // Detectar si es petición API/AJAX
        $isApiRequest = self::isApiRequest();

        // Manejar según tipo de excepción
        match (true) {
            $exception instanceof ValidationException => self::handleValidation($exception, $isApiRequest),
            $exception instanceof NotFoundException => self::handleNotFound($exception, $isApiRequest),
            $exception instanceof AuthenticationException => self::handleAuthentication($exception, $isApiRequest),
            $exception instanceof AuthorizationException => self::handleAuthorization($exception, $isApiRequest),
            $exception instanceof BusinessRuleException => self::handleBusinessRule($exception, $isApiRequest),
            $exception instanceof RateLimitException => self::handleRateLimit($exception, $isApiRequest),
            $exception instanceof RouterParameterException => self::handleRouterParameter($exception, $isApiRequest),
            $exception instanceof RouterException => self::handleRouter($exception, $isApiRequest),
            $exception instanceof MiddlewareException => self::handleMiddleware($exception, $isApiRequest),
            $exception instanceof DatabaseException => self::handleDatabase($exception, $isApiRequest),
            $exception instanceof ConfigurationException => self::handleConfiguration($exception, $isApiRequest),
            $exception instanceof ExternalServiceException => self::handleExternalService($exception, $isApiRequest),
            default => self::handleGeneric($exception, $isApiRequest)
        };
    }

    /**
     * Detecta si es una petición API/AJAX
     *
     * @return boolean True si la petición espera/usa JSON
     */
    private static function isApiRequest(): bool
    {
        // Obtener valores relevantes
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        $uri = $_SERVER['REQUEST_URI'] ?? '';

        return \str_contains($accept, 'application/json') ||
            \str_contains($contentType, 'application/json') ||
            \strtolower($requestedWith) === 'xmlhttprequest' ||
            \str_starts_with($uri, '/api/');
    }

    // ------------------------------------------------------------
    // Manejadores por Tipo de Excepción
    // ------------------------------------------------------------

    /**
     * Maneja ValidationException (422)
     *
     * @throws JsonException
     */
    private static function handleValidation(ValidationException $exception, bool $isApi): void
    {
        if ($isApi) {
            self::sendProblem(
                $exception->getMessage(),
                'validation_error',
                $exception->getHttpCode(),
                ['errors' => $exception->getErrors()]
            );
        } else {
            // Guardar errores en sesión para mostrarlos
            Flash::error($exception->getMessage());
            foreach ($exception->getErrors() as $field => $error) {
                Flash::error("$field: $error");
            }

            if (!\headers_sent()) {
                @\http_response_code($exception->getHttpCode());
            }
            \header('Location: ' . View::safeReferer());
            exit;
        }
    }

    /**
     * Maneja NotFoundException (404)
     *
     * @throws JsonException
     */
    private static function handleNotFound(NotFoundException $exception, bool $isApi): void
    {
        if ($isApi) {
            self::sendProblem(
                $exception->getMessage(),
                'not_found',
                $exception->getHttpCode(),
                [
                    'resource_type' => $exception->getResourceType(),
                    'resource_id' => $exception->getResourceId(),
                ]
            );
        } else {
            if (!\headers_sent()) {
                @\http_response_code(404);
            }
            View::render('errors/404', [
                'message' => $exception->getMessage(),
                'resource_type' => $exception->getResourceType(),
            ], [], 'errors');
        }
    }

    /**
     * Maneja AuthenticationException (401)
     *
     * @throws JsonException
     */
    private static function handleAuthentication(AuthenticationException $exception, bool $isApi): void
    {
        if ($isApi) {
            self::sendProblem(
                $exception->getMessage(),
                'unauthorized',
                $exception->getHttpCode(),
                ['reason' => $exception->getReason()]
            );
        } else {
            Flash::error($exception->getMessage());
            Session::set('redirect_after_login', $_SERVER['REQUEST_URI'] ?? '/');

            \header('Location: /auth/login');
            exit;
        }
    }

    /**
     * Maneja AuthorizationException (403)
     *
     * @throws JsonException
     */
    private static function handleAuthorization(AuthorizationException $exception, bool $isApi): void
    {
        if ($isApi) {
            self::sendProblem(
                $exception->getMessage(),
                'forbidden',
                $exception->getHttpCode(),
                [
                    'permission' => $exception->getPermission(),
                    'resource' => $exception->getResource(),
                ]
            );
        } else {
            if (!\headers_sent()) {
                @\http_response_code(403);
            }
            View::render('errors/403', [
                'message' => $exception->getMessage(),
                'permission' => $exception->getPermission(),
            ], [], 'errors');
        }
    }

    /**
     * Maneja BusinessRuleException (400)
     *
     * @throws JsonException
     */
    private static function handleBusinessRule(BusinessRuleException $exception, bool $isApi): void
    {
        if ($isApi) {
            self::sendProblem(
                $exception->getMessage(),
                'business_rule',
                $exception->getHttpCode(),
                [
                    'rule_code' => $exception->getRuleCode(),
                    'context' => $exception->getContext(),
                ]
            );
        } else {
            Flash::error($exception->getMessage());

            if (!\headers_sent()) {
                @\http_response_code($exception->getHttpCode());
            }
            \header('Location: ' . View::safeReferer());
            exit;
        }
    }

    /**
     * Maneja RateLimitException (429)
     *
     * @throws JsonException
     */
    private static function handleRateLimit(RateLimitException $exception, bool $isApi): void
    {
        // Agregar header Retry-After
        \header('Retry-After: ' . $exception->getRetryAfter());

        if ($isApi) {
            self::sendProblem(
                $exception->getMessage(),
                'rate_limited',
                $exception->getHttpCode(),
                [
                    'retry_after' => $exception->getRetryAfter(),
                    'limit' => $exception->getLimit(),
                    'action' => $exception->getAction(),
                ]
            );
        } else {
            if (!\headers_sent()) {
                @\http_response_code($exception->getHttpCode());
            }
            View::render('errors/429', [
                'titulo' => '429 - Demasiadas solicitudes',
                'message' => $exception->getMessage(),
                'retryAfter' => $exception->getRetryAfter(),
            ], [], 'errors');
        }
    }

    // Manejadores para excepciones de Router/Middleware

    /**
     * Maneja RouterParameterException (400)
     *
     * @throws JsonException
     */
    private static function handleRouterParameter(RouterParameterException $exception, bool $isApi): void
    {
        if ($isApi) {
            self::sendProblem(
                $exception->getMessage(),
                'invalid_param',
                $exception->getHttpCode(),
                ['param' => $exception->getParamName()]
            );
        } else {
            if (!\headers_sent()) {
                @\http_response_code($exception->getHttpCode());
            }
            View::render('errors/400', ['message' => $exception->getMessage()], [], 'errors');
        }
    }

    /**
     * Maneja RouterException (500)
     *
     * @throws JsonException
     */
    private static function handleRouter(RouterException $exception, bool $isApi): void
    {
        if ($isApi) {
            self::sendProblem($exception->getMessage(), 'router_error', $exception->getHttpCode());
        } else {
            if (!\headers_sent()) {
                @\http_response_code(500);
            }
            View::render('errors/500', ['message' => $exception->getMessage()], [], 'errors');
        }
    }

    /**
     * Maneja MiddlewareException (500)
     *
     * @throws JsonException
     */
    private static function handleMiddleware(MiddlewareException $exception, bool $isApi): void
    {
        if ($isApi) {
            self::sendProblem(
                $exception->getMessage(),
                'middleware_error',
                $exception->getHttpCode(),
                ['middleware' => $exception->getMiddleware() ?? 'unknown']
            );
        } else {
            if (!\headers_sent()) {
                @\http_response_code(500);
            }
            View::render('errors/500', ['message' => $exception->getMessage()], [], 'errors');
        }
    }

    /**
     * Maneja DatabaseException (500)
     *
     * @throws JsonException
     */
    private static function handleDatabase(DatabaseException $exception, bool $isApi): void
    {
        // NUNCA exponer detalles de BD en producción
        $isDebug = Env::get('APP_DEBUG', '') ?: (Env::get('APP_ENV', '') !== 'production');
        $message = $isDebug
            ? $exception->getMessage()
            : 'Error interno del servidor. Por favor, intenta de nuevo.';

        if ($isApi) {
            self::sendProblem($message, 'database_error', $exception->getHttpCode());
        } else {
            if (!\headers_sent()) {
                @\http_response_code(500);
            }
            View::render('errors/500', [
                'message' => $message,
                'show_details' => $isDebug,
            ], [], 'errors');
        }
    }

    /**
     * Maneja ConfigurationException (500)
     *
     * @throws JsonException
     */
    private static function handleConfiguration(ConfigurationException $exception, bool $isApi): void
    {
        // NUNCA exponer detalles de configuración en producción
        $isDebug = Env::get('APP_DEBUG', '') ?: (Env::get('APP_ENV', '') !== 'production');
        $message = $isDebug
            ? $exception->getMessage()
            : 'Error de configuración del sistema. Contacta al administrador.';

        if ($isApi) {
            self::sendProblem($message, 'configuration_error', $exception->getHttpCode());
        } else {
            if (!\headers_sent()) {
                @\http_response_code(500);
            }
            View::render('errors/500', [
                'message' => $message,
                'show_details' => false, // Nunca mostrar config
            ], [], 'errors');
        }
    }

    /**
     * Maneja ExternalServiceException (503)
     *
     * @throws JsonException
     */
    private static function handleExternalService(ExternalServiceException $exception, bool $isApi): void
    {
        if ($isApi) {
            self::sendProblem(
                $exception->getMessage(),
                'external_service',
                $exception->getHttpCode(),
                ['service' => $exception->getServiceName()]
            );
        } else {
            if (!\headers_sent()) {
                @\http_response_code(503);
            }
            View::render('errors/503', [
                'titulo' => '503 - Servicio no disponible',
                'message' => $exception->getMessage(),
                'service' => $exception->getServiceName(),
            ], [], 'errors');
        }
    }

    /**
     * Maneja excepciones genéricas no capturadas
     *
     * @throws JsonException
     */
    private static function handleGeneric(Throwable $exception, bool $isApi): void
    {
        $isDebug = Env::get('APP_DEBUG', '') ?: (Env::get('APP_ENV', '') !== 'production');
        $errorId = ExceptionLogger::generateErrorId();

        $message = $isDebug
            ? $exception->getMessage()
            : "Ha ocurrido un error inesperado. ID: $errorId";

        if ($isApi) {
            self::sendProblem(
                $message,
                'server_error',
                500,
                $isDebug ? [
                    'type' => \get_class($exception),
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                    'error_id' => $errorId,
                ] : ['error_id' => $errorId]
            );
        } else {
            if (!\headers_sent()) {
                @\http_response_code(500);
            }
            View::render('errors/500', [
                'message' => $message,
                'error_id' => $errorId,
                'show_details' => $isDebug,
                'exception' => $isDebug ? (string) $exception : null,
            ], [], 'errors');
        }
    }

    // ------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------

    /**
     * Registra el manejador global de excepciones y errores.
     *
     * - Establece el handler de excepciones personalizado.
     * - Convierte errores en excepciones controladas.
     * - Registra un shutdown function para errores fatales.
     *
     * @throws JsonException
     * @throws ErrorException
     *
     * @return void
     */
    public static function register(): void
    {
        \set_exception_handler([self::class, 'handle']);

        \set_error_handler(static function ($severity, $message, $file, $line) {
            // Registrar el error antes de convertirlo en excepción
            try {
                ExceptionLogger::logQuick(new ErrorException($message, 0, $severity, $file, $line), 'php_error', ['severity' => $severity]);
            } catch (Throwable) {
                // Fallback silencioso si logging falla
            }

            if (!(\error_reporting() & $severity)) {
                return false;
            }

            throw new PhpErrorException($message, 0, $severity, $file, $line);
        });

        \register_shutdown_function(static function () {
            $error = \error_get_last();

            if ($error !== null && \in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                self::handle(
                    new PhpErrorException(
                        $error['message'],
                        0,
                        $error['type'],
                        $error['file'],
                        $error['line']
                    ),
                    'FATAL_ERROR'
                );
            }
        });
    }

    /**
     * Obtiene información de debug de una excepción
     *
     * @param Throwable $exception
     *
     * @return array
     */
    public static function getDebugInfo(Throwable $exception): array
    {
        return [
            'message' => $exception->getMessage(),
            'type' => \get_class($exception),
            'code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
        ];
    }

    /**
     * Emite una respuesta RFC 9457 Problem Details y termina la ejecución.
     *
     * @param array<string, mixed> $extensions Campos de extensión RFC 9457
     */
    private static function sendProblem(
        string $detail,
        string $code,
        int $status,
        array $extensions = [],
    ): never {
        if (!\headers_sent()) {
            \http_response_code($status);
            \header('Content-Type: application/problem+json; charset=UTF-8');
        }

        $body = ProblemDetails::fromResult(Result::fail($detail, $code), $status);

        if ($extensions !== []) {
            $body += $extensions;
        }

        echo \json_encode($body, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        exit;
    }
}
