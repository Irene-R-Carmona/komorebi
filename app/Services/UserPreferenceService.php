<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Contracts\UserPreferenceServiceInterface;
use Override;

/**
 * Servicio de preferencias de usuario.
 *
 * Gestiona la lectura y escritura de preferencias persistidas en el perfil.
 */
final class UserPreferenceService implements UserPreferenceServiceInterface
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepo,
    ) {
    }

    /**
     * Obtiene las preferencias del usuario.
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function getPreferences(int $userId): array
    {
        $user = $this->userRepo->findById($userId);

        if (!$user || empty($user['preferences'])) {
            return [];
        }

        return \is_string($user['preferences'])
            ? \json_decode($user['preferences'], true) ?? []
            : (array) $user['preferences'];
    }

    /**
     * Actualiza las preferencias del usuario.
     *
     * @param array<string, mixed> $preferences
     */
    #[Override]
    public function updatePreferences(int $userId, array $preferences): bool
    {
        return $this->userRepo->updatePreferences($userId, $preferences);
    }
}
