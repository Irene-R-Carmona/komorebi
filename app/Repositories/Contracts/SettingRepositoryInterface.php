<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Domain\DTO\SettingDTO;

interface SettingRepositoryInterface
{
    /** @return SettingDTO[] Todas las filas de settings */
    public function findAll(): array;

    /** @return SettingDTO[] Settings de un grupo */
    public function findByGroup(string $group): array;
}
