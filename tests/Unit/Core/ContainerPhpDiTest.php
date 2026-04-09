<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * Tests de la integración del Container como lazy-build shim sobre PHP-DI v7.
 *
 * ¿Qué me quieres demostrar?
 * Que la API pública de Container (make, singleton, instance, reset, has, call)
 * funciona correctamente delegando a PHP-DI internamente.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se rompe la lógica del shim (ensamblado lazy, singleton scope,
 * pre-built instances, reset para tests, o call con autowiring).
 */

namespace Tests\Unit\Core;

use App\Core\Container;
use PHPUnit\Framework\TestCase;

// Fixtures locales para los tests
final class DummyServiceA
{
    public int $value = 42;
}

final class DummyServiceB
{
    public function __construct(public readonly DummyServiceA $a) {}
}

final class ContainerPhpDiTest extends TestCase
{
    protected function setUp(): void
    {
        Container::reset();
    }

    protected function tearDown(): void
    {
        Container::reset();
    }

    public function testSingletonReturnsAlwaysSameInstance(): void
    {
        Container::singleton(DummyServiceA::class, fn() => new DummyServiceA());

        $first  = Container::make(DummyServiceA::class);
        $second = Container::make(DummyServiceA::class);

        $this->assertInstanceOf(DummyServiceA::class, $first);
        $this->assertSame($first, $second, 'El singleton debe devolver siempre la misma instancia');
    }

    public function testMakeResolvesConcreteClassWithAutowiring(): void
    {
        Container::singleton(DummyServiceA::class, fn() => new DummyServiceA());

        /** @var DummyServiceB $b */
        $b = Container::make(DummyServiceB::class);

        $this->assertInstanceOf(DummyServiceB::class, $b);
        $this->assertInstanceOf(DummyServiceA::class, $b->a);
        $this->assertSame(42, $b->a->value);
    }

    public function testInstanceRegisteredBeforeBuildIsReturned(): void
    {
        $preBuilt = new DummyServiceA();
        $preBuilt->value = 99;

        Container::instance(DummyServiceA::class, $preBuilt);

        $resolved = Container::make(DummyServiceA::class);

        $this->assertSame($preBuilt, $resolved);
        $this->assertSame(99, $resolved->value);
    }

    public function testResetAllowsRebuildWithFreshDefinitions(): void
    {
        Container::singleton(DummyServiceA::class, fn() => new DummyServiceA());
        $first = Container::make(DummyServiceA::class);

        Container::reset();

        Container::singleton(DummyServiceA::class, fn() => new DummyServiceA());
        $second = Container::make(DummyServiceA::class);

        $this->assertNotSame($first, $second, 'Tras reset() debe construir instancias nuevas');
    }

    public function testHasReturnsTrueForRegisteredClass(): void
    {
        Container::singleton(DummyServiceA::class, fn() => new DummyServiceA());

        $this->assertTrue(Container::getInstance()->has(DummyServiceA::class));
    }

    public function testHasReturnsTrueForAutoWirableConcreteClass(): void
    {
        // Clase concreta no registrada explícitamente — PHP-DI la puede autowire
        $this->assertTrue(Container::getInstance()->has(DummyServiceA::class));
    }

    public function testCallInvokesMethodWithAutowiredDependencies(): void
    {
        Container::singleton(DummyServiceA::class, fn() => new DummyServiceA());

        $obj = new class {
            public function compute(DummyServiceA $a, int $multiplier = 1): int
            {
                return $a->value * $multiplier;
            }
        };

        $result = Container::call($obj, 'compute', ['multiplier' => 3]);

        $this->assertSame(126, $result); // 42 * 3
    }
}
