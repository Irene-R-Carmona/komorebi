<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Container;
use App\Core\Database;
use App\Core\Result;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Contracts\AccountDeletionServiceInterface;
use Override;
use Throwable;

/**
 * Servicio de eliminación de cuenta conforme a GDPR.
 *
 * Garantiza que el soft-delete y la anonimización se aplican
 * de forma atómica (una sola transacción).
 */
final class AccountDeletionService implements AccountDeletionServiceInterface
{
    private UserRepositoryInterface $userRepo;

    public function __construct(?UserRepositoryInterface $userRepo = null)
    {
        $this->userRepo = $userRepo ?? Container::make(UserRepositoryInterface::class);
    }

    /**
     * Eliminar y anonimizar una cuenta de usuario de forma atómica.
     *
     * Realiza en una sola transacción:
     * 1. Soft delete (deleted_at + is_active = 0)
     * 2. Anonimización GDPR (name, email, phone)
     */
    #[Override]
    public function deleteAndAnonymize(int $userId): Result
    {
        try {
            return Database::transaction(function () use ($userId): Result {
                $this->userRepo->update($userId, [
                    'deleted_at' => \date('Y-m-d H:i:s'),
                    'is_active'  => 0,
                ]);

                $this->userRepo->anonymize($userId);

                return Result::ok(true);
            });
        } catch (Throwable $e) {
            return Result::fail('No se pudo eliminar la cuenta: ' . $e->getMessage());
        }
    }
}
