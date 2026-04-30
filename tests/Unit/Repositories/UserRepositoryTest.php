<?php

/**
 * ¿Qué prueba aquí? Métodos de UserRepository cubiertos con PDO stubs.
 * ¿Qué me quieres demostrar? Que cada método construye la query correcta y devuelve el tipo esperado.
 * ¿Qué va a fallar en este test si se cambia el código? Tests que esperen el tipo correcto de retorno o la lógica de cada método.
 */

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Domain\DTO\UserDTO;
use App\Repositories\UserRepository;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(UserRepository::class)]
final class UserRepositoryTest extends RepositoryTestCase
{
    // ------------------------------------------------------------------
    // findById
    // ------------------------------------------------------------------

    public function testFindByIdReturnsDtoWhenRowFound(): void
    {
        $pdo = $this->makePdo(fetchReturn: RowFactory::userRow());
        $repo = new UserRepository($pdo);

        $result = $repo->findById(1);

        $this->assertInstanceOf(UserDTO::class, $result);
    }

    public function testFindByIdReturnsNullWhenNoRow(): void
    {
        $pdo = $this->makePdo(fetchReturn: false);
        $repo = new UserRepository($pdo);

        $this->assertNull($repo->findById(99));
    }

    // ------------------------------------------------------------------
    // findByEmail
    // ------------------------------------------------------------------

    public function testFindByEmailReturnsArrayWhenFound(): void
    {
        $pdo = $this->makePdo(fetchReturn: RowFactory::userRow());
        $repo = new UserRepository($pdo);

        $result = $repo->findByEmail('user@test.com');

        $this->assertIsArray($result);
        $this->assertSame('user@test.com', $result['email']);
    }

    public function testFindByEmailReturnsNullWhenNotFound(): void
    {
        $pdo = $this->makePdo(fetchReturn: false);
        $repo = new UserRepository($pdo);

        $this->assertNull($repo->findByEmail('nobody@test.com'));
    }

    // ------------------------------------------------------------------
    // findByEmailWithCredentials
    // ------------------------------------------------------------------

    public function testFindByEmailWithCredentialsReturnsArrayWhenFound(): void
    {
        $row = ['id' => 1, 'uuid' => 'abc', 'email' => 'u@t.com', 'password' => '$2y$hash',
                 'login_attempts' => 0, 'locked_until' => null,
                 'last_ip_address' => null, 'is_active' => 1, 'email_verified_at' => null];
        $pdo = $this->makePdo(fetchReturn: $row);
        $repo = new UserRepository($pdo);

        $result = $repo->findByEmailWithCredentials('u@t.com');

        $this->assertIsArray($result);
        $this->assertSame(1, $result['id']);
    }

    public function testFindByEmailWithCredentialsReturnsNullWhenNotFound(): void
    {
        $pdo = $this->makePdo(fetchReturn: false);
        $repo = new UserRepository($pdo);

        $this->assertNull($repo->findByEmailWithCredentials('unknown@test.com'));
    }

    // ------------------------------------------------------------------
    // findByIdForSecurity
    // ------------------------------------------------------------------

    public function testFindByIdForSecurityReturnsArrayWhenFound(): void
    {
        $row = ['id' => 1, 'uuid' => 'abc', 'email' => 'u@t.com', 'password' => '$2y$h',
                 'login_attempts' => 0, 'locked_until' => null, 'last_ip_address' => null];
        $pdo = $this->makePdo(fetchReturn: $row);
        $repo = new UserRepository($pdo);

        $result = $repo->findByIdForSecurity(1);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('login_attempts', $result);
    }

    public function testFindByIdForSecurityReturnsNullWhenNotFound(): void
    {
        $pdo = $this->makePdo(fetchReturn: false);
        $repo = new UserRepository($pdo);

        $this->assertNull($repo->findByIdForSecurity(99));
    }

    // ------------------------------------------------------------------
    // emailExists
    // ------------------------------------------------------------------

    public function testEmailExistsReturnsTrueWhenFound(): void
    {
        $pdo = $this->makePdo(fetchReturn: ['1' => '1']);
        $repo = new UserRepository($pdo);

        $this->assertTrue($repo->emailExists('user@test.com'));
    }

