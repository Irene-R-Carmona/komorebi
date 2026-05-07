<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Session;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Session::class)]
final class SessionTest extends TestCase
{
    protected function setUp(): void
    {
        // Iniciar sesión si no está activa, luego limpiarla
        Session::start();
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    // ─── Generic helpers ───────────────────────────────────────────────────────

    public function testSetStoresValueInSession(): void
    {
        Session::set('foo', 'bar');

        self::assertSame('bar', $_SESSION['foo']);
    }

    public function testGetReturnsStoredValue(): void
    {
        $_SESSION['mykey'] = 'myval';

        self::assertSame('myval', Session::get('mykey'));
    }

    public function testGetReturnsDefaultForMissingKey(): void
    {
        self::assertNull(Session::get('nonexistent'));
    }

    public function testGetReturnsCustomDefault(): void
    {
        self::assertSame(42, Session::get('nonexistent', 42));
    }

    public function testHasReturnsTrueForExistingKey(): void
    {
        $_SESSION['exists'] = true;

        self::assertTrue(Session::has('exists'));
    }

    public function testHasReturnsFalseForMissingKey(): void
    {
        self::assertFalse(Session::has('missing'));
    }

    public function testRemoveDeletesKey(): void
    {
        $_SESSION['todelete'] = 'value';
        Session::remove('todelete');

        self::assertNull(Session::get('todelete'));
    }

    public function testPullReturnsValueAndRemovesKey(): void
    {
        $_SESSION['token'] = 'abc123';

        $value = Session::pull('token');

        self::assertSame('abc123', $value);
        self::assertNull(Session::get('token'));
    }

    public function testPullReturnsDefaultWhenKeyMissing(): void
    {
        self::assertNull(Session::pull('nonexistent'));
    }

    public function testAllReturnsSessionArray(): void
    {
        $_SESSION['a'] = 1;
        $_SESSION['b'] = 2;

        $all = Session::all();

        self::assertSame(1, $all['a']);
        self::assertSame(2, $all['b']);
    }

    // ─── Authentication state ──────────────────────────────────────────────────

    public function testIsAuthenticatedReturnsFalseWhenNoUserId(): void
    {
        self::assertFalse(Session::isAuthenticated());
    }

    public function testIsAuthenticatedReturnsTrueWhenUserIdSet(): void
    {
        $_SESSION['user_id'] = 5;

        self::assertTrue(Session::isAuthenticated());
    }

    public function testUserIdReturnsNullWhenNotSet(): void
    {
        self::assertNull(Session::userId());
    }

    public function testUserIdReturnsIntWhenSet(): void
    {
        $_SESSION['user_id'] = '7';

        self::assertSame(7, Session::userId());
    }

    // ─── Role resolution ───────────────────────────────────────────────────────

    public function testRoleReturnsGuestWhenNoRoleInSession(): void
    {
        self::assertSame('guest', Session::role());
    }

    public function testRoleReturnsUserRoleKeyWhenPresent(): void
    {
        $_SESSION['user_role'] = 'admin';

        self::assertSame('admin', Session::role());
    }

    public function testRoleReturnsFirstElementFromUserRolesArray(): void
    {
        $_SESSION['user_roles'] = ['manager', 'user'];

        self::assertSame('manager', Session::role());
    }

    public function testRoleReturnsGuestForEmptyRolesArray(): void
    {
        $_SESSION['user_roles'] = [];

        self::assertSame('guest', Session::role());
    }

    // ─── User fields ───────────────────────────────────────────────────────────

    public function testUserNameReturnsEmptyStringWhenNotSet(): void
    {
        self::assertSame('', Session::userName());
    }

    public function testUserNameReturnsStoredName(): void
    {
        $_SESSION['user_name'] = 'Irene';

        self::assertSame('Irene', Session::userName());
    }

    public function testUserEmailReturnsEmptyStringWhenNotSet(): void
    {
        self::assertSame('', Session::userEmail());
    }

    public function testUserEmailReturnsStoredEmail(): void
    {
        $_SESSION['user_email'] = 'irene@example.com';

        self::assertSame('irene@example.com', Session::userEmail());
    }

    public function testUserCafeIdReturnsNullWhenNotSet(): void
    {
        self::assertNull(Session::userCafeId());
    }

    public function testUserCafeIdReturnsIntWhenSet(): void
    {
        $_SESSION['user_cafe_id'] = '3';

        self::assertSame(3, Session::userCafeId());
    }

    public function testUserReturnsAggregatedStructure(): void
    {
        $_SESSION['user_id'] = 10;
        $_SESSION['user_name'] = 'Ana';
        $_SESSION['user_email'] = 'ana@cafe.com';
        $_SESSION['user_role'] = 'barista';
        $_SESSION['user_cafe_id'] = 2;

        $user = Session::user();

        self::assertSame(10, $user['id']);
        self::assertSame('Ana', $user['name']);
        self::assertSame('ana@cafe.com', $user['email']);
        self::assertSame('barista', $user['role']);
        self::assertSame(2, $user['cafe_id']);
    }

    // ─── setUser ───────────────────────────────────────────────────────────────

    public function testSetUserWithStringRoleSetsSessionData(): void
    {
        Session::setUser([
            'id' => 1,
            'name' => 'Carlos',
            'email' => 'carlos@komorebi.jp',
            'role' => 'admin',
        ]);

        self::assertSame(1, $_SESSION['user_id']);
        self::assertSame('Carlos', $_SESSION['user_name']);
        self::assertSame('carlos@komorebi.jp', $_SESSION['user_email']);
        self::assertSame('admin', $_SESSION['user_role']);
    }

    public function testSetUserWithArrayRolesMapsAliases(): void
    {
        Session::setUser([
            'id' => 2,
            'name' => 'Yuki',
            'email' => 'yuki@komorebi.jp',
            'role' => ['techou', 'encargado'],
        ]);

        self::assertContains('manager', $_SESSION['user_roles']);
        self::assertContains('supervisor', $_SESSION['user_roles']);
    }

    public function testSetUserWithArrayRolesSetsPrimaryRole(): void
    {
        Session::setUser([
            'id' => 3,
            'name' => 'Takeshi',
            'email' => 'takeshi@komorebi.jp',
            'role' => ['admin', 'user'],
        ]);

        self::assertSame('admin', $_SESSION['user_role']);
    }

    public function testSetUserWithNoRoleDefaultsToUser(): void
    {
        Session::setUser([
            'id' => 4,
            'name' => 'Guest',
        ]);

        self::assertSame('user', $_SESSION['user_role']);
    }

    public function testSetUserDeduplicatesRoles(): void
    {
        Session::setUser([
            'id' => 5,
            'name' => 'Dup',
            'role' => ['admin', 'admin', 'admin'],
        ]);

        self::assertCount(1, $_SESSION['user_roles']);
    }

    // ─── Permissions cache ─────────────────────────────────────────────────────

    public function testGetPermissionsCacheReturnsEmptyArrayWhenNotSet(): void
    {
        self::assertSame([], Session::getPermissionsCache());
    }

    public function testGetPermissionsCacheReturnsStoredCache(): void
    {
        // La clave real en $_SESSION es 'user_permissions'
        $_SESSION['user_permissions'] = ['posts.edit' => true, 'users.delete' => false];

        self::assertSame(['posts.edit' => true, 'users.delete' => false], Session::getPermissionsCache());
    }

    public function testHasPermissionCachedReturnsNullWhenNoCache(): void
    {
        // Sin 'user_permissions' en sesión devuelve null
        self::assertNull(Session::hasPermissionCached('posts.edit'));
    }

    public function testHasPermissionCachedReturnsTrueWhenPermissionKeyExists(): void
    {
        // hasPermissionCached devuelve isset($cache[$permission]), no el valor almacenado
        $_SESSION['user_permissions'] = ['posts.edit' => true];

        self::assertTrue(Session::hasPermissionCached('posts.edit'));
    }

    public function testHasPermissionCachedReturnsFalseWhenPermissionKeyMissing(): void
    {
        // Cache existe pero la clave del permiso no está
        $_SESSION['user_permissions'] = ['posts.view' => true];

        self::assertFalse(Session::hasPermissionCached('users.delete'));
    }

    public function testInvalidatePermissionsCacheClearsCache(): void
    {
        // Las claves reales son 'user_permissions' y 'permissions_cached_at'
        $_SESSION['user_permissions'] = ['posts.edit' => true];
        $_SESSION['permissions_cached_at'] = \time();

        Session::invalidatePermissionsCache();

        self::assertSame([], Session::getPermissionsCache());
        self::assertNull(Session::get('permissions_cached_at'));
    }

    // ─── Start initializes superglobal ─────────────────────────────────────────

    public function testStartEnsuresSessionSuperglobalExists(): void
    {
        // Aunque ya está iniciada, start() no debe romper el estado actual
        $_SESSION['marker'] = 'intact';
        Session::start();

        self::assertSame('intact', $_SESSION['marker']);
    }
}
