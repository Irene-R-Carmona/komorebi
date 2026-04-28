<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * ¿Qué me quieres demostrar?
 * ¿Qué va a fallar en este test si se cambia el código?
 */

use App\Core\Container;
use App\Repositories\Contracts\NewsletterSubscriptionRepositoryInterface;
use App\Services\NewsletterService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Test del DI Container - Auto-wiring.
 *
 * Verifica que el Container puede:
 * - Resolver dependencias automáticamente
 * - Instanciar servicios con type-hints
 * - Singletons compartir instancia
 */
#[CoversClass(Container::class)]
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
        Container::singleton(PDO::class, fn() => $this->createStub(PDO::class));

        $instance1 = Container::make(PDO::class);
        $instance2 = Container::make(PDO::class);

        $this->assertSame($instance1, $instance2, 'Singleton debe retornar la misma instancia');
    }

    /**
     * @throws ReflectionException
     */
    public function testBindReturnsNewInstance(): void
    {
        Container::bind(PDO::class, fn() => $this->createStub(PDO::class));

        $instance1 = Container::make(PDO::class);
        $instance2 = Container::make(PDO::class);

        $this->assertNotSame($instance1, $instance2, 'Bind debe retornar instancias diferentes');
    }

    /**
     * @throws ReflectionException
     */
    public function testAutoWiringResolvesConstructorDependencies(): void
    {
        Container::instance(
            NewsletterSubscriptionRepositoryInterface::class,
            $this->createStub(NewsletterSubscriptionRepositoryInterface::class)
        );

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
        Container::bind(PDO::class, fn() => $this->createStub(PDO::class));

        $this->assertTrue(Container::getInstance()->has(PDO::class));
    }

    public function testContainerHasReturnsFalseForUnregistered(): void
    {
        $this->assertFalse(Container::getInstance()->has('NonExistentClass'));
    }

    /**
     * Test: enableCompilation activa el path; ensureBuild() crea el directorio y
     * compila el container con las definiciones internas (DI\value, compilables por PHP-DI).
     * Se activa haciendo make() de un símbolo que siempre se resuelve internamente.
     */
    public function testEnableCompilationBuildsContainerWithoutErrors(): void
    {
        $tmpDir = \sys_get_temp_dir() . '/container_compile_test_' . \uniqid('', true);

        Container::enableCompilation($tmpDir);

        // Container::class siempre se define como DI\value($self) en ensureBuild(),
        // forzando la ruta de compilación sin closures incompatibles.
        $result = Container::make(Container::class);

        $this->assertInstanceOf(Container::class, $result);
        $this->assertDirectoryExists($tmpDir);

        // Limpieza: eliminar el directorio generado
        $files = \glob($tmpDir . '/*') ?: [];
        foreach ($files as $file) {
            \unlink($file);
        }
        \rmdir($tmpDir);
    }
}
