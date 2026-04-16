<?php

/**
 * ¿Qué pruebas aquí?
 * ExceptionRendererRegistry: registro de renderers y búsqueda por tipo + prioridad.
 * ¿Qué me quieres demostrar?
 * Que find() devuelve el renderer con mayor prioridad entre los que soportan la excepción.
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si find() ignora priority(), devuelve renderer incorrecto, no filtra por supports() o
 * retorna null cuando debería encontrar un renderer válido.
 */

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Core\Http\ExceptionRendererInterface;
use App\Core\Http\ExceptionRendererRegistry;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class ExceptionRendererRegistryTest extends TestCase
{
    public function testFindReturnsNullWhenEmpty(): void
    {
        $registry = new ExceptionRendererRegistry();
        $this->assertNull($registry->find(new \RuntimeException('test')));
    }

    public function testFindReturnsNullWhenNoneSupport(): void
    {
        $registry = new ExceptionRendererRegistry();
        $registry->register($this->makeRenderer(supports: false, priority: 10));

        $this->assertNull($registry->find(new \RuntimeException('test')));
    }

    public function testFindReturnsSupportingRenderer(): void
    {
        $renderer = $this->makeRenderer(supports: true, priority: 10);
        $registry = new ExceptionRendererRegistry();
        $registry->register($renderer);

        $this->assertSame($renderer, $registry->find(new \RuntimeException('test')));
    }

    public function testFindReturnsHighestPriorityRenderer(): void
    {
        $low = $this->makeRenderer(supports: true, priority: 5);
        $high = $this->makeRenderer(supports: true, priority: 100);

        $registry = new ExceptionRendererRegistry();
        $registry->register($low);
        $registry->register($high);

        $this->assertSame($high, $registry->find(new \RuntimeException('test')));
    }

    public function testFindIgnoresNonSupportingRendererWhenSelecting(): void
    {
        $noMatch = $this->makeRenderer(supports: false, priority: 999);
        $matching = $this->makeRenderer(supports: true, priority: 1);

        $registry = new ExceptionRendererRegistry();
        $registry->register($noMatch);
        $registry->register($matching);

        $this->assertSame($matching, $registry->find(new \RuntimeException('test')));
    }

    public function testFindDoesNotMutateOriginalOrder(): void
    {
        $first = $this->makeRenderer(supports: true, priority: 1);
        $second = $this->makeRenderer(supports: true, priority: 2);
        $third = $this->makeRenderer(supports: true, priority: 3);

        $registry = new ExceptionRendererRegistry();
        $registry->register($first);
        $registry->register($second);
        $registry->register($third);

        // Llamar dos veces — debe ser determinista
        $this->assertSame($registry->find(new \RuntimeException()), $registry->find(new \RuntimeException()));
    }

    // -----------------------------------------------------------------------
    // Helper
    // -----------------------------------------------------------------------

    private function makeRenderer(bool $supports, int $priority): ExceptionRendererInterface
    {
        return new class ($supports, $priority) implements ExceptionRendererInterface {
            public function __construct(
                private readonly bool $s,
                private readonly int  $p,
            ) {
            }

            #[\Override] public function supports(\Throwable $e): bool
            {
                return $this->s;
            }
            #[\Override] public function priority(): int
            {
                return $this->p;
            }
            #[\Override] public function render(\Throwable $e, ServerRequestInterface $req): ResponseInterface
            {
                throw new \LogicException('should not be called in registry test');
            }
        };
    }
}
