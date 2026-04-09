<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Service Provider base class.
 *
 * Permite organizar el registro de servicios en el Container de forma modular.
 *
 * Ciclo de vida:
 * 1. register(): Registra bindings en el Container
 * 2. boot(): Inicialización (se ejecuta DESPUÉS de todos los register())
 *
 * Ejemplo:
 *   class DatabaseServiceProvider extends ServiceProvider {
 *       public function register(): void {
 *           Container::singleton(PDO::class, fn() => $this->createPdoConnection());
 *       }
 *       public function boot(): void {
 *           // Ejecutar migraciones, seeders, etc.
 *       }
 *   }
 */
abstract class ServiceProvider
{
    /**
     * Registrar servicios en el Container.
     *
     * Este método se llama PRIMERO para todos los providers.
     */
    abstract public function register(): void;

    /**
     * Inicialización posterior (opcional).
     *
     * Se ejecuta DESPUÉS de que todos los providers han registrado sus servicios.
     * Útil para configuraciones que dependen de otros servicios.
     */
    public function boot(): void
    {
        // Override si necesitas inicialización
    }
}
