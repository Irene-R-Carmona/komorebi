<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Result;
use PDO;

/**
 * Servicio de eliminación de cuenta conforme a GDPR.
 *
 * Garantiza que el soft-delete y la anonimización se aplican
 * de forma atómica (una sola transacción).
 */
final class AccountDeletionService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    /**
     * Eliminar y anonimizar una cuenta de usuario de forma atómica.
     *
     * Realiza en una sola transacción:
     * 1. Soft delete (deleted_at + is_active = 0)
     * 2. Anonimización GDPR (name, email, phone)
     */
    public function deleteAndAnonymize(int $userId): Result
    {
        try {
            $this->db->beginTransaction();

            // Soft delete
            $stmt = $this->db->prepare(
                'UPDATE users SET deleted_at = NOW(), is_active = 0 WHERE id = :id'
            );
            $stmt->execute(['id' => $userId]);

            // Anonimización GDPR
            $stmt = $this->db->prepare(
                "UPDATE users
                 SET name = 'Usuario eliminado',
                     email = CONCAT('deleted_', id, '@deleted.local'),
                     phone = NULL
                 WHERE id = :id"
            );
            $stmt->execute(['id' => $userId]);

            $this->db->commit();

            return Result::ok(true);
        } catch (\Throwable $e) {
            $this->db->rollBack();
            return Result::fail('No se pudo eliminar la cuenta: ' . $e->getMessage());
        }
    }
}
