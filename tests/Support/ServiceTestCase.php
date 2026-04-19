<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * Clase base abstracta para todos los tests unitarios de servicios.
 *
 * ¿Qué me quieres demostrar?
 * Que los helpers makePdoStub(), startFreshSession() y resetSession()
 * eliminan ~4 líneas de setup repetido en cada test de servicio,
 * y que ResultAssertions está disponible sin trait extra en cada subclase.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si PDOStatement o PDO cambian la firma de prepare()/execute(),
 * si ResultAssertions elimina assertResultOk/assertResultFail,
 * o si session_start() deja de estar disponible en el entorno de test.
 */

namespace Tests\Support;

use Override;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

/**
 * Base para tests unitarios de servicios.
 *
 * Responsabilidades:
 *   - Proporcionar makePdoStub() para crear el par PDO+PDOStatement preconfigurado.
 *   - Proporcionar helpers de sesión para tests que leen $_SESSION.
 *   - Incluir ResultAssertions para verificar objetos Result sin repetir el trait.
 *
 * Uso:
 *   final class MyServiceTest extends ServiceTestCase { ... }
 */
abstract class ServiceTestCase extends TestCase
{
    use ResultAssertions;

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
        $this->resetSession();
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Helpers de PDO
    // -------------------------------------------------------------------------

    /**
     * Crea un stub de PDO cuyo prepare() devuelve siempre el mismo PDOStatement stub.
     * El statement tiene execute() → true y fetch()/fetchAll() → false/[] por defecto.
     *
     * Para tests que necesitan un fetch personalizado, usa makePdoWithFetch().
     *
     * @return PDO&\PHPUnit\Framework\MockObject\Stub
     */
    protected function makePdoStub(): PDO
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn(false);
        $stmt->method('fetchAll')->willReturn([]);
        $stmt->method('fetchColumn')->willReturn(false);
        $stmt->method('rowCount')->willReturn(0);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);
        $pdo->method('beginTransaction')->willReturn(true);
        $pdo->method('commit')->willReturn(true);
        $pdo->method('rollBack')->willReturn(true);
        $pdo->method('inTransaction')->willReturn(false);

        return $pdo;
    }

    /**
     * Crea un stub de PDO cuyo único fetch() devuelve $row (o false si null).
     * Útil para tests donde el servicio busca una entidad por ID.
     *
     * @param array<string, mixed>|false $row
     * @return PDO&\PHPUnit\Framework\MockObject\Stub
     */
    protected function makePdoWithFetch(array|false $row): PDO
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn($row);
        $stmt->method('fetchAll')->willReturn($row === false ? [] : [$row]);
        $stmt->method('rowCount')->willReturn($row === false ? 0 : 1);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);
        $pdo->method('beginTransaction')->willReturn(true);
        $pdo->method('commit')->willReturn(true);
        $pdo->method('rollBack')->willReturn(true);
        $pdo->method('inTransaction')->willReturn(false);

        return $pdo;
    }

    /**
     * Crea un stub de PDOStatement independiente para configuraciones especiales.
     *
     * @return PDOStatement&\PHPUnit\Framework\MockObject\Stub
     */
    protected function makeStmtStub(): PDOStatement
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn(false);
        $stmt->method('fetchAll')->willReturn([]);
        $stmt->method('rowCount')->willReturn(0);

        return $stmt;
    }

    // -------------------------------------------------------------------------
    // Helpers de sesión
    // -------------------------------------------------------------------------

    /**
     * Inicia sesión limpia y vacía (sin datos de usuario).
     */
    protected function startFreshSession(): void
    {
        if (\session_status() === \PHP_SESSION_NONE) {
            \session_start();
        }
        $_SESSION = [];
    }

    /**
     * Limpia los datos de sesión sin destruirla.
     */
    protected function resetSession(): void
    {
        if (\session_status() === \PHP_SESSION_ACTIVE) {
            $_SESSION = [];
        }
    }

    /**
     * Simula un usuario autenticado en sesión.
     */
    protected function asSessionUser(int $userId = 1, string $role = 'user', ?int $cafeId = null): void
    {
        $this->startFreshSession();
        $_SESSION['user_id']   = $userId;
        $_SESSION['user_role'] = $role;
        if ($cafeId !== null) {
            $_SESSION['user_cafe_id'] = $cafeId;
        }
    }
}

