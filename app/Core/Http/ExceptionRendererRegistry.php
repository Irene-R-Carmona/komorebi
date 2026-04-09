<?php

declare(strict_types=1);

namespace App\Core\Http;

/**
 * Registro de renderers para excepciones.
 *
 * Permite registrar múltiples ExceptionRendererInterface y encontrar
 * el más adecuado para una excepción dada, ordenado por prioridad descendente.
 */
final class ExceptionRendererRegistry
{
    /** @var list<ExceptionRendererInterface> */
    private array $renderers = [];

    /**
     * Registra un renderer en el registry.
     */
    public function register(ExceptionRendererInterface $renderer): void
    {
        $this->renderers[] = $renderer;
    }

    /**
     * Encuentra el renderer con mayor prioridad que soporte la excepción.
     * Retorna null si ninguno la soporta.
     */
    public function find(\Throwable $e): ?ExceptionRendererInterface
    {
        $matches = \array_filter($this->renderers, static fn($r) => $r->supports($e));

        if ($matches === []) {
            return null;
        }

        \usort($matches, static fn($a, $b) => $b->priority() <=> $a->priority());

        return $matches[0];
    }
}
