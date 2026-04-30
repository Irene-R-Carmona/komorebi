<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? AdminActivityService: getSystemStatus y getRecentReservations con Result pattern.
 * ¿Qué me quieres demostrar? Que los métodos devuelven Result::ok con datos o Result::fail ante errores.
 * ¿Qué va a fallar en este test si se cambia el código? Si se elimina el Result pattern o cambian las claves de los arrays retornados.
 */

namespace Tests\Unit\Services;

use App\Services\AdminActivityService;
use PDO;
use PDOException;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AdminActivityService::class)]
final class AdminActivityServiceTest extends TestCase
{
    public function testGetSystemStatusReturnsDatabaseOfflineWhenPdoThrows(): void
    {
        $pdoStub = $this->createStub(PDO::class);
        $pdoStub->method('query')->willThrowException(new PDOException('Connection refused'));

        $service = new AdminActivityService($pdoStub);
        $status = $service->getSystemStatus();

        $this->assertTrue($status->ok);
        $this->assertArrayHasKey('database', $status->data);
        $this->assertSame('offline', $status->data['database']);
    }

    public function testGetSystemStatusReturnsDatabaseOnlineWhenPdoSucceeds(): void
    {
        $stmtStub = $this->createStub(PDOStatement::class);
        $stmtStub->method('execute')->willReturn(true);

        $pdoStub = $this->createStub(PDO::class);
        $pdoStub->method('query')->willReturn($stmtStub);

        $service = new AdminActivityService($pdoStub);
        $status = $service->getSystemStatus();

        $this->assertTrue($status->ok);
        $this->assertArrayHasKey('database', $status->data);
        $this->assertSame('online', $status->data['database']);
    }

    public function testGetRecentReservationsReturnsEmptyArrayWhenPdoThrows(): void
    {
        $pdoStub = $this->createStub(PDO::class);
        $pdoStub->method('prepare')->willThrowException(new PDOException('DB error'));

        $service = new AdminActivityService($pdoStub);
        $result = $service->getRecentReservations(5);

        $this->assertFalse($result->ok);
        $this->assertSame('db_error', $result->code);
    }

    public function testGetRecentReservationsReturnsResultWithExpectedShape(): void
    {
        $row = [
            'id' => 1,
            'date' => '2025-01-15',
            'time_slot' => '14:00',
            'status' => 'confirmed',
            'guests' => 2,
            'cafe_name' => 'Komorebi Central',
            'customer_name' => 'Ana García',
            'created_at' => '2025-01-10 10:00:00',
        ];

        $stmtStub = $this->createStub(PDOStatement::class);
        $stmtStub->method('execute')->willReturn(true);
        $stmtStub->method('fetchAll')->willReturn([$row]);
        $pdoStub = $this->createStub(PDO::class);
        $pdoStub->method('prepare')->willReturn($stmtStub);

        $service = new AdminActivityService($pdoStub);
        $result = $service->getRecentReservations(1);

        $this->assertTrue($result->ok);
        $this->assertCount(1, $result->data);
        $this->assertSame('Komorebi Central', $result->data[0]['cafe_name']);
    }

    public function testGetUsersWithRolesMergesMultipleRolesForSameUser(): void
    {
        $rows = [
            ['id' => 1, 'name' => 'Ana', 'email' => 'ana@example.com', 'is_active' => 1, 'created_at' => '2024-01-01', 'role_id' => 10, 'role_name' => 'admin'],
            ['id' => 1, 'name' => 'Ana', 'email' => 'ana@example.com', 'is_active' => 1, 'created_at' => '2024-01-01', 'role_id' => 11, 'role_name' => 'manager'],
        ];

        $stmtStub = $this->createStub(PDOStatement::class);
        $stmtStub->method('fetchAll')->willReturn($rows);
        $pdoStub = $this->createStub(PDO::class);
        $pdoStub->method('query')->willReturn($stmtStub);

        $service = new AdminActivityService($pdoStub);
        $result = $service->getUsersWithRoles();

        $this->assertTrue($result->ok);
        $this->assertCount(1, $result->data);
        $this->assertArrayHasKey('roles', $result->data[0]);
        $this->assertCount(2, $result->data[0]['roles']);
        $this->assertContains('admin', $result->data[0]['roles']);
        $this->assertContains('manager', $result->data[0]['roles']);
    }

    public function testGetUsersWithRolesReturnsUserWithNoRolesWhenRoleNameIsNull(): void
    {
        $rows = [
            ['id' => 2, 'name' => 'Luis', 'email' => 'luis@example.com', 'is_active' => 0, 'created_at' => '2024-02-01', 'role_id' => null, 'role_name' => null],
        ];

        $stmtStub = $this->createStub(PDOStatement::class);
        $stmtStub->method('fetchAll')->willReturn($rows);
        $pdoStub = $this->createStub(PDO::class);
        $pdoStub->method('query')->willReturn($stmtStub);

        $service = new AdminActivityService($pdoStub);
        $result = $service->getUsersWithRoles();

        $this->assertTrue($result->ok);
        $this->assertCount(1, $result->data);
        $this->assertSame([], $result->data[0]['roles']);
    }

