<?php

/**
 * ¿Qué prueba aquí? El repositorio RoleRepository: listados de roles con counts,
 *   permisos asociados, CRUD de roles, gestión de permisos y estadísticas.
 * ¿Qué me quieres demostrar? Que cada método ejecuta la query correcta y transforma
 *   la respuesta con la estructura esperada.
 * ¿Qué va a fallar en este test si se cambia el código? Si se cambia la transformación
 *   de permission_ids/permission_names en getAllWithPermissions, o si update retorna
 *   false cuando no hay campos a actualizar.
 */

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Repositories\RoleRepository;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RoleRepository::class)]
final class RoleRepositoryTest extends TestCase
{
    /** @phpstan-ignore method.unused */
    private function makeQueryStmt(array $fetchAllReturn = [], mixed $fetchReturn = false): PDOStatement
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('fetchAll')->willReturn($fetchAllReturn);
        $stmt->method('fetch')->willReturn($fetchReturn);

        return $stmt;
    }

    private function makeSimplePdo(array $fetchAllReturn = [], mixed $fetchReturn = false): PDO
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn($fetchAllReturn);
        $stmt->method('fetch')->willReturn($fetchReturn);
        $stmt->method('fetchColumn')->willReturn($fetchReturn !== false ? 1 : 0);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);
        $pdo->method('query')->willReturn($stmt);
        $pdo->method('lastInsertId')->willReturn('10');

        return $pdo;
    }

    // ─────────────────────────────────────────────────────────────
    // findAllWithCounts
    // ─────────────────────────────────────────────────────────────

    public function testFindAllWithCountsReturnsRows(): void
    {
        $rows = [[
            'id' => 1,
            'code' => 'admin',
            'name' => 'Admin',
            'description' => null,
            'permissions_count' => 5,
            'users_count' => 3,
        ]];
        $pdo = $this->makeSimplePdo($rows);
        $repo = new RoleRepository($pdo);

        $result = $repo->findAllWithCounts();
        $this->assertCount(1, $result);
        $this->assertSame('Admin', $result[0]['name']);
    }

    public function testFindAllWithCountsReturnsEmpty(): void
    {
        $pdo = $this->makeSimplePdo([]);
        $repo = new RoleRepository($pdo);

        $this->assertSame([], $repo->findAllWithCounts());
    }

    // ─────────────────────────────────────────────────────────────
    // getAllWithPermissions
    // ─────────────────────────────────────────────────────────────

    public function testGetAllWithPermissionsTransformsPermissionIds(): void
    {
        $rows = [[
            'id' => 1,
            'code' => 'admin',
            'name' => 'Admin',
            'description' => null,
            'permission_ids' => '1,2',
            'permission_names' => 'Read,Write',
        ]];
        $pdo = $this->makeSimplePdo($rows);
        $repo = new RoleRepository($pdo);

        $result = $repo->getAllWithPermissions();
        $this->assertCount(1, $result);
        $this->assertArrayHasKey('permissions', $result[0]);
        $this->assertCount(2, $result[0]['permissions']);
        $this->assertSame(1, $result[0]['permissions'][0]['id']);
        $this->assertSame('Read', $result[0]['permissions'][0]['name']);
        $this->assertArrayNotHasKey('permission_ids', $result[0]);
    }

    public function testGetAllWithPermissionsHandlesNullPermissions(): void
    {
        $rows = [[
            'id' => 2,
            'code' => 'guest',
            'name' => 'Guest',
            'description' => null,
            'permission_ids' => null,
            'permission_names' => null,
        ]];
        $pdo = $this->makeSimplePdo($rows);
        $repo = new RoleRepository($pdo);

        $result = $repo->getAllWithPermissions();
        $this->assertSame([], $result[0]['permissions']);
    }

    // ─────────────────────────────────────────────────────────────
    // getStats
    // ─────────────────────────────────────────────────────────────

    public function testGetStatsReturnsRow(): void
    {
        $row = ['users_with_roles' => 10, 'total_roles' => 5, 'total_permissions' => 20];
        $pdo = $this->makeSimplePdo([], $row);
        $repo = new RoleRepository($pdo);

        $result = $repo->getStats();
        $this->assertSame(5, $result['total_roles']);
    }

    // ─────────────────────────────────────────────────────────────
    // findById / findByCode
    // ─────────────────────────────────────────────────────────────

    public function testFindByIdReturnsArrayWhenFound(): void
    {
        $row = ['id' => 1, 'code' => 'admin', 'name' => 'Admin', 'description' => null];
        $pdo = $this->makeSimplePdo([], $row);
        $repo = new RoleRepository($pdo);

        $result = $repo->findById(1);
        $this->assertSame('admin', $result->code);
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $pdo = $this->makeSimplePdo([], false);
        $repo = new RoleRepository($pdo);

        $this->assertNull($repo->findById(999));
    }

    public function testFindByCodeReturnsArrayWhenFound(): void
    {
        $row = ['id' => 1, 'code' => 'manager', 'name' => 'Manager', 'description' => null];
        $pdo = $this->makeSimplePdo([], $row);
        $repo = new RoleRepository($pdo);

        $result = $repo->findByCode('manager');
        $this->assertSame(1, $result->id);
    }

    public function testFindByCodeReturnsNullWhenNotFound(): void
    {
        $pdo = $this->makeSimplePdo([], false);
        $repo = new RoleRepository($pdo);

        $this->assertNull($repo->findByCode('nonexistent'));
    }

    // ─────────────────────────────────────────────────────────────
    // create
    // ─────────────────────────────────────────────────────────────

    public function testCreateReturnsInsertedId(): void
    {
        $pdo = $this->makeSimplePdo();
        $repo = new RoleRepository($pdo);

        $id = $repo->createRole('supervisor', 'Supervisor', 'Manages shifts');
        $this->assertSame(10, $id);
    }

    public function testCreateWithNullDescription(): void
    {
        $pdo = $this->makeSimplePdo();
        $repo = new RoleRepository($pdo);

        $id = $repo->createRole('viewer', 'Viewer');
        $this->assertIsInt($id);
    }

    // ─────────────────────────────────────────────────────────────
    // update
    // ─────────────────────────────────────────────────────────────

    public function testUpdateReturnsTrueWhenNameProvided(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new RoleRepository($pdo);
        $this->assertTrue($repo->updateRole(1, 'New Name'));
    }

    public function testUpdateReturnsTrueWhenDescriptionProvided(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new RoleRepository($pdo);
        $this->assertTrue($repo->updateRole(1, null, 'New description'));
    }

    public function testUpdateReturnsFalseWhenNoFieldsProvided(): void
    {
        $pdo = $this->makeSimplePdo();
        $repo = new RoleRepository($pdo);

        $this->assertFalse($repo->updateRole(1));
    }

    // ─────────────────────────────────────────────────────────────
    // delete
    // ─────────────────────────────────────────────────────────────

    public function testDeleteReturnsTrueOnSuccess(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new RoleRepository($pdo);
        $this->assertTrue($repo->delete(1));
    }

    // ─────────────────────────────────────────────────────────────
    // countUsers
    // ─────────────────────────────────────────────────────────────

    public function testCountUsersReturnsInteger(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('bindValue')->willReturn(true);
        $stmt->method('fetchColumn')->willReturn(7);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new RoleRepository($pdo);
        $this->assertSame(7, $repo->countUsers(2));
    }

    // ─────────────────────────────────────────────────────────────
    // grantPermission
    // ─────────────────────────────────────────────────────────────

    public function testGrantPermissionReturnsTrueWhenAlreadyExists(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn(['1' => 1]);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new RoleRepository($pdo);
        $this->assertTrue($repo->grantPermission(1, 2));
    }

    public function testGrantPermissionInsertsWhenNotExists(): void
    {
        $checkStmt = $this->createStub(PDOStatement::class);
        $checkStmt->method('execute')->willReturn(true);
        $checkStmt->method('fetch')->willReturn(false);

        $insertStmt = $this->createStub(PDOStatement::class);
        $insertStmt->method('execute')->willReturn(true);

        $idx = 0;
        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturnCallback(function () use (&$idx, $checkStmt, $insertStmt) {
            return $idx++ === 0 ? $checkStmt : $insertStmt;
        });

        $repo = new RoleRepository($pdo);
        $this->assertTrue($repo->grantPermission(1, 3));
    }

    // ─────────────────────────────────────────────────────────────
    // revokePermission
    // ─────────────────────────────────────────────────────────────

    public function testRevokePermissionReturnsTrueOnSuccess(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new RoleRepository($pdo);
        $this->assertTrue($repo->revokePermission(1, 2));
    }

    // ─────────────────────────────────────────────────────────────
    // findAllPermissions / findPermissionById
    // ─────────────────────────────────────────────────────────────

    public function testFindAllPermissionsReturnsRows(): void
    {
        $rows = [[
            'id' => 1,
            'code' => 'read',
            'name' => 'Read',
            'description' => null,
            'resource' => 'product',
            'action' => 'read',
        ]];
        $pdo = $this->makeSimplePdo($rows);
        $repo = new RoleRepository($pdo);

        $result = $repo->findAllPermissions();
        $this->assertCount(1, $result);
        $this->assertSame('read', $result[0]['code']);
    }

    public function testFindPermissionByIdReturnsRowWhenFound(): void
    {
        $row = [
            'id' => 1,
            'code' => 'write',
            'name' => 'Write',
            'description' => null,
            'resource' => 'product',
            'action' => 'write',
        ];
        $pdo = $this->makeSimplePdo([], $row);
        $repo = new RoleRepository($pdo);

        $result = $repo->findPermissionById(1);
        $this->assertSame('write', $result['code']);
    }

    public function testFindPermissionByIdReturnsNullWhenNotFound(): void
    {
        $pdo = $this->makeSimplePdo([], false);
        $repo = new RoleRepository($pdo);

        $this->assertNull($repo->findPermissionById(999));
    }
}
