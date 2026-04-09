<?php

declare(strict_types=1);

namespace App\Core\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Contrato para renderizar excepciones como respuestas HTTP PSR-7.
 *
 * Cada renderer es responsable de:
 * - Indicar qué tipos de excepción soporta (supports)
 * - Declarar su prioridad relativa (mayor = preferido en caso de conflicto)
 * - Convertir la excepción en una ResponseInterface
 */
interface ExceptionRendererInterface
{
    /**
     * Indica si este renderer puede manejar la excepción dada.
     */
    public function supports(\Throwable $e): bool;

    /**
     * Prioridad de este renderer. Mayor número = mayor prioridad.
     * Se usa cuando varios renderers soportan la misma excepción.
     */
    public function priority(): int;

    /**
     * Convierte la excepción en una respuesta HTTP PSR-7.
     */
    public function render(\Throwable $e, ServerRequestInterface $request): ResponseInterface;
}