    public function testEmailExistsReturnsFalseWhenNotFound(): void
    {
        $pdo = $this->makePdo(fetchReturn: false);
        $repo = new UserRepository($pdo);

        $this->assertFalse($repo->emailExists('nobody@test.com'));
    }

    // ------------------------------------------------------------------
    // getRoles
    // ------------------------------------------------------------------

    public function testGetRolesReturnsRoleRows(): void
    {
        $rows = [['name' => 'Admin', 'slug' => 'admin', 'description' => 'Administrador']];
        $pdo = $this->makePdo(fetchAllReturn: $rows);
        $repo = new UserRepository($pdo);

        $result = $repo->getRoles(1);

        $this->assertCount(1, $result);
        $this->assertSame('Admin', $result[0]['name']);
    }

    public function testGetRolesReturnsEmptyWhenNoRoles(): void
    {
        $pdo = $this->makePdo(fetchAllReturn: []);
        $repo = new UserRepository($pdo);

        $this->assertSame([], $repo->getRoles(1));
    }

    // ------------------------------------------------------------------
    // getPermissions
    // ------------------------------------------------------------------

    public function testGetPermissionsReturnsPermissionRows(): void
    {
        $rows = [['name' => 'edit_menu', 'slug' => 'edit_menu', 'resource' => 'menu', 'action' => 'edit']];
        $pdo = $this->makePdo(fetchAllReturn: $rows);
        $repo = new UserRepository($pdo);

        $result = $repo->getPermissions(1);

        $this->assertCount(1, $result);
        $this->assertSame('edit_menu', $result[0]['name']);
    }

    // ------------------------------------------------------------------
    // hasPermission
    // ------------------------------------------------------------------

    public function testHasPermissionReturnsTrueWhenGranted(): void
    {
        $pdo = $this->makePdo(fetchReturn: ['1' => '1']);
        $repo = new UserRepository($pdo);

        $this->assertTrue($repo->hasPermission(1, 'edit_menu'));
    }

    public function testHasPermissionReturnsFalseWhenNotGranted(): void
    {
        $pdo = $this->makePdo(fetchReturn: false);
        $repo = new UserRepository($pdo);

        $this->assertFalse($repo->hasPermission(1, 'delete_all'));
    }

    // ------------------------------------------------------------------
    // setActive / toggleStatus
    // ------------------------------------------------------------------

    public function testSetActiveReturnsTrueOnSuccess(): void
    {
        $pdo = $this->makePdo(rowCount: 1);
        $repo = new UserRepository($pdo);

        $this->assertTrue($repo->setActive(1, true));
    }

    public function testToggleStatusReturnsFalseWhenNotFound(): void
    {
        $pdo = $this->makePdo(fetchReturn: false);
        $repo = new UserRepository($pdo);

        $this->assertFalse($repo->toggleStatus(99));
    }

    public function testToggleStatusTogglesActiveUser(): void
    {
        $pdo = $this->makeMultiCallPdo([
            ['fetch' => RowFactory::userRow(['is_active' => 1])],
            ['rowCount' => 1],
        ]);
        $repo = new UserRepository($pdo);

        $this->assertTrue($repo->toggleStatus(1));
    }

    // ------------------------------------------------------------------
    // assignRole / removeRole / clearRoles
    // ------------------------------------------------------------------

    public function testAssignRoleReturnsTrueOnSuccess(): void
    {
        $pdo = $this->makePdo(rowCount: 1);
        $repo = new UserRepository($pdo);

        $this->assertTrue($repo->assignRole(1, 2));
    }

    public function testRemoveRoleReturnsTrueOnSuccess(): void
    {
        $pdo = $this->makePdo(rowCount: 1);
        $repo = new UserRepository($pdo);

        $this->assertTrue($repo->removeRole(1, 2));
    }

    public function testClearRolesReturnsTrueOnSuccess(): void
    {
        $pdo = $this->makePdo(rowCount: 1);
        $repo = new UserRepository($pdo);

        $this->assertTrue($repo->clearRoles(1));
    }

    // ------------------------------------------------------------------
    // updateLastLogin
    // ------------------------------------------------------------------

    public function testUpdateLastLoginReturnsTrueOnSuccess(): void
    {
        $pdo = $this->makePdo(rowCount: 1);
        $repo = new UserRepository($pdo);

        $this->assertTrue($repo->updateLastLogin(1, '127.0.0.1'));
    }

