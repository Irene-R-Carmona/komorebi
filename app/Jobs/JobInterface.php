<?php

declare(strict_types=1);

namespace App\Jobs;

/**
 * Interfaz para Jobs en el sistema de colas
 *
 * Todos los jobs deben implementar esta interfaz y definir
 * el método handle() que contiene la lógica del trabajo.
 *
 * @package App\Jobs
 */
interface JobInterface
{
    /**
     * Ejecuta el trabajo del job
     *
     * @param array<string, mixed> $payload Datos necesarios para ejecutar el job
     * @return void
     * @throws \Throwable Si ocurre un error durante la ejecución
     */
    public function handle(array $payload): void;
}
