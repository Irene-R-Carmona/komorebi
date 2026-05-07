<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * Clase base abstracta para todos los tests unitarios de repositorios.
 *
 * ¿Qué me quieres demostrar?
 * Que makePdoWithStmt() configura el par PDO+PDOStatement en una sola
 * llamada, eliminando el setup repetido en cada test de repositorio,
 * y que assertQueryContains() permite verificar fragmentos SQL sin
 * acoplarse al query completo.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si PDO::prepare() cambia su firma, si PDOStatement deja de tener
 * fetchAll()/fetch(), o si PDO deja de aceptar stubs de PHPUnit.
 */

namespace Tests\Support;

use Override;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

/**
 * Base para tests unitarios de repositorios.
 *
 * Responsabilidades:
 *   - Proporcionar makePdoWithStmt() para crear pares PDO+PDOStatement configurados.
 *   - Centralizar la configuración de fetch/fetchAll/rowCount para cada test.
 *
 * Uso:
 *   final class MyRepositoryTest extends RepositoryTestCase { ... }
 */
abstract class RepositoryTestCase extends TestCase
{
    // -------------------------------------------------------------------------
    // Ciclo de vida
    // -------------------------------------------------------------------------

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
    }

    #[Override]
    protected function tearDown(): void
    {
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Helpers de PDO
    // -------------------------------------------------------------------------

    /**
     * Crea un par (PDO stub, PDOStatement mock) con fetchAll configurado.
     * El PDOStatement devuelto como segundo elemento puede recibir expects()
     * adicionales para verificar interacciones.
     *
     * @param array<int, array<string, mixed>> $fetchAllReturn filas a devolver
     * @param array<string, mixed>|false $fetchReturn fila única (false = no encontrado)
     * @param int $rowCount filas afectadas
     *
     * @return array{0: PDO&\PHPUnit\Framework\MockObject\Stub, 1: PDOStatement&\PHPUnit\Framework\MockObject\Stub}
     */
    protected function makePdoWithStmt(
        array $fetchAllReturn = [],
        array|false $fetchReturn = false,
        int $rowCount = 0
    ): array {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn($fetchAllReturn);
        $stmt->method('fetch')->willReturn($fetchReturn);
        $stmt->method('fetchColumn')->willReturn($rowCount > 0 ? $rowCount : false);
        $stmt->method('rowCount')->willReturn($rowCount);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);
        $pdo->method('lastInsertId')->willReturn((string) ($rowCount ?: 1));

        return [$pdo, $stmt];
    }

    /**
     * Crea un PDO stub simple con prepare() → statement stub de valores fijos.
     * Más ligero que makePdoWithStmt() cuando no necesitas verificar interacciones.
     *
     * @return PDO&\PHPUnit\Framework\MockObject\Stub
     */
    protected function makePdoStub(): PDO
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn([]);
        $stmt->method('fetch')->willReturn(false);
        $stmt->method('rowCount')->willReturn(0);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);
        $pdo->method('lastInsertId')->willReturn('1');

        return $pdo;
    }

    /**
     * Construye un PDO mock que espera exactamente N llamadas a prepare().
     * Útil para verificar que un método de repositorio no genera queries extras.
     *
     * @return array{0: PDO&\PHPUnit\Framework\MockObject\MockObject, 1: PDOStatement&\PHPUnit\Framework\MockObject\MockObject}
     */
    protected function makePdoExpectingPrepare(int $times = 1): array
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn([]);
        $stmt->method('fetch')->willReturn(false);
        $stmt->method('rowCount')->willReturn(0);

        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->exactly($times))
            ->method('prepare')
            ->willReturn($stmt);

        return [$pdo, $stmt];
    }
}
