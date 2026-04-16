<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * ¿Qué me quieres demostrar?
 * ¿Qué va a fallar en este test si se cambia el código?
 */
use App\Core\Container;
use App\Services\NewsletterService;
use PHPUnit\Framework\TestCase;

/**
 * Test del DI Container - Auto-wiring.
 *
 * Verifica que el Container puede:
 * - Resolver dependencias automáticamente
 * - Instanciar servicios con type-hints
 * - Singletons compartir instancia
 */
final class ContainerTest extends TestCase
{
    protected function tearDown(): void
    {
        // Limpiar container entre tests
        Container::reset();
    }

    /**
     * @throws ReflectionException
     */
    public function testSingletonReturnsSameInstance(): void
    {
        Container::singleton(PDO::class, fn () => $this->createStub(PDO::class));

        $instance1 = Container::make(PDO::class);
        $instance2 = Container::make(PDO::class);

        $this->assertSame($instance1, $instance2, 'Singleton debe retornar la misma instancia');
    }

    /**
     * @throws ReflectionException
     */
    public function testBindReturnsNewInstance(): void
    {
        Container::bind(PDO::class, fn () => $this->createStub(PDO::class));

        $instance1 = Container::make(PDO::class);
        $instance2 = Container::make(PDO::class);

        $this->assertNotSame($instance1, $instance2, 'Bind debe retornar instancias diferentes');
    }

    /**
     * @throws ReflectionException
     */
    public function testAutoWiringResolvesConstructorDependencies(): void
    {
        // Mock PDO
        $mockPdo = $this->createStub(PDO::class);
        Container::instance(PDO::class, $mockPdo);

        // NewsletterService tiene PDO en su constructor
        $service = Container::make(NewsletterService::class);

        $this->assertInstanceOf(NewsletterService::class, $service);
    }

    /**
     * @throws ReflectionException
     */
    public function testAliasResolvesToRealClass(): void
    {
        $mockPdo = $this->createStub(PDO::class);
        Container::instance(PDO::class, $mockPdo);
        Container::alias('db', PDO::class);

        $resolved = Container::make('db');

        $this->assertSame($mockPdo, $resolved, 'Alias debe resolver a la clase real');
    }

    public function testContainerHasReturnsTrueForRegisteredBindings(): void
    {
        Container::bind(PDO::class, fn () => $this->createStub(PDO::class));

        $this->assertTrue(Container::getInstance()->has(PDO::class));
    }

    public function testContainerHasReturnsFalseForUnregistered(): void
    {
        $this->assertFalse(Container::getInstance()->has('NonExistentClass'));
    }
}
