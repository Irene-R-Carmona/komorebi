<?php

declare(strict_types=1);

namespace App\Services\Contracts;

interface GamificationServiceInterface
{
    public function calculateUserLevel(int $reservasCount): array;

    public function getLevelName(int $nivelNumero): string;

    public function checkLevelUp(int $reservasAntes, int $reservasDespues): array;
}
