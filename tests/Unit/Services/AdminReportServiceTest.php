<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? AdminReportService: getReportsSummary delega al repositorio.
 * ¿Qué me quieres demostrar? Que getReportsSummary retorna lo que retorna el statsRepo.
 * ¿Qué va a fallar en este test si se cambia el código? Si se deja de delegar al repositorio o cambia la firma.
 */

namespace Tests\Unit\Services;

use App\Repositories\Contracts\StatisticsRepositoryInterface;
use App\Services\AdminReportService;
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
        $result  = $service->getReportsSummary('2025-01-01', '2025-01-31');

        $this->assertSame($expected, $result);
    }
}
