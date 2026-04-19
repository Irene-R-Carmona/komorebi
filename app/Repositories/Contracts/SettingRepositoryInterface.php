<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

interface SettingRepositoryInterface
{
    /** @return array<int, array<string, mixed>> Todas las filas de settings */
    public function findAll(): array;

    /** @return array<int, array<string, mixed>> Settings de un grupo */
    public function findByGroup(string $group): array;
}
