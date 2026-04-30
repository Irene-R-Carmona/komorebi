<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? AdminReportService: getReportsSummary delega al repositorio y retorna Result.
 * ¿Qué me quieres demostrar? Que getReportsSummary retorna Result::ok con lo que devuelve el statsRepo.
 * ¿Qué va a fallar en este test si se cambia el código? Si se deja de delegar al repositorio, cambia la firma, o se elimina el Result pattern.
 */

namespace Tests\Unit\Services;

use App\Repositories\Contracts\StatisticsRepositoryInterface;
use App\Services\AdminReportService;
use PDOException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AdminReportService::class)]
final class AdminReportServiceTest extends TestCase
{
    public function testGetReportsSummaryDelegatesToRepository(): void
    {
        $expected = ['reservations' => 5, 'revenue' => 150.0];

        $statsRepoStub = $this->createStub(StatisticsRepositoryInterface::class);
        $statsRepoStub->method('getReportsSummary')->willReturn($expected);

        $service = new AdminReportService($statsRepoStub);
        $result = $service->getReportsSummary('2025-01-01', '2025-01-31');

        $this->assertTrue($result->ok);
        $this->assertSame($expected, $result->data);
    }

    public function testGetReportsSummaryReturnsFail(): void
    {
        $statsRepoStub = $this->createStub(StatisticsRepositoryInterface::class);
        $statsRepoStub->method('getReportsSummary')->willThrowException(new PDOException('DB error'));

        $service = new AdminReportService($statsRepoStub);
        $result = $service->getReportsSummary('2025-01-01', '2025-01-31');

        $this->assertFalse($result->ok);
        $this->assertSame('db_error', $result->code);
    }
}
