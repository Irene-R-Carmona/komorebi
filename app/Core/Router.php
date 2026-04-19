<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Http\ResponseFactory;
use App\Exceptions\RouterException;
use App\Exceptions\RouterParameterException;
use Closure;
use JsonException;
use Nyholm\Psr7\ServerRequest;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionException;
use ReflectionMethod;
use ReflectionNamedType;
use Throwable;

/**
 * Router PSR-7/PSR-15 con soporte de grupos y middlewares.
 */
final class Router implements RequestHandlerInterface
{
    /** @var array<string, array<string, array{handler: mixed, middleware: array<MiddlewareInterface>}>> */
    private array $routes = [];

    private string $controllerNamespace = 'App\\Http\\Controllers';
    private ?Closure $notFoundHandler = null;
    private string $currentPrefix = '';
    /** @var array<MiddlewareInterface> */
    private array $currentMiddleware = [];
    private ResponseFactory $response;

    public function __construct(?ResponseFactory $response = null)
    {
        $this->response = $response ?? new ResponseFactory();
    }

    /**
     * Despacha una ruta internamente para testing.
     *
     * @throws JsonException
     */
    public function dispatch(string $path, string $method = 'GET'): mixed
    {
        $request = new ServerRequest($method, $path);
        $response = $this->handle($request);

        $body = (string) $response->getBody();
        $contentType = $response->getHeaderLine('Content-Type');

        if (\str_contains($contentType, 'application/json')) {
            return \json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        }

        return $body;
    }

    public function setControllerNamespace(string $namespace): self
    {
        $this->controllerNamespace = \rtrim($namespace, '\\');

        return $this;
    }

    public function setNotFoundHandler(Closure $handler): self
    {
        $this->notFoundHandler = $handler;

        return $this;
    }

    public function get(string $uri, callable|string $handler, array $middleware = []): self
    {
        return $this->addRoute('GET', $uri, $handler, $middleware);
    }

    public function post(string $uri, callable|string $handler, array $middleware = []): self
    {
        return $this->addRoute('POST', $uri, $handler, $middleware);
    }

    public function put(string $uri, callable|string $handler, array $middleware = []): self
    {
        return $this->addRoute('PUT', $uri, $handler, $middleware);
    }

    public function patch(string $uri, callable|string $handler, array $middleware = []): self
    {
        return $this->addRoute('PATCH', $uri, $handler, $middleware);
    }

    public function delete(string $uri, callable|string $handler, array $middleware = []): self
    {
        return $this->addRoute('DELETE', $uri, $handler, $middleware);
    }

    public function options(string $uri, callable|string $handler, array $middleware = []): self
    {
        return $this->addRoute('OPTIONS', $uri, $handler, $middleware);
    }

    /**
     * @param array{prefix?: string, middleware?: array<MiddlewareInterface>} $options
     */
    public function group(array $options, callable $callback): self
    {
        $previousPrefix = $this->currentPrefix;
        $previousMiddleware = $this->currentMiddleware;

        if (isset($options['prefix'])) {
            $this->currentPrefix .= $options['prefix'];
        }

        if (isset($options['middleware'])) {
            $middlewares = \is_array($options['middleware']) ? $options['middleware'] : [$options['middleware']];
            $this->currentMiddleware = \array_merge($this->currentMiddleware, $middlewares);
        }

        $callback($this);

        $this->currentPrefix = $previousPrefix;
        $this->currentMiddleware = $previousMiddleware;

        return $this;
    }

    /**
     * PSR-15 RequestHandler
     */
    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $path = $this->normalizePath($request->getUri()->getPath());
        $method = $request->getMethod();

        $route = $this->findRoute($method, $path);

        if ($route === null) {
            return $this->handleNotFound();
        }

        // Crear pipeline con middlewares
        $handler = $this->createFinalHandler($route);
        $pipeline = new MiddlewarePipeline($handler);

