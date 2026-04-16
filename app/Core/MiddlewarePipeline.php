<?php

declare(strict_types=1);

namespace App\Core;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Pipeline de middlewares PSR-15.
 *
 * Implementa el patrón Chain of Responsibility:
 * cada middleware puede procesar la request y llamar al siguiente,
 * o cortocircuitar devolviendo una respuesta directamente.
 */
final class MiddlewarePipeline implements RequestHandlerInterface
{
    /**
     * @var array<callable> Middlewares a ejecutar (PSR-15 MiddlewareInterface::process)
     */
    private array $middlewares = [];

    /**
     * @var RequestHandlerInterface|null Handler final (generalmente el Router)
     */
    private ?RequestHandlerInterface $finalHandler;

    /**
     * @param RequestHandlerInterface|null $finalHandler Handler que procesa la request tras pasar middlewares
     */
    public function __construct(?RequestHandlerInterface $finalHandler = null)
    {
        $this->finalHandler = $finalHandler;
    }

    /**
     * Añade un middleware PSR-15 al pipeline.
     *
     * @param callable|MiddlewareInterface $middleware Callable o MiddlewareInterface PSR-15
     *
     * @return $this
     */
    public function pipe(callable|MiddlewareInterface $middleware): self
    {
        $this->middlewares[] = $middleware;

        return $this;
    }

    /**
     * Procesa la request ejecutando la cadena de middlewares.
     *
     * @param ServerRequestInterface $request Request PSR-7
     *
     * @return ResponseInterface Response PSR-7
     */
    #[\Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // Si no hay middlewares, delegar directamente al handler final
        if (empty($this->middlewares)) {
            if ($this->finalHandler === null) {
                throw new \LogicException('No hay middlewares ni finalHandler configurado en el pipeline');
            }

            return $this->finalHandler->handle($request);
        }

        // Extraer primer middleware y crear nested handler para el resto
        $middleware = \array_shift($this->middlewares);

        // Crear un handler que ejecute el resto del pipeline
        $nextHandler = new class ($this->middlewares, $this->finalHandler) implements RequestHandlerInterface {
            private array $remainingMiddlewares;

            private ?RequestHandlerInterface $finalHandler;

            public function __construct(array $remainingMiddlewares, ?RequestHandlerInterface $finalHandler)
            {
                $this->remainingMiddlewares = $remainingMiddlewares;
                $this->finalHandler = $finalHandler;
            }

            #[\Override]
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $pipeline = new MiddlewarePipeline($this->finalHandler);
                foreach ($this->remainingMiddlewares as $mw) {
                    $pipeline->pipe($mw);
                }

                return $pipeline->handle($request);
            }
        };

        // Ejecutar middleware actual con el nextHandler
        // Si es MiddlewareInterface PSR-15, usar su método process()
        if ($middleware instanceof MiddlewareInterface) {
            return $middleware->process($request, $nextHandler);
        }

        // Si es callable, ejecutar directamente
        return $middleware($request, $nextHandler);
    }
}