    // ------------------------------------------------------------------
    // incrementFailedAttempts / lockAccount
    // ------------------------------------------------------------------

    public function testIncrementFailedAttemptsReturnsTrueOnSuccess(): void
    {
        $pdo = $this->makePdo(rowCount: 1);
        $repo = new UserRepository($pdo);

        $this->assertTrue($repo->incrementFailedAttempts(1));
    }

    public function testLockAccountReturnsTrueOnSuccess(): void
    {
        $pdo = $this->makePdo(rowCount: 1);
        $repo = new UserRepository($pdo);

        $this->assertTrue($repo->lockAccount(1, 15));
    }

    // ------------------------------------------------------------------
    // updatePassword / verifyEmail / updateAvatar
    // ------------------------------------------------------------------

    public function testUpdatePasswordReturnsTrueOnSuccess(): void
    {
        $pdo = $this->makePdo(rowCount: 1);
        $repo = new UserRepository($pdo);

        $this->assertTrue($repo->updatePassword(1, 'NuevaContra123!'));
    }

    public function testVerifyEmailReturnsTrueOnSuccess(): void
    {
        $pdo = $this->makePdo(rowCount: 1);
        $repo = new UserRepository($pdo);

        $this->assertTrue($repo->verifyEmail(1));
    }

    public function testUpdateAvatarReturnsTrueOnSuccess(): void
    {
        $pdo = $this->makePdo(rowCount: 1);
        $repo = new UserRepository($pdo);

        $this->assertTrue($repo->updateAvatar(1, 'https://cdn.test/avatar.jpg'));
    }

    // ------------------------------------------------------------------
    // softDelete / updatePreferences / anonymize
    // ------------------------------------------------------------------

    public function testSoftDeleteReturnsTrueOnSuccess(): void
    {
        $pdo = $this->makePdo(rowCount: 1);
        $repo = new UserRepository($pdo);

        $this->assertTrue($repo->softDelete(1));
    }

    public function testUpdatePreferencesReturnsTrueOnSuccess(): void
    {
        $pdo = $this->makePdo(rowCount: 1);
        $repo = new UserRepository($pdo);

        $this->assertTrue($repo->updatePreferences(1, ['theme' => 'dark']));
    }

    public function testAnonymizeReturnsTrueOnSuccess(): void
    {
        $pdo = $this->makePdo(rowCount: 1);
        $repo = new UserRepository($pdo);

        $this->assertTrue($repo->anonymize(1));
    }

    // ------------------------------------------------------------------
    // findByRole
    // ------------------------------------------------------------------

    public function testFindByRoleReturnsUsers(): void
    {
        $rows = [RowFactory::userRow()];
        $pdo = $this->makePdo(fetchAllReturn: $rows);
        $repo = new UserRepository($pdo);

        $result = $repo->findByRole('admin');

        $this->assertCount(1, $result);
    }

    public function testFindByRoleReturnsEmptyWhenNone(): void
    {
        $pdo = $this->makePdo(fetchAllReturn: []);
        $repo = new UserRepository($pdo);

        $this->assertSame([], $repo->findByRole('keeper'));
    }

    // ------------------------------------------------------------------
    // getActiveUsersList
    // ------------------------------------------------------------------

    public function testGetActiveUsersListReturnsRows(): void
    {
        $rows = [['id' => 1, 'name' => 'Test User', 'email' => 'u@t.com']];
        $pdo = $this->makePdo(fetchAllReturn: $rows);
        $repo = new UserRepository($pdo);

        $result = $repo->getActiveUsersList();

        $this->assertCount(1, $result);
        $this->assertSame('Test User', $result[0]['name']);
    }

    // ------------------------------------------------------------------
    // getStaffByCafe
    // ------------------------------------------------------------------

    public function testGetStaffByCafeReturnsRows(): void
    {
        $rows = [RowFactory::userRow(['cafe_id' => 1])];
        $pdo = $this->makePdo(fetchAllReturn: $rows);
        $repo = new UserRepository($pdo);

        $result = $repo->getStaffByCafe(1);

        $this->assertCount(1, $result);
    }