        foreach ($route['middleware'] as $middleware) {
            $pipeline->pipe($middleware);
        }

        return $pipeline->handle($request);
    }

    private function addRoute(string $method, string $uri, callable|string $handler, array $middleware): self
    {
        $fullUri = $this->currentPrefix . $uri;

        /** @var array<MiddlewareInterface> $allMiddleware */
        $allMiddleware = \array_merge($this->currentMiddleware, $middleware);

        $this->routes[$method][$fullUri] = [
            'handler' => $handler,
            'middleware' => $allMiddleware,
        ];

        return $this;
    }

    /**
     * @param string $path
     * @return string
     */
    private function normalizePath(string $path): string
    {
        $parsed = \parse_url($path, PHP_URL_PATH);
        if ($parsed === false || $parsed === null) {
            $path = '/';
        } else {
            $path = $parsed;
        }

        if ($path !== '/' && \str_ends_with($path, '/')) {
            $path = \rtrim($path, '/');
        }

        return $path;
    }

    /**
     * @return array{handler: mixed, middleware: array<MiddlewareInterface>, params: array<string, string>}|null
     */
    private function findRoute(string $method, string $path): ?array
    {
        if (!isset($this->routes[$method])) {
            return null;
        }

        // Coincidencia exacta
        if (isset($this->routes[$method][$path])) {
            return [
                'handler' => $this->routes[$method][$path]['handler'],
                'middleware' => $this->routes[$method][$path]['middleware'],
                'params' => [],
            ];
        }

        // Coincidencia con parámetros
        foreach ($this->routes[$method] as $route => $definition) {
            if (!\str_contains($route, '{')) {
                continue;
            }

            $pattern = $this->compilePattern($route);

            if (\preg_match($pattern, $path, $matches)) {
                $params = \array_filter($matches, static function ($key) {
                    return \is_string($key);
                }, ARRAY_FILTER_USE_KEY);

                return [
                    'handler' => $definition['handler'],
                    'middleware' => $definition['middleware'],
                    'params' => $params,
                ];
            }
        }

        return null;
    }

    private function compilePattern(string $route): string
    {
        $pattern = \preg_replace('/\{(\w+)}/', '(?P<$1>[^/]+)', $route);

        return '#^' . $pattern . '$#';
    }

    /**
     * Crea el handler final que ejecuta el controller/closure
     *
     * @param array{handler: mixed, middleware: array<MiddlewareInterface>, params: array<string, string>} $route
     */
    private function createFinalHandler(array $route): RequestHandlerInterface
    {
        $router = $this;

        return new class ($router, $route) implements RequestHandlerInterface {
            private Router $router;
            /** @var array{handler: mixed, middleware: array<MiddlewareInterface>, params: array<string, string>} */
            private array $route;

            public function __construct(Router $router, array $route)
            {
                $this->router = $router;
                $this->route = $route;
            }

            #[Override]
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                // Añadir parámetros a la request
                foreach ($this->route['params'] as $key => $value) {
                    $request = $request->withAttribute($key, $value);
                }

                return $this->router->executeHandler(
                    $this->route['handler'],
                    $request,
                    $this->route['params']
                );
            }
        };
    }

    /**
     * @throws RouterException
     * @throws Throwable
     */
    public function executeHandler(callable|string $handler, ServerRequestInterface $request, array $params): ResponseInterface
    {
        if ($handler instanceof Closure || (\is_callable($handler) && !\is_string($handler))) {
            $result = $handler($request);

            return $this->convertToResponse($result);
        }

        if (\str_contains($handler, '@')) {
            return $this->executeController($handler, $request, $params);
        }

        throw new RouterException('Handler inválido');
    }

    /**
     * @param string $handler
     * @param ServerRequestInterface $request
     * @param array $params
     * @return ResponseInterface
     * @throws RouterException
     * @throws RouterParameterException
     * @throws JsonException
     * @throws ReflectionException
     */
    private function executeController(string $handler, ServerRequestInterface $request, array $params): ResponseInterface
    {
        [$controllerName, $method] = \explode('@', $handler, 2);

        $controllerClass = \str_starts_with($controllerName, '\\')
            ? \ltrim($controllerName, '\\')
            : $this->controllerNamespace . '\\' . $controllerName;

        if (!\class_exists($controllerClass)) {
            // Fallback para tests que declaran controllers en espacio global
            if (\class_exists($controllerName)) {
                $controllerClass = $controllerName;
            } else {
                throw new RouterException("Controller no encontrado: $controllerClass");
            }
        }

        // Usar Container para instanciar con inyección de dependencias
        try {
            $controller = Container::make($controllerClass);
        } catch (Throwable $e) {
            throw new RouterException("Error al instanciar controller $controllerClass: " . $e->getMessage(), previous: $e);
        }

        if (!\method_exists($controller, $method)) {
            throw new RouterException("Método no encontrado: $controllerClass@$method");
        }

        $args = $this->resolveMethodArguments($controller, $method, $request, $params);

        // Capturar output buffer para controladores que hacen echo directamente
        \ob_start();

        try {
            $result = $controller->$method(...$args);
            $output = \ob_get_clean();
        } catch (Throwable $e) {
            \ob_end_clean();
            throw $e;
        }

        // Si el controlador devolvió null pero generó output, convertir a HTML response
        if ($result === null && $output !== '' && $output !== false) {
            return $this->response->html($output);
        }

        return $this->convertToResponse($result);
    }

    /**
     * @throws ReflectionException
     * @throws RouterParameterException
     */
    private function resolveMethodArguments(object $controller, string $method, ServerRequestInterface $request, array $params): array
    {
        $reflection = new ReflectionMethod($controller, $method);
        $args = [];

        foreach ($reflection->getParameters() as $param) {
            $type = $param->getType();
            $paramName = $param->getName();

            if ($type instanceof ReflectionNamedType && $type->getName() === ServerRequestInterface::class) {
                $args[] = $request;
                continue;
            }

            if (\array_key_exists($paramName, $params)) {
                $value = $params[$paramName];

                // Cast según tipo declarado (int/float/bool)
                if ($type instanceof ReflectionNamedType && $type->isBuiltin()) {
                    $typeName = $type->getName();
                    if ($typeName === 'int') {
                        $value = (int) $value;
                    } elseif ($typeName === 'float') {
                        $value = (float) $value;
                    } elseif ($typeName === 'bool') {
                        $value = \filter_var($value, FILTER_VALIDATE_BOOLEAN);
                    }
                }

                $args[] = $value;
                continue;
            }

            // Si el parámetro espera un array y no existe en $params, pasar el array de params
            if ($type instanceof ReflectionNamedType && $type->getName() === 'array') {
                $args[] = $params;
                continue;
            }

            if ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
                continue;
            }

            if ($type && $type->allowsNull()) {
                $args[] = null;
                continue;
            }

            throw new RouterParameterException("Parámetro requerido: $paramName");
        }

        return $args;
    }

    /**
     * @throws RouterException
     * @throws JsonException
     */
    private function convertToResponse(mixed $result): ResponseInterface
    {
        if ($result instanceof ResponseInterface) {
            return $result;
        }

        if (\is_array($result)) {
            return $this->response->json($result);
        }

        if (\is_string($result)) {
            return $this->response->html($result);
        }

        if ($result === null) {
            return $this->response->createResponse(204);
        }

        throw new RouterException('Handler debe retornar ResponseInterface, array, string o null');
    }

    private function handleNotFound(): ResponseInterface
    {
        if ($this->notFoundHandler !== null) {
            $result = ($this->notFoundHandler)();

            return $this->convertToResponse($result);
        }

        return $this->response->html('<h1>404 - Not Found</h1>', 404);
    }
}
