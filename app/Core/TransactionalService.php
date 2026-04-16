<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

/**
 * Clase base para Services que requieren transacciones de base de datos.
 *
 * Extiende BaseService y añade PDO + helper transact().
 */
abstract class TransactionalService extends BaseService
{
    public function __construct(protected PDO $db)
    {
    }

    /**
     * Ejecuta un callable dentro de una transacción PDO.
     *
     * - Si el callable lanza una excepción → rollback + Result::fail
     * - Si el callable retorna Result::fail → rollback + propaga el Result
     * - Si el callable retorna Result::ok  → commit + propaga el Result
     *
     * @param callable(): Result $fn
     */
    protected function transact(callable $fn): Result
    {
        $this->db->beginTransaction();

        try {
            $result = $fn();

            if ($result->isFail()) {
                $this->db->rollBack();

                return $result;
            }

            $this->db->commit();

            return $result;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            $this->logError('Transaction failed', ['exception' => $e->getMessage()]);

            return Result::fail($e->getMessage(), 'transaction_error');
        }
    }
}
