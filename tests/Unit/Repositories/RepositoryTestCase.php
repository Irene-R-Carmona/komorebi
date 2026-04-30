<?php

/**
 * ¿Qué prueba aquí? Clase base abstracta para tests de repositorios.
 * ¿Qué me quieres demostrar? Eliminar la duplicación de makePdo() en los 30 repositorios.
 * ¿Qué va a fallar en este test si se cambia el código? No aplica — es infraestructura de tests.
 */

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

/**
 * Clase base para tests unitarios de repositorios.
 *
 * Proporciona un factory makePdo() que construye un PDO stub donde
 * prepare() y query() devuelven siempre el mismo PDOStatement stub.
 *
 * Uso básico:
 *   $pdo = $this->makePdo(fetchAllReturn: [RowFactory::userRow()]);
 *   $repo = new UserRepository($pdo);
 *
 * Para secuencias de llamadas distintas (consultas múltiples dentro de un método):
 *   $pdo = $this->makeMultiCallPdo([
 *       ['fetchAll' => [RowFactory::userRow()]],
 *       ['fetch'    => RowFactory::userRow()],
 *   ]);
 */
abstract class RepositoryTestCase extends TestCase
{
    // ─────────────────────────────────────────────────────────────
    // Factory principal (single-stmt stub)
    // ─────────────────────────────────────────────────────────────

    /**
     * Construye un PDO stub cuyos prepare()/query() devuelven siempre el mismo stmt.
     *
     * @param array       $fetchAllReturn    Valor devuelto por $stmt->fetchAll()
     * @param mixed       $fetchReturn       Valor devuelto por $stmt->fetch()
     * @param mixed       $fetchColumnReturn Valor devuelto por $stmt->fetchColumn()
     * @param string      $lastInsertId      Valor devuelto por $pdo->lastInsertId()
     * @param int         $rowCount          Valor devuelto por $stmt->rowCount()
     */
    protected function makePdo(
        array $fetchAllReturn = [],
        mixed $fetchReturn = false,
        mixed $fetchColumnReturn = '0',
        string $lastInsertId = '1',
        int $rowCount = 1,
    ): PDO {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn($fetchAllReturn);
        $stmt->method('fetch')->willReturn($fetchReturn);
        $stmt->method('fetchColumn')->willReturn($fetchColumnReturn);
        $stmt->method('bindValue')->willReturn(true);
        $stmt->method('bindParam')->willReturn(true);
        $stmt->method('rowCount')->willReturn($rowCount);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);
        $pdo->method('query')->willReturn($stmt);
        $pdo->method('lastInsertId')->willReturn($lastInsertId);

        return $pdo;
    }

    // ─────────────────────────────────────────────────────────────
    // Factory de secuencias (multi-stmt — cuando un método ejecuta
    // varias consultas con resultados distintos)
    // ─────────────────────────────────────────────────────────────

    /**
     * Construye un PDO stub donde cada llamada a prepare() devuelve un
     * stmt distinto configurado según $callSequence.
     *
     * Cada elemento de $callSequence puede tener las claves:
     *   'fetchAll', 'fetch', 'fetchColumn', 'rowCount', 'lastInsertId'
     *
     * Ejemplo:
     *   $pdo = $this->makeMultiCallPdo([
     *       ['fetchAll' => [RowFactory::userRow()]],
     *       ['fetch'    => RowFactory::reservationRow()],
     *   ]);
     *
     * @param list<array{
     *   fetchAll?: array,
     *   fetch?: mixed,
     *   fetchColumn?: mixed,
     *   rowCount?: int,
     * }> $callSequence
     */
    protected function makeMultiCallPdo(array $callSequence): PDO
    {
        $stmts = [];

        foreach ($callSequence as $config) {
            $stmt = $this->createStub(PDOStatement::class);
            $stmt->method('execute')->willReturn(true);
            $stmt->method('bindValue')->willReturn(true);
            $stmt->method('bindParam')->willReturn(true);
            $stmt->method('fetchAll')->willReturn($config['fetchAll'] ?? []);
            $stmt->method('fetch')->willReturn($config['fetch'] ?? false);
            $stmt->method('fetchColumn')->willReturn($config['fetchColumn'] ?? '0');
            $stmt->method('rowCount')->willReturn($config['rowCount'] ?? 1);
            $stmts[] = $stmt;
        }

        $stmtCount = \count($stmts);
        $callIndex = 0;

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturnCallback(
            function () use ($stmts, $stmtCount, &$callIndex): PDOStatement {
                $stmt = $stmts[\min($callIndex, $stmtCount - 1)];
                $callIndex++;

                return $stmt;
            }
        );
        $pdo->method('query')->willReturnCallback(
            function () use ($stmts, $stmtCount, &$callIndex): PDOStatement {
                $stmt = $stmts[\min($callIndex, $stmtCount - 1)];
                $callIndex++;

                return $stmt;
            }
        );
        $pdo->method('lastInsertId')->willReturn('1');

        return $pdo;
    }
}
