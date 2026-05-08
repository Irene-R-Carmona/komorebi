<?php

declare(strict_types=1);

namespace App\Providers;

use App\Core\Container;
use App\Core\Database;
use App\Core\ServiceProvider;
use App\Repositories\AuditLogRepository;
use App\Repositories\AuthLogRepository;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use App\Repositories\Contracts\AuthLogRepositoryInterface;
use App\Repositories\Contracts\RoleRepositoryInterface;
use App\Repositories\Contracts\StaffShiftRepositoryInterface;
use App\Repositories\Contracts\SupervisorAssignmentRepositoryInterface;
use App\Repositories\RoleRepository;
use App\Repositories\StaffShiftRepository;
use App\Repositories\SupervisorAssignmentRepository;
use App\Services\Contracts\StaffShiftServiceInterface;
use App\Services\Contracts\SupervisorAssignmentServiceInterface;
use App\Services\StaffShiftService;
use App\Services\SupervisorAssignmentService;
use Override;

final class StaffServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        Container::singleton(
            StaffShiftRepository::class,
            fn() => new StaffShiftRepository(Database::getConnection())
        );
        Container::singleton(
            StaffShiftRepositoryInterface::class,
            fn() => Container::make(StaffShiftRepository::class)
        );

        Container::singleton(
            StaffShiftService::class,
            fn() => new StaffShiftService(Container::make(StaffShiftRepository::class))
        );
        Container::singleton(
            StaffShiftServiceInterface::class,
            fn() => Container::make(StaffShiftService::class)
        );

        Container::singleton(
            SupervisorAssignmentRepository::class,
            fn() => new SupervisorAssignmentRepository(Database::getConnection())
        );
        Container::singleton(
            SupervisorAssignmentRepositoryInterface::class,
            fn() => Container::make(SupervisorAssignmentRepository::class)
        );

        Container::singleton(
            SupervisorAssignmentService::class,
            fn() => new SupervisorAssignmentService(Container::make(SupervisorAssignmentRepository::class))
        );

        Container::singleton(
            SupervisorAssignmentServiceInterface::class,
            fn() => Container::make(SupervisorAssignmentService::class)
        );

        Container::singleton(
            AuthLogRepository::class,
            fn() => new AuthLogRepository(Database::getConnection())
        );
        Container::singleton(
            AuthLogRepositoryInterface::class,
            fn() => Container::make(AuthLogRepository::class)
        );

        Container::singleton(
            AuditLogRepository::class,
            fn() => new AuditLogRepository(Database::getConnection())
        );
        Container::singleton(
            AuditLogRepositoryInterface::class,
            fn() => Container::make(AuditLogRepository::class)
        );

        Container::singleton(
            RoleRepository::class,
            fn() => new RoleRepository(Database::getConnection())
        );
        Container::singleton(
            RoleRepositoryInterface::class,
            fn() => Container::make(RoleRepository::class)
        );
    }

    #[Override]
    public function boot(): void
    {
        // No hay bootstrap específico
    }
}
