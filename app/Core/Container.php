<?php

declare(strict_types=1);

namespace App\Core;

use Closure;
use DI\ContainerBuilder;
use Override;
use Psr\Container\ContainerInterface;
use RuntimeException;

/**
 * Dependency Injection Container (PSR-11 compatible)
 *
 * Façade estática sobre PHP-DI v7 con estrategia lazy-build:
 * - Las definiciones se acumulan mediante singleton()/bind()/instance()/alias()
 * - PHP-DI se construye en el primer make() (esto ocurre durante boot() de los Providers)
 * - Toda la resolución, autowiring y singleton-scope la gestiona PHP-DI
 *
 * API pública idéntica a la versión anterior: cero cambios en Providers,
 * bootstrap, controllers ni rutas.
 */
final class Container implements ContainerInterface
{
    private static ?self $instance = null;

    /**
     * Definiciones pendientes: abstract => [closure, isSingleton]
     * @var array<string, array{0: Closure, 1: bool}>
     */
    private static array $pendingDefinitions = [];

    /**
     * Closures prototype (bind): se invocan en cada get(), sin cache.
     * @var array<string, Closure>
     */
    private static array $prototypeClosures = [];

    /**
     * Instancias pre-construidas (instance() / self-registration)
     * @var array<string, object>
     */
    private static array $pendingInstances = [];

    /**
     * Aliases pendientes: alias => abstract
     * @var array<string, string>
     */
    private static array $pendingAliases = [];

    private static ?\DI\Container $phpdi = null;
    private static bool $built = false;

    private function __construct()
    {
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Registrar binding (factory, no singleton — nueva instancia cada llamada).
     */
    public static function bind(string $abstract, ?Closure $concrete = null): void
    {
        $concrete ??= static fn () => throw new RuntimeException("No hay factory concreta para: $abstract");
        self::$prototypeClosures[$abstract] = $concrete;
    }

    /**
     * Registrar singleton (instancia única compartida).
     */
    public static function singleton(string $abstract, ?Closure $concrete = null): void
    {
        $concrete ??= static function () use ($abstract): object {
            throw new RuntimeException("No hay factory concreta para: $abstract");
        };
        self::$pendingDefinitions[$abstract] = [$concrete, true];
    }

    /**
     * Registrar instancia ya creada.
     * Puede llamarse antes o después del build; siempre tiene prioridad sobre PHP-DI.
     */
    public static function instance(string $abstract, object $instance): void
    {
        self::$pendingInstances[$abstract] = $instance;
    }

    /**
     * Registrar alias (nombre corto → clase completa).
     */
    public static function alias(string $alias, string $abstract): void
    {
        self::$pendingAliases[$alias] = $abstract;
    }

    /**
     * Resolver una clase o interfaz. Punto de entrada principal.
     */
    public static function make(string $abstract): mixed
    {
        return self::getInstance()->get($abstract);
    }

    /**
     * PSR-11: get(). Construye PHP-DI si aún no está construido.
     */
    #[Override]
    public function get(string $id): mixed
    {
        // Resolver alias antes de todo
        $id = self::$pendingAliases[$id] ?? $id;

        // Instancias pre-registradas tienen prioridad absoluta (útil en tests)
        if (isset(self::$pendingInstances[$id])) {
            return self::$pendingInstances[$id];
        }

        // Prototype bindings: nueva instancia cada llamada, sin pasar por PHP-DI
        if (isset(self::$prototypeClosures[$id])) {
            return (self::$prototypeClosures[$id])();
        }

        self::ensureBuild();

        return self::$phpdi->get($id);
    }

    /**
     * PSR-11: has().
     */
    #[Override]
    public function has(string $id): bool
    {
        $id = self::$pendingAliases[$id] ?? $id;

        if (
            isset(self::$pendingInstances[$id])
            || isset(self::$pendingDefinitions[$id])
            || isset(self::$prototypeClosures[$id])
        ) {
            return true;
        }

        if (self::$built) {
            return self::$phpdi->has($id);
        }

        return \class_exists($id);
    }

    /**
     * Limpiar container — destruye PHP-DI y reinicia todo (para testing).
     */
    public static function reset(): void
    {
        self::$instance = null;
        self::$pendingDefinitions = [];
        self::$prototypeClosures = [];
        self::$pendingInstances = [];
        self::$pendingAliases = [];
        self::$phpdi = null;
        self::$built = false;
    }

    /**
     * Llamar método con auto-wiring de parámetros (usado por Router para controllers).
     *
     * @param array<string, mixed> $additionalParams Parámetros adicionales (ej: route params)
     */
    public static function call(object|string $classOrInstance, string $method, array $additionalParams = []): mixed
    {
        self::ensureBuild();

        $instance = \is_string($classOrInstance)
            ? self::make($classOrInstance)
            : $classOrInstance;

        return self::$phpdi->call([$instance, $method], $additionalParams);
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    private static function ensureBuild(): void
    {
        if (self::$built) {
            return;
        }

        // Marcar como construido ANTES de build() para evitar recursión si un
        // factory llama Container::make() durante la construcción de PHP-DI.
        self::$built = true;

        $builder = new ContainerBuilder();
        $defs = [];

        // Pre-built instances → DI\value()
        foreach (self::$pendingInstances as $abstract => $inst) {
            $defs[$abstract] = \DI\value($inst);
        }

        // Closures → DI\factory() (PHP-DI 7 cachea el resultado en resolvedEntries por defecto)
        foreach (self::$pendingDefinitions as $abstract => [$closure, $isSingleton]) {
            $captured = $closure;
            $defs[$abstract] = \DI\factory(static fn () => $captured());
        }

        // Aliases → DI\get()
        foreach (self::$pendingAliases as $alias => $target) {
            $defs[$alias] = \DI\get($target);
        }

        // El Container y la interfaz PSR-11 se resuelven a la façade estática misma
        $self = self::getInstance();
        $defs[self::class] = \DI\value($self);
        $defs[ContainerInterface::class] = \DI\value($self);

        $builder->addDefinitions($defs);
        self::$phpdi = $builder->build();
    }
}
