<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Role;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RoleTest extends TestCase
{
    private function stubPdoWithPrepare(PDOStatement $stmt): PDO
    {
        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);
        return $pdo;
    }

    private function stubPdoWithQuery(PDOStatement $stmt): PDO
    {
        $pdo = $this->createStub(PDO::class);
        $pdo->method('query')->willReturn($stmt);
        return $pdo;
    }

    // ── all ───────────────────────────────────────────────────────

    public function testAllReturnsArray(): void
    {
        $rows = [['id' => 1, 'code' => 'admin', 'name' => 'Admin', 'description' => null]];
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('fetchAll')->willReturn($rows);
        $result = (new Role($this->stubPdoWithQuery($stmt)))->all();
        $this->assertSame($rows, $result);
    }

    // ── findById ──────────────────────────────────────────────────

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('fetch')->willReturn(false);
        $this->assertNull((new Role($this->stubPdoWithPrepare($stmt)))->findById(999));
    }

    public function testFindByIdReturnsArrayWhenFound(): void
    {
        $row = ['id' => 1, 'code' => 'admin', 'name' => 'Admin'];
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('fetch')->willReturn($row);
        $this->assertSame($row, (new Role($this->stubPdoWithPrepare($stmt)))->findById(1));
    }

    // ── findByKey ─────────────────────────────────────────────────

    public function testFindByKeyReturnsNullWhenNotFound(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('fetch')->willReturn(false);
        $this->assertNull((new Role($this->stubPdoWithPrepare($stmt)))->findByKey('ghost'));
    }

    public function testFindByKeyReturnsArrayWhenFound(): void
    {
        $row = ['id' => 2, 'code' => 'user', 'name' => 'User'];
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('fetch')->willReturn($row);
        $this->assertSame($row, (new Role($this->stubPdoWithPrepare($stmt)))->findByKey('user'));
    }

    // ── create ────────────────────────────────────────────────────

    public function testCreateThrowsWhenCodeAlreadyExists(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('fetch')->willReturn(['id' => 1, 'code' => 'admin']); // findByKey returns existing
        $this->expectException(RuntimeException::class);
        (new Role($this->stubPdoWithPrepare($stmt)))->create('admin', 'Admin');
    }

    public function testCreateReturnsInsertId(): void
    {
        $stmtFind = $this->createStub(PDOStatement::class);
        $stmtFind->method('fetch')->willReturn(false); // no existing

        $stmtInsert = $this->createStub(PDOStatement::class);
        $stmtInsert->method('execute')->willReturn(true);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturnOnConsecutiveCalls($stmtFind, $stmtInsert);
        $pdo->method('lastInsertId')->willReturn('5');

        $result = (new Role($pdo))->create('moderator', 'Moderator', 'Can moderate content');
        $this->assertSame(5, $result);
    }

    // ── update ────────────────────────────────────────────────────

    public function testUpdateReturnsFalseWhenNoFields(): void
    {
        $pdo = $this->createStub(PDO::class);
        $this->assertFalse((new Role($pdo))->update(1));
    }

    public function testUpdateReturnsTrueWhenNameProvided(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $this->assertTrue((new Role($this->stubPdoWithPrepare($stmt)))->update(1, 'New Name'));
    }

    public function testUpdateReturnsTrueWhenDescriptionProvided(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $this->assertTrue((new Role($this->stubPdoWithPrepare($stmt)))->update(1, null, 'New desc'));
    }

    // ── delete ────────────────────────────────────────────────────

    public function testDeleteReturnsTrueOnSuccess(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $this->assertTrue((new Role($this->stubPdoWithPrepare($stmt)))->delete(1));
    }

    public function testDeleteReturnsFalseOnFailure(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(false);
        $this->assertFalse((new Role($this->stubPdoWithPrepare($stmt)))->delete(1));
    }

    // ── getPermissions ────────────────────────────────────────────

    public function testGetPermissionsReturnsArray(): void
    {
        $rows = [['id' => 1, 'code' => 'view_users', 'name' => 'View Users']];
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('fetchAll')->willReturn($rows);
        $result = (new Role($this->stubPdoWithPrepare($stmt)))->getPermissions(1);
        $this->assertSame($rows, $result);
    }

    public function testGetPermissionsReturnsEmptyWhenNone(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('fetchAll')->willReturn([]);
        $this->assertSame([], (new Role($this->stubPdoWithPrepare($stmt)))->getPermissions(1));
    }

    // ── grantPermission ───────────────────────────────────────────

    public function testGrantPermissionReturnsTrueWhenAlreadyGranted(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('fetch')->willReturn(['role_id' => 1, 'permission_id' => 2]); // existing
        $this->assertTrue((new Role($this->stubPdoWithPrepare($stmt)))->grantPermission(1, 2));
    }

    public function testGrantPermissionInsertsAndReturnsTrue(): void
    {
        $stmtCheck = $this->createStub(PDOStatement::class);
        $stmtCheck->method('fetch')->willReturn(false); // not existing

        $stmtInsert = $this->createStub(PDOStatement::class);
        $stmtInsert->method('execute')->willReturn(true);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturnOnConsecutiveCalls($stmtCheck, $stmtInsert);

        $this->assertTrue((new Role($pdo))->grantPermission(1, 2));
    }

    // ── revokePermission ──────────────────────────────────────────

    public function testRevokePermissionReturnsTrueOnSuccess(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $this->assertTrue((new Role($this->stubPdoWithPrepare($stmt)))->revokePermission(1, 2));
    }

    // ── getAllWithPermissions ──────────────────────────────────────

    public function testGetAllWithPermissionsReturnsEmptyPermissionsWhenNull(): void
    {
        $rows = [
            ['id' => 1, 'code' => 'admin', 'name' => 'Admin', 'permission_ids' => null, 'permission_names' => null],
        ];
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('fetchAll')->willReturn($rows);

        $result = (new Role($this->stubPdoWithQuery($stmt)))->getAllWithPermissions();
        $this->assertCount(1, $result);
        $this->assertSame([], $result[0]['permissions']);
        $this->assertArrayNotHasKey('permission_ids', $result[0]);
    }

    public function testGetAllWithPermissionsBuildsPermissionsArray(): void
    {
        $rows = [
            [
                'id' => 1,
                'code' => 'admin',
                'name' => 'Admin',
                'permission_ids' => '1,2',
                'permission_names' => 'View,Edit',
            ],
        ];
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('fetchAll')->willReturn($rows);

        $result = (new Role($this->stubPdoWithQuery($stmt)))->getAllWithPermissions();
        $this->assertCount(2, $result[0]['permissions']);
        $this->assertSame(1, $result[0]['permissions'][0]['id']);
        $this->assertSame('View', $result[0]['permissions'][0]['name']);
    }

    // ── findAllWithCounts ─────────────────────────────────────────

    public function testFindAllWithCountsReturnsArray(): void
    {
        $rows = [['id' => 1, 'code' => 'admin', 'permissions_count' => 5, 'users_count' => 3]];
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('fetchAll')->willReturn($rows);
        $result = (new Role($this->stubPdoWithQuery($stmt)))->findAllWithCounts();
        $this->assertSame($rows, $result);
    }

    // ── getStats ──────────────────────────────────────────────────

    public function testGetStatsReturnsArray(): void
    {
        $stats = ['users_with_roles' => 10, 'total_roles' => 3, 'total_permissions' => 12];
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('fetch')->willReturn($stats);
        $result = (new Role($this->stubPdoWithQuery($stmt)))->getStats();
        $this->assertSame($stats, $result);
    }

    // ── countUsers ────────────────────────────────────────────────

    public function testCountUsersReturnsInteger(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('fetch')->willReturn(['count' => 7]);
        $result = (new Role($this->stubPdoWithPrepare($stmt)))->countUsers(1);
        $this->assertSame(7, $result);
    }

    public function testCountUsersReturnsZeroWhenNone(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('fetch')->willReturn(['count' => 0]);
        $result = (new Role($this->stubPdoWithPrepare($stmt)))->countUsers(99);
        $this->assertSame(0, $result);
    }
}
