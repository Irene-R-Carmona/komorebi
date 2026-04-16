<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * Clase base abstracta para todos los tests de integración que necesitan MySQL.
 * ¿Qué me quieres demostrar?
 * Que la conexión real a BD se obtiene una sola vez por clase y que cada test
 * individual se aísla mediante beginTransaction/rollBack automático.
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si Database::getConnection() falla, si RUN_INTEGRATION_TESTS no está definido,
 * o si la extensión pdo_mysql no está disponible en el contenedor de test.
 */

namespace Tests\Support;

use App\Core\Database;
use PDO;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Base para tests de integración con MySQL real.
 *
 * Responsabilidades:
 *   - Verificar que el entorno de integración está disponible.
 *   - Obtener la conexión PDO una sola vez por test-class (setUpBeforeClass).
 *   - Envolver cada test en una transacción que se deshace al terminar (rollback).
 *
 * Cómo usarla:
 *   1. Extiende esta clase (extends BaseIntegrationTest).
 *   2. Implementa setUp() llamando primero a parent::setUp().
 *   3. Si necesitas tearDown(), llama a parent::tearDown() al final.
 *   4. Usa self::$db para queries directas o inyéctala en tus repositorios/services.
 */
#[Group('integration')]
abstract class BaseIntegrationTest extends TestCase
{
    use ResultAssertions;

    /** Conexión real a MySQL — compartida entre todos los métodos de la misma clase. */
    protected static PDO $db;

    // -------------------------------------------------------------------------
    // Ciclo de vida de la clase (una vez por test-class)
    // -------------------------------------------------------------------------

    #[\Override]
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        if (!\getenv('RUN_INTEGRATION_TESTS')) {
            self::markTestSkipped(
                'Tests de integración desactivados. ' .
                    'Define la variable de entorno RUN_INTEGRATION_TESTS=1 o usa make test.'
            );
        }

        if (!\extension_loaded('pdo_mysql')) {
            self::markTestSkipped('La extensión pdo_mysql no está disponible en este entorno.');
        }

        static::$db = Database::getConnection();
    }

    // -------------------------------------------------------------------------
    // Ciclo de vida por test individual
    // -------------------------------------------------------------------------

    /**
     * Abre una transacción antes de cada test para aislar sus cambios en BD.
     *
     * Las subclases DEBEN llamar parent::setUp() al inicio de su setUp().
     */
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        static::$db->beginTransaction();
    }

    /**
     * Deshace la transacción al finalizar cada test, dejando la BD limpia.
     *
     * Las subclases que sobreescriban tearDown() DEBEN llamar parent::tearDown()
     * al final de su implementación.
     */
    #[\Override]
    protected function tearDown(): void
    {
        if (static::$db->inTransaction()) {
            static::$db->rollBack();
        }
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Helpers de aserción para BD
    // -------------------------------------------------------------------------

    /**
     * Ejecuta una consulta preparada y devuelve todas las filas como arrays asociativos.
     *
     * @param array<mixed> $params
     * @return array<int, array<string, mixed>>
     */
    protected function query(string $sql, array $params = []): array
    {
        $stmt = static::$db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Aserta que existe al menos una fila que cumple las condiciones dadas.
     *
     * @param array<string, mixed> $conditions  columna => valor
     */
    protected function assertRowExists(string $table, array $conditions): void
    {
        $count = $this->countRows($table, $conditions);
        $this->assertGreaterThan(
            0,
            $count,
            "No se encontró ninguna fila en `{$table}` que coincida con: " . \json_encode($conditions)
        );
    }

    /**
     * Aserta el número exacto de filas que cumplen las condiciones dadas.
     *
     * @param array<string, mixed> $conditions  columna => valor  (vacío = contar todos)
     */
    protected function assertRowCount(string $table, int $expected, array $conditions = []): void
    {
        $count = $this->countRows($table, $conditions);
        $this->assertSame(
            $expected,
            $count,
            "Se esperaban {$expected} fila(s) en `{$table}`, se encontraron {$count}."
        );
    }

    // -------------------------------------------------------------------------
    // Métodos privados de soporte
    // -------------------------------------------------------------------------

    /**
     * @param array<string, mixed> $conditions
     */
    private function countRows(string $table, array $conditions): int
    {
        $where = '';
        $params = [];

        if ($conditions !== []) {
            $clauses = \array_map(
                static fn (string $col): string => "`{$col}` = :{$col}",
                \array_keys($conditions)
            );
            $where = ' WHERE ' . \implode(' AND ', $clauses);
            $params = $conditions;
        }

        $stmt = static::$db->prepare("SELECT COUNT(*) FROM `{$table}`{$where}");
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }
}
