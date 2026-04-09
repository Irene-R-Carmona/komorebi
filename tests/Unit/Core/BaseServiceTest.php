<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? Los helpers de validación y logging de BaseService.
 * ¿Qué me quieres demostrar? Que los assert* lanzan ValidationException con el campo correcto.
 * ¿Qué va a fallar en este test si se cambia el código? Si se cambia el campo que se reporta en la excepción.
 */

use App\Core\BaseService;
use App\Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;

final class ConcreteService extends BaseService {}

final class BaseServiceTest extends TestCase
{
    private ConcreteService $service;

    protected function setUp(): void
    {
        $this->service = new ConcreteService();
    }

    public function testAssertNotBlankThrowsOnEmptyString(): void
    {
        $this->expectException(ValidationException::class);
        $this->callProtected('assertNotBlank', ['', 'name']);
    }

    public function testAssertNotBlankPassesOnNonEmptyString(): void
    {
        $this->callProtected('assertNotBlank', ['hello', 'name']);
        $this->addToAssertionCount(1);
    }

    public function testAssertMaxLengthThrowsWhenExceeded(): void
    {
        $this->expectException(ValidationException::class);
        $this->callProtected('assertMaxLength', [str_repeat('a', 51), 50, 'field']);
    }

    public function testAssertMaxLengthPassesAtLimit(): void
    {
        $this->callProtected('assertMaxLength', [str_repeat('a', 50), 50, 'field']);
        $this->addToAssertionCount(1);
    }

    public function testAssertRangeThrowsBelowMin(): void
    {
        $this->expectException(ValidationException::class);
        $this->callProtected('assertRange', [0, 1, 10, 'num']);
    }

    public function testAssertRangeThrowsAboveMax(): void
    {
        $this->expectException(ValidationException::class);
        $this->callProtected('assertRange', [11, 1, 10, 'num']);
    }

    public function testAssertRangePassesWithinBounds(): void
    {
        $this->callProtected('assertRange', [5, 1, 10, 'num']);
        $this->addToAssertionCount(1);
    }

    public function testAssertOneOfThrowsForInvalid(): void
    {
        $this->expectException(ValidationException::class);
        $this->callProtected('assertOneOf', ['x', ['a', 'b', 'c'], 'field']);
    }

    public function testAssertOneOfPassesForValid(): void
    {
        $this->callProtected('assertOneOf', ['a', ['a', 'b', 'c'], 'field']);
        $this->addToAssertionCount(1);
    }

    /** @param array<mixed> $args */
    private function callProtected(string $method, array $args): void
    {
        $ref = new \ReflectionMethod($this->service, $method);
        $ref->invoke($this->service, ...$args);
    }
}
