<?php

declare(strict_types=1);

namespace App\Services\Contracts;

/**
 * Contrato para el servicio de preferencias de usuario.
 */
interface UserPreferenceServiceInterface
{
    /**
     * Obtiene las preferencias del usuario.
     *
     * @return array<string, mixed>
     */
    public function getPreferences(int $userId): array;

    /**
     * Actualiza las preferencias del usuario.
     *
     * @param array<string, mixed> $preferences
     */
    public function updatePreferences(int $userId, array $preferences): bool;
}
