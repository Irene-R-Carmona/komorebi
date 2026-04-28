<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Permission;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * ¿Qué pruebas aquí? Métodos CRUD del modelo Permission con stubs de PDO.
 * ¿Qué me quieres demostrar? Que cada método delega en PDO y respeta la lógica de negocio (excepción en create duplicado, false si no hay campos).
 * ¿Qué va a fallar en este test si se cambia el código? Cambios en las queries, en el lanzamiento de excepción o en los valores de retorno.
 */
#[CoversClass(Permission::class)]
final class PermissionTest extends TestCase
{
    private PDO $pdo;
    private PDOStatement $stmt;
    private Permission $model;

    protected function setUp(): void
    {
        $this->pdo   = $this->createStub(PDO::class);
        $this->stmt  = $this->createStub(PDOStatement::class);
        $this->pdo->method('prepare')->willReturn($this->stmt);
        $this->model = new Permission($this->pdo);
    }

    // ── all ──────────────────────────────────────────────────────

    public function testAllReturnsArray(): void
    {
        $rows = [
            ['id' => 1, 'code' => 'users.view', 'name' => 'Ver usuarios', 'resource' => 'users'],
        ];
        $this->stmt->method('fetchAll')->willReturn($rows);
        $this->pdo->method('query')->willReturn($this->stmt);

        $result = $this->model->all();

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
    }

    // ── findById ─────────────────────────────────────────────────

    public function testFindByIdReturnsArrayWhenFound(): void
    {
        $row = ['id' => 1, 'code' => 'users.view', 'name' => 'Ver usuarios', 'resource' => 'users'];
        $this->stmt->method('fetch')->willReturn($row);

        $result = $this->model->findById(1);

        $this->assertIsArray($result);
        $this->assertSame('users.view', $result['code']);
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $this->stmt->method('fetch')->willReturn(false);

        $result = $this->model->findById(999);

        $this->assertNull($result);
    }

    // ── findByKey ─────────────────────────────────────────────────

    public function testFindByKeyReturnsArrayWhenFound(): void
    {
        $row = ['id' => 1, 'code' => 'users.view', 'name' => 'Ver usuarios', 'resource' => 'users'];
        $this->stmt->method('fetch')->willReturn($row);

        $result = $this->model->findByKey('users.view');

        $this->assertIsArray($result);
        $this->assertSame('users.view', $result['code']);
    }

    public function testFindByKeyReturnsNullWhenNotFound(): void
    {
        $this->stmt->method('fetch')->willReturn(false);

        $result = $this->model->findByKey('nonexistent.key');

        $this->assertNull($result);
    }

    // ── findByResource ────────────────────────────────────────────

    public function testFindByResourceReturnsArray(): void
    {
        $rows = [
            ['id' => 1, 'code' => 'users.view', 'name' => 'Ver usuarios', 'resource' => 'users'],
            ['id' => 2, 'code' => 'users.create', 'name' => 'Crear usuarios', 'resource' => 'users'],
        ];
        $this->stmt->method('fetchAll')->willReturn($rows);

        $result = $this->model->findByResource('users');

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    // ── create ────────────────────────────────────────────────────

    public function testCreateThrowsRuntimeExceptionIfCodeAlreadyExists(): void
    {
        $existing = ['id' => 1, 'code' => 'users.view'];
        // findByKey returns existing record → must throw
        $this->stmt->method('fetch')->willReturn($existing);

        $this->expectException(RuntimeException::class);
        $this->model->create('users.view', 'Ver usuarios', 'Permiso de ver', 'users');
    }

    public function testCreateReturnsInsertedId(): void
    {
        // findByKey → null (code doesn't exist), then prepare INSERT
        $fetchCount = 0;
        $stmtSeq = $this->createStub(PDOStatement::class);
        $stmtSeq->method('fetch')->willReturnCallback(function () use (&$fetchCount) {
            $fetchCount++;
            return false; // code not found
        });
        $this->pdo->method('prepare')->willReturn($stmtSeq);
        $this->pdo->method('lastInsertId')->willReturn('7');
        $model = new Permission($this->pdo);

        $result = $model->create('users.delete', 'Eliminar usuarios', 'Permiso borrar', 'users');

        $this->assertSame(7, $result);
    }

    // ── update ────────────────────────────────────────────────────

    public function testUpdateReturnsFalseWhenNoFieldsProvided(): void
    {
        $result = $this->model->update(1, null, null);

        $this->assertFalse($result);
    }

    public function testUpdateReturnsTrueWhenFieldsProvided(): void
    {
        $this->stmt->method('execute')->willReturn(true);

        $result = $this->model->update(1, 'Nuevo nombre', null);

        $this->assertTrue($result);
    }

    // ── delete ────────────────────────────────────────────────────

    public function testDeleteReturnsTrue(): void
    {
        $this->stmt->method('execute')->willReturn(true);

        $result = $this->model->delete(1);

        $this->assertTrue($result);
    }

    // ── getRoles ──────────────────────────────────────────────────

    public function testGetRolesReturnsArray(): void
    {
        $roles = [['id' => 1, 'code' => 'admin', 'name' => 'Administrador']];
        $this->stmt->method('fetchAll')->willReturn($roles);

        $result = $this->model->getRoles(1);

        $this->assertIsArray($result);
        $this->assertSame('admin', $result[0]['code']);
    }

    public function testGetRolesReturnsEmptyArrayWhenNoRoles(): void
    {
        $this->stmt->method('fetchAll')->willReturn([]);

        $result = $this->model->getRoles(999);

        $this->assertSame([], $result);
    }
}
