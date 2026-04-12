<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Lanzada por CircuitBreaker::call() cuando el circuito está OPEN
 * y el timeout de recuperación aún no ha expirado.
 */
final class CircuitOpenException extends RuntimeException {}
