<?php

declare(strict_types=1);

namespace App\Jobs;

use Throwable;

interface JobInterface
{
    /**
     * @param array<string, mixed> $payload Datos necesarios para ejecutar el job
     * @throws Throwable Si ocurre un error durante la ejecución
     */
    public function handle(array $payload): void;
}