    public function testGetProductsWithCategoriesReturnsOkWithData(): void
    {
        $row = ['id' => 1, 'name' => 'Matcha Latte', 'category_name' => 'Bebidas'];

        $stmtStub = $this->createStub(PDOStatement::class);
        $stmtStub->method('fetchAll')->willReturn([$row]);
        $pdoStub = $this->createStub(PDO::class);
        $pdoStub->method('query')->willReturn($stmtStub);

        $service = new AdminActivityService($pdoStub);
        $result = $service->getProductsWithCategories();

        $this->assertTrue($result->ok);
        $this->assertCount(1, $result->data);
        $this->assertSame('Matcha Latte', $result->data[0]['name']);
    }

    public function testGetProductsWithCategoriesReturnsFailOnPdoException(): void
    {
        $pdoStub = $this->createStub(PDO::class);
        $pdoStub->method('query')->willThrowException(new PDOException('DB error'));

        $service = new AdminActivityService($pdoStub);
        $result = $service->getProductsWithCategories();

        $this->assertFalse($result->ok);
        $this->assertSame('db_error', $result->code);
    }

    public function testGetReservationsWithDetailsReturnsOkWithData(): void
    {
        $row = ['id' => 1, 'cafe_name' => 'Komorebi Central', 'customer_name' => 'Ana', 'customer_email' => 'ana@example.com'];

        $stmtStub = $this->createStub(PDOStatement::class);
        $stmtStub->method('execute')->willReturn(true);
        $stmtStub->method('fetchAll')->willReturn([$row]);
        $pdoStub = $this->createStub(PDO::class);
        $pdoStub->method('prepare')->willReturn($stmtStub);

        $service = new AdminActivityService($pdoStub);
        $result = $service->getReservationsWithDetails(10);

        $this->assertTrue($result->ok);
        $this->assertCount(1, $result->data);
        $this->assertSame('Komorebi Central', $result->data[0]['cafe_name']);
    }

    public function testGetReservationsWithDetailsReturnsFailOnPdoException(): void
    {
        $pdoStub = $this->createStub(PDO::class);
        $pdoStub->method('prepare')->willThrowException(new PDOException('DB error'));

        $service = new AdminActivityService($pdoStub);
        $result = $service->getReservationsWithDetails();

        $this->assertFalse($result->ok);
        $this->assertSame('db_error', $result->code);
    }

    public function testGetRecentActivityReturnsOkWithMergedResults(): void
    {
        $twoHoursAgo = \date('Y-m-d H:i:s', \time() - 7200);

        $stmtRes = $this->createStub(PDOStatement::class);
        $stmtRes->method('fetchAll')->willReturn([
            ['cafe_name' => 'Café Komorebi', 'user_name' => 'Ana', 'created_at' => $twoHoursAgo],
        ]);

        $stmtUsers = $this->createStub(PDOStatement::class);
        $stmtUsers->method('fetchAll')->willReturn([
            ['email' => 'nueva@example.com', 'created_at' => $twoHoursAgo],
        ]);

        $stmtReviews = $this->createStub(PDOStatement::class);
        $stmtReviews->method('fetchAll')->willReturn([
            ['cafe_name' => 'Café Komorebi', 'created_at' => $twoHoursAgo],
        ]);

        $pdoStub = $this->createStub(PDO::class);
        $pdoStub->method('query')->willReturnOnConsecutiveCalls($stmtRes, $stmtUsers, $stmtReviews);

        $service = new AdminActivityService($pdoStub);
        $result = $service->getRecentActivity(10);

        $this->assertTrue($result->ok);
        $this->assertIsArray($result->data);
        $this->assertCount(3, $result->data);
    }

    public function testGetRecentActivityReturnsFailOnPdoException(): void
    {
        $pdoStub = $this->createStub(PDO::class);
        $pdoStub->method('query')->willThrowException(new PDOException('DB error'));

        $service = new AdminActivityService($pdoStub);
        $result = $service->getRecentActivity();

        $this->assertFalse($result->ok);
        $this->assertSame('db_error', $result->code);
    }

    public function testGetReservationsChartDataReturnsOkWithLabelsAndValues(): void
    {
        $stmtStub = $this->createStub(PDOStatement::class);
        $stmtStub->method('execute')->willReturn(true);
        $stmtStub->method('fetchColumn')->willReturn(5);

        $pdoStub = $this->createStub(PDO::class);
        $pdoStub->method('prepare')->willReturn($stmtStub);

        $service = new AdminActivityService($pdoStub);
        $result = $service->getReservationsChartData();

        $this->assertTrue($result->ok);
        $this->assertArrayHasKey('labels', $result->data);
        $this->assertArrayHasKey('values', $result->data);
        $this->assertCount(7, $result->data['labels']);
        $this->assertCount(7, $result->data['values']);
    }

    public function testGetReservationsChartDataReturnsFailOnException(): void
    {
        $pdoStub = $this->createStub(PDO::class);
        $pdoStub->method('prepare')->willThrowException(new PDOException('DB error'));

        $service = new AdminActivityService($pdoStub);
        $result = $service->getReservationsChartData();

        $this->assertFalse($result->ok);
        $this->assertSame('chart_error', $result->code);
    }
}
