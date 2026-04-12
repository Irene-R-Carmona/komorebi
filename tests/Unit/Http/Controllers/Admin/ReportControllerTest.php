<?php

/**
 * ¿Qué pruebas aquí?
 * Verifica que Admin\ReportController acepta AdminService inyectado.
 *
 * ¿Qué me quieres demostrar?
 * Que el controlador expone los métodos de reportes esperados.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se renombran los métodos públicos o cambian las dependencias del constructor.
 */

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Admin;

use App\Core\Http\ResponseFactory;
use App\Http\Controllers\Admin\ReportController;
use App\Services\Contracts\AdminReportServiceInterface;
use App\Services\Contracts\AdminStatisticsServiceInterface;
use Tests\Support\ControllerTestCase;

final class ReportControllerTest extends ControllerTestCase
{
    private function makeController(): ReportController
    {
        return new ReportController(
            $this->createStub(AdminStatisticsServiceInterface::class),
            $this->createStub(AdminReportServiceInterface::class),
            new ResponseFactory()
        );
    }

    public function test_class_has_expected_methods(): void
    {
        $this->assertTrue(method_exists(ReportController::class, 'index'));
        $this->assertTrue(method_exists(ReportController::class, 'exportReportes'));
    }

    public function test_instance_can_be_created_with_stub(): void
    {
        $this->assertInstanceOf(ReportController::class, $this->makeController());
    }
}