    // ------------------------------------------------------------------
    // getStaffById / getStaffBasicById
    // ------------------------------------------------------------------

    public function testGetStaffByIdReturnsArrayWhenFound(): void
    {
        $pdo = $this->makePdo(fetchReturn: RowFactory::userRow(['cafe_id' => 1]));
        $repo = new UserRepository($pdo);

        $result = $repo->getStaffById(1, 1);

        $this->assertIsArray($result);
    }

    public function testGetStaffByIdReturnsNullWhenNotFound(): void
    {
        $pdo = $this->makePdo(fetchReturn: false);
        $repo = new UserRepository($pdo);

        $this->assertNull($repo->getStaffById(99, 1));
    }

    public function testGetStaffBasicByIdReturnsArrayWhenFound(): void
    {
        $pdo = $this->makePdo(fetchReturn: ['id' => 1, 'name' => 'Keeper Ana']);
        $repo = new UserRepository($pdo);

        $result = $repo->getStaffBasicById(1, 1);

        $this->assertIsArray($result);
        $this->assertSame('Keeper Ana', $result['name']);
    }

    public function testGetStaffBasicByIdReturnsNullWhenNotFound(): void
    {
        $pdo = $this->makePdo(fetchReturn: false);
        $repo = new UserRepository($pdo);

        $this->assertNull($repo->getStaffBasicById(99, 1));
    }

    // ------------------------------------------------------------------
    // existsInCafe
    // ------------------------------------------------------------------

    public function testExistsInCafeReturnsTrueWhenBelongs(): void
    {
        $pdo = $this->makePdo(fetchReturn: ['id' => 1]);
        $repo = new UserRepository($pdo);

        $this->assertTrue($repo->existsInCafe(1, 1));
    }

    public function testExistsInCafeReturnsFalseWhenNotBelongs(): void
    {
        $pdo = $this->makePdo(fetchReturn: false);
        $repo = new UserRepository($pdo);

        $this->assertFalse($repo->existsInCafe(1, 99));
    }

    // ------------------------------------------------------------------
    // getUsersWithRoles
    // ------------------------------------------------------------------

    public function testGetUsersWithRolesReturnsRows(): void
    {
        $rows = [RowFactory::userRow(['roles' => 'admin', 'role_ids' => '1'])];
        $pdo = $this->makePdo(fetchAllReturn: $rows);
        $repo = new UserRepository($pdo);

        $result = $repo->getUsersWithRoles();

        $this->assertCount(1, $result);
    }

    public function testGetUsersWithRolesReturnsEmptyWhenQueryFails(): void
    {
        $pdo = $this->createStub(PDO::class);
        $pdo->method('query')->willReturn(false);
        $repo = new UserRepository($pdo);

        $this->assertSame([], $repo->getUsersWithRoles());
    }

    // ------------------------------------------------------------------
    // getUserStats
    // ------------------------------------------------------------------

    public function testGetUserStatsReturnsExpectedKeys(): void
    {
        $row = ['total' => 10, 'active' => 8, 'inactive' => 2, 'admins' => 1];
        $pdo = $this->makePdo(fetchReturn: $row);
        $repo = new UserRepository($pdo);

        $result = $repo->getUserStats();

        $this->assertArrayHasKey('total_users', $result);
        $this->assertArrayHasKey('active_users', $result);
        $this->assertArrayHasKey('inactive_users', $result);
        $this->assertArrayHasKey('admin_users', $result);
        $this->assertSame(10, $result['total_users']);
    }

    public function testGetUserStatsReturnsDefaultsWhenQueryFails(): void
    {
        $pdo = $this->createStub(PDO::class);
        $pdo->method('query')->willReturn(false);
        $repo = new UserRepository($pdo);

        $result = $repo->getUserStats();

        $this->assertSame(0, $result['total_users']);
        $this->assertSame(0, $result['admin_users']);
    }

    // ------------------------------------------------------------------
    // verifyPassword
    // ------------------------------------------------------------------

    public function testVerifyPasswordReturnsFalseWhenMissingFields(): void
    {
        $pdo = $this->makePdo();
        $repo = new UserRepository($pdo);

        $this->assertFalse($repo->verifyPassword([], 'pass'));
        $this->assertFalse($repo->verifyPassword(['id' => 1], 'pass'));
    }

