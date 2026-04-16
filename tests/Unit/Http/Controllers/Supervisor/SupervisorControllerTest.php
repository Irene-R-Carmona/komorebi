<?php

/**
 * ¿Qué pruebas aquí?
 * Smoke test de Supervisor\SupervisorController: métodos y constructor con deps reales.
 *
 * ¿Qué me quieres demostrar?
 * Que el controlador expone index(), assignments() y createAssignment(),
 * y que acepta ReservationRepository, KitchenService, SupervisorAssignmentService
 * como dependencias inyectables.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se renombra alguno de los métodos del supervisor o si se rompe el contrato del constructor.
 */

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Supervisor;

use App\Http\Controllers\Supervisor\SupervisorController;
use App\Repositories\ReservationRepository;
use App\Services\KitchenService;
use App\Services\SupervisorAssignmentService;
use Tests\Support\ControllerTestCase;

final class SupervisorControllerTest extends ControllerTestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function test_class_has_expected_methods(): void
    {
        $this->assertTrue(\method_exists(SupervisorController::class, 'index'));
        $this->assertTrue(\method_exists(SupervisorController::class, 'assignments'));
        $this->assertTrue(\method_exists(SupervisorController::class, 'createAssignment'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_instance_can_be_created_with_dependencies(): void
    {
        $assignmentService = $this->createStub(SupervisorAssignmentService::class);
        $reservationRepo = new ReservationRepository();
        $kitchenService = new KitchenService();

        $controller = new SupervisorController($assignmentService, $reservationRepo, $kitchenService);
        $this->assertInstanceOf(SupervisorController::class, $controller);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_instance_can_be_created_with_only_required_dependency(): void
    {
        $assignmentService = $this->createStub(SupervisorAssignmentService::class);

        $controller = new SupervisorController($assignmentService);
        $this->assertInstanceOf(SupervisorController::class, $controller);
    }
}