    public function testVerifyPasswordReturnsFalseWhenWrongPassword(): void
    {
        $user = ['id' => 1, 'password' => \password_hash('correct', PASSWORD_ARGON2ID)];
        $pdo = $this->makePdo(rowCount: 1);
        $repo = new UserRepository($pdo);

        $this->assertFalse($repo->verifyPassword($user, 'wrong'));
    }

    public function testVerifyPasswordReturnsTrueWhenCorrect(): void
    {
        $user = ['id' => 1, 'password' => \password_hash('correct', PASSWORD_ARGON2ID)];
        $pdo = $this->makePdo(rowCount: 1);
        $repo = new UserRepository($pdo);

        $this->assertTrue($repo->verifyPassword($user, 'correct'));
    }

    // ------------------------------------------------------------------
    // isLocked / lockoutMinutesRemaining
    // ------------------------------------------------------------------

    public function testIsLockedReturnsFalseWhenNoLockUntil(): void
    {
        $pdo = $this->makePdo();
        $repo = new UserRepository($pdo);

        $this->assertFalse($repo->isLocked([]));
        $this->assertFalse($repo->isLocked(['locked_until' => null]));
    }

    public function testIsLockedReturnsTrueWhenFutureTime(): void
    {
        $pdo = $this->makePdo();
        $repo = new UserRepository($pdo);

        $future = \date('Y-m-d H:i:s', \time() + 600);
        $this->assertTrue($repo->isLocked(['locked_until' => $future]));
    }

    public function testIsLockedReturnsFalseWhenPastTime(): void
    {
        $pdo = $this->makePdo();
        $repo = new UserRepository($pdo);

        $past = \date('Y-m-d H:i:s', \time() - 600);
        $this->assertFalse($repo->isLocked(['locked_until' => $past]));
    }

    public function testLockoutMinutesRemainingReturnsZeroWhenNotLocked(): void
    {
        $pdo = $this->makePdo();
        $repo = new UserRepository($pdo);

        $this->assertSame(0, $repo->lockoutMinutesRemaining([]));
    }

    public function testLockoutMinutesRemainingReturnsPositiveWhenLocked(): void
    {
        $pdo = $this->makePdo();
        $repo = new UserRepository($pdo);
        $future = \date('Y-m-d H:i:s', \time() + 600);

        $minutes = $repo->lockoutMinutesRemaining(['locked_until' => $future]);

        $this->assertGreaterThan(0, $minutes);
    }

    // ------------------------------------------------------------------
    // create (UUID auto-generation)
    // ------------------------------------------------------------------

    public function testCreateGeneratesUuidAndReturnsId(): void
    {
        $pdo = $this->makePdo(lastInsertId: '42');
        $repo = new UserRepository($pdo);

        $id = $repo->create(['name' => 'New User', 'email' => 'new@test.com', 'password' => 'hash']);

        $this->assertSame(42, $id);
    }

    public function testCreateUsesProvidedUuidWhenGiven(): void
    {
        $pdo = $this->makePdo(lastInsertId: '5');
        $repo = new UserRepository($pdo);

        $id = $repo->create(['name' => 'User', 'email' => 'u@t.com', 'password' => 'h',
                             'uuid' => 'custom-uuid-1234']);

        $this->assertSame(5, $id);
    }

    // ------------------------------------------------------------------
    // clearLoginAttempts / registerFailedAttempt
    // ------------------------------------------------------------------

    public function testClearLoginAttemptsExecutesWithoutException(): void
    {
        $pdo = $this->makePdo(rowCount: 1);
        $repo = new UserRepository($pdo);

        $repo->clearLoginAttempts(1);
        $this->assertTrue(true);
    }

    public function testRegisterFailedAttemptExecutesWithoutException(): void
    {
        $pdo = $this->makeMultiCallPdo([
            ['rowCount' => 1],
            ['fetch' => ['id' => 1, 'login_attempts' => 2, 'locked_until' => null,
                            'uuid' => 'abc', 'email' => 'u@t.com', 'password' => '',
                            'last_ip_address' => null]],
        ]);
        $repo = new UserRepository($pdo);

        $repo->registerFailedAttempt(1);
        $this->assertTrue(true);
    }
}
