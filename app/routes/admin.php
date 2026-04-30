<?php

declare(strict_types=1);

use App\Core\Env;
use App\Core\Router;
use Psr\Http\Server\MiddlewareInterface;

/** @var \App\Core\Router $router */
/** @var \App\Core\MiddlewareFactory $mw */
/** @var \App\Core\Http\ResponseFactory $responseFactory */

// ============================================================================
// BACKOFFICE (FEATURE_BACKOFFICE) — admin / manager / supervisor
// ============================================================================

if (Env::bool('FEATURE_BACKOFFICE', true)) {

    // ============================================================================
    // BACKOFFICE - ADMIN
    // ============================================================================

    $router->get('/admin', function () use ($responseFactory) {
        return $responseFactory->redirect('/admin/dashboard', 302);
    });

    /** @var array<int, MiddlewareInterface> $adminMiddleware */
    $adminMiddleware = [$mw->auth(), $mw->role('admin')];
    $router->group(['prefix' => '/admin', 'middleware' => $adminMiddleware], function (Router $r) use ($mw) {
        $r->get('/dashboard', 'Admin\DashboardController@index');
        $r->get('/users', 'Admin\UserController@index');
        $r->get('/users/create', 'Admin\UserController@create');
        $r->get('/users/{id}/edit', 'Admin\UserController@edit');

        $r->get('/roles', 'Admin\RoleController@index');
        $r->get('/menu/{id}/edit', 'Admin\MenuController@edit');

        $r->get('/reviews', 'Admin\ReviewController@index');

        $r->get('/reservations', 'Admin\ReservationController@index');
        $r->get('/reservations/{id}', 'Admin\ReservationController@show');

        $r->get('/waitlists', 'Admin\WaitlistController@index');
        $r->get('/waitlists/{id}', 'Admin\WaitlistController@show');
        $r->post('/waitlists/{id}/cancel', 'Admin\WaitlistController@cancel', [$mw->csrf()]);

        $r->get('/animals', 'Admin\AnimalController@index');
        $r->get('/animals/create', 'Admin\AnimalController@create');
        $r->post('/animals', 'Admin\AnimalController@store', [$mw->csrf()]);
        $r->get('/animals/{id}/edit', 'Admin\AnimalController@edit');
        $r->post('/animals/{id}', 'Admin\AnimalController@update', [$mw->csrf()]);
        $r->post('/animals/{id}/delete', 'Admin\AnimalController@delete', [$mw->csrf()]);

        $r->get('/settings', 'Admin\SystemController@settings');
        $r->get('/logs', 'Admin\SystemController@logs');
        $r->get('/data-viewer', 'Admin\DataViewerController@index');

        $r->get('/logs/audit', 'Admin\AuditLogController@index');
        $r->get('/logs/audit/export', 'Admin\AuditLogController@export');
        $r->get('/logs/auth', 'Admin\AuthLogController@index');
        $r->get('/logs/auth/suspicious-count', 'Admin\AuthLogController@suspiciousCount');
        $r->get('/logs/auth/export', 'Admin\AuthLogController@export');

        $r->get('/reports', 'Admin\ReportController@index');
        $r->get('/reports/export', 'Admin\ReportController@exportReportes');
    });

    // ============================================================================
    // BACKOFFICE - MANAGER
    // ============================================================================

    /** @var array<int, MiddlewareInterface> $managerMiddleware */
    $managerMiddleware = [$mw->auth(), $mw->role(['admin', 'manager'])];
    $router->group(['prefix' => '/manager', 'middleware' => $managerMiddleware], function (Router $r) use ($mw) {
        $r->get('/dashboard', 'Manager\DashboardController@index');
        $r->get('/reservations', 'Manager\ReservationController@index');
        $r->get('/reviews', 'Manager\ReviewController@index');

        // Staff Management — vistas web (GETs)
        $r->get('/staff', 'Manager\StaffController@index', [$mw->ownsCafe()]);
        $r->get('/staff/{id}', 'Manager\StaffController@show', [$mw->ownsCafe()]);

        $r->get('/reports', 'Manager\ReportController@index');
        $r->get('/reports/export', 'Manager\ReportController@exportReportes');

        // Café Management — vista web (GET); mutaciones vía /api/v1/manager/cafe/*
        $r->get('/cafe', 'Manager\CafeController@show', [$mw->ownsCafe()]);

        // Gestión de productos del café — vista web (GET); mutaciones vía /api/v1/manager/products/*
        $r->get('/products', 'Manager\ProductController@index', [$mw->ownsCafe()]);
    });

    // ============================================================================
    // BACKOFFICE - SUPERVISOR
    // ============================================================================

    /** @var array<int, MiddlewareInterface> $supervisorMiddleware */
    $supervisorMiddleware = [$mw->auth(), $mw->role(['admin', 'manager', 'supervisor'])];
    $router->group(['prefix' => '/supervisor', 'middleware' => $supervisorMiddleware], function (Router $r) use ($mw) {
        $r->get('/dashboard', 'Supervisor\SupervisorController@index');
        $r->get('/assignments', 'Supervisor\SupervisorController@assignments');
        $r->post('/assignments', 'Supervisor\SupervisorController@createAssignment', [$mw->csrf()]);
    });

    // API Manager - Dashboard
    $router->get('/api/v1/manager/stats', 'Api\V1\ManagerController@stats', [$mw->cors(), $mw->apiAuth(), $mw->apiRole(['admin', 'manager'])]);
    $router->get('/api/v1/manager/revenue/weekly', 'Api\V1\ManagerController@weeklyRevenue', [$mw->cors(), $mw->apiAuth(), $mw->apiRole(['admin', 'manager'])]);
    $router->get('/api/v1/manager/animals/top', 'Api\V1\ManagerController@topAnimals', [$mw->cors(), $mw->apiAuth(), $mw->apiRole(['admin', 'manager'])]);
    $router->get('/api/v1/manager/reservations/status', 'Api\V1\ManagerController@reservationStatus', [$mw->cors(), $mw->apiAuth(), $mw->apiRole(['admin', 'manager'])]);

    // API Manager — mutaciones AJAX (FASE 4C)
    /** @var array<int, MiddlewareInterface> $managerApiMiddleware */
    $managerApiMiddleware = [$mw->cors(), $mw->apiAuth(), $mw->apiRole(['admin', 'manager'])];
    $router->group(['prefix' => '/api/v1/manager', 'middleware' => $managerApiMiddleware], function (Router $r) use ($mw): void {
        // Café
        $r->put('/cafe/capacity', 'Api\V1\Manager\CafeApiController@updateCapacity', [$mw->csrf(), $mw->ownsCafe()]);
        $r->put('/cafe/schedule', 'Api\V1\Manager\CafeApiController@updateSchedule', [$mw->csrf(), $mw->ownsCafe()]);
        $r->put('/cafe/settings', 'Api\V1\Manager\CafeApiController@updateSettings', [$mw->csrf(), $mw->ownsCafe()]);
        // Products
        $r->post('/products', 'Api\V1\Manager\ProductApiController@create', [$mw->csrf(), $mw->ownsCafe()]);
        $r->put('/products/{id}', 'Api\V1\Manager\ProductApiController@update', [$mw->csrf(), $mw->ownsCafe()]);
        $r->patch('/products/{id}/toggle', 'Api\V1\Manager\ProductApiController@toggleAvailability', [$mw->csrf(), $mw->ownsCafe()]);
        $r->delete('/products/{id}', 'Api\V1\Manager\ProductApiController@delete', [$mw->csrf(), $mw->ownsCafe()]);
        // Staff mutations
        $r->post('/staff/assign-shift', 'Api\V1\Manager\StaffApiController@assignShift', [$mw->csrf(), $mw->ownsCafe()]);
        $r->post('/staff/edit-permissions', 'Api\V1\Manager\StaffApiController@editPermissions', [$mw->csrf(), $mw->ownsCafe()]);
        $r->get('/staff/performance/{id}', 'Api\V1\Manager\StaffApiController@viewPerformance', [$mw->ownsCafe()]);
        // Reviews (delegado a Admin ReviewApiController ya existente)
        $r->post('/reviews/{id}/approve', 'Api\V1\Admin\ReviewApiController@approve', [$mw->csrf()]);
        $r->post('/reviews/{id}/reject', 'Api\V1\Admin\ReviewApiController@reject', [$mw->csrf()]);
    });

    // API Supervisor
    $router->post('/api/v1/supervisor/assignments', 'Api\V1\SupervisorController@assign', [$mw->cors(), $mw->apiAuth(), $mw->csrf()]);
    $router->get('/api/v1/supervisor/assignments', 'Api\V1\SupervisorController@list', [$mw->cors(), $mw->apiAuth()]);

    // API Tokens — gestión de Bearer tokens (requiere sesión activa para generarlos)
    $router->group(
        ['prefix' => '/api/v1/tokens', 'middleware' => [$mw->cors(), $mw->apiAuth()]],
        function (Router $r) use ($mw): void {
            $r->get('', 'Api\V1\TokenController@list');
            $r->post('', 'Api\V1\TokenController@create', [$mw->csrf()]);
            $r->delete('/{id}', 'Api\V1\TokenController@revoke', [$mw->csrf()]);
        }
    );

    // ============================================================================
    // API ADMIN — mutaciones AJAX (FASE 4A)
    // ============================================================================

    /** @var array<int, MiddlewareInterface> $adminApiMiddleware */
    $adminApiMiddleware = [$mw->cors(), $mw->apiAuth(), $mw->apiRole(['admin'])];
    $router->group(['prefix' => '/api/v1/admin', 'middleware' => $adminApiMiddleware], function (Router $r) use ($mw): void {
        // Users
        $r->post('/users', 'Api\V1\Admin\UserApiController@create', [$mw->csrf()]);
        $r->put('/users/{id}', 'Api\V1\Admin\UserApiController@update', [$mw->csrf()]);
        $r->delete('/users/{id}', 'Api\V1\Admin\UserApiController@delete', [$mw->csrf()]);
        $r->patch('/users/{id}/status', 'Api\V1\Admin\UserApiController@toggleActive', [$mw->csrf()]);

        // Cafes
        $r->post('/cafes', 'Api\V1\Admin\CafeApiController@create', [$mw->csrf()]);
        $r->put('/cafes/{id}', 'Api\V1\Admin\CafeApiController@update', [$mw->csrf()]);
        $r->delete('/cafes/{id}', 'Api\V1\Admin\CafeApiController@delete', [$mw->csrf()]);
        $r->patch('/cafes/{id}/status', 'Api\V1\Admin\CafeApiController@toggleStatus', [$mw->csrf()]);

        // Menu / Products
        $r->post('/menu', 'Api\V1\Admin\MenuApiController@create', [$mw->csrf()]);
        $r->put('/menu/{id}', 'Api\V1\Admin\MenuApiController@update', [$mw->csrf()]);
        $r->delete('/menu/{id}', 'Api\V1\Admin\MenuApiController@delete', [$mw->csrf()]);
        $r->patch('/menu/{id}/toggle', 'Api\V1\Admin\MenuApiController@toggleAvailability', [$mw->csrf()]);

        // Reviews
        $r->post('/reviews/{id}/approve', 'Api\V1\Admin\ReviewApiController@approve', [$mw->csrf()]);
        $r->post('/reviews/{id}/reject', 'Api\V1\Admin\ReviewApiController@reject', [$mw->csrf()]);
        $r->delete('/reviews/{id}', 'Api\V1\Admin\ReviewApiController@delete', [$mw->csrf()]);

        // Reservations
        $r->post('/reservations/{id}/confirm', 'Api\V1\Admin\ReservationApiController@confirm', [$mw->csrf()]);
        $r->post('/reservations/{id}/cancel', 'Api\V1\Admin\ReservationApiController@cancel', [$mw->csrf()]);

        // Roles
        $r->post('/roles', 'Api\V1\Admin\RoleApiController@createRole', [$mw->csrf()]);
        $r->put('/roles/{id}', 'Api\V1\Admin\RoleApiController@updateRole', [$mw->csrf()]);
        $r->delete('/roles/{id}', 'Api\V1\Admin\RoleApiController@deleteRole', [$mw->csrf()]);
        $r->post('/roles/{roleId}/permissions/{permissionId}/grant', 'Api\V1\Admin\RoleApiController@grantPermission', [$mw->csrf()]);
        $r->post('/roles/{roleId}/permissions/{permissionId}/revoke', 'Api\V1\Admin\RoleApiController@revokePermission', [$mw->csrf()]);

        // Settings
        $r->get('/settings', 'Api\V1\Admin\SystemApiController@getSettingsData');
        $r->put('/settings/{group}', 'Api\V1\Admin\SystemApiController@updateSettingsGroup', [$mw->csrf()]);
        $r->post('/settings/test-email', 'Api\V1\Admin\SystemApiController@testEmail', [$mw->csrf()]);

        // Logs — Audit (GET, sin CSRF)
        $r->get('/logs/audit', 'Api\V1\Admin\LogApiController@auditLogs');
        $r->get('/logs/audit/export', 'Api\V1\Admin\LogApiController@auditExport');
        $r->get('/logs/audit/stats', 'Api\V1\Admin\LogApiController@auditStats');

        // Logs — Auth (GET, sin CSRF)
        $r->get('/logs/auth', 'Api\V1\Admin\LogApiController@authLogs');
        $r->get('/logs/auth/suspicious', 'Api\V1\Admin\LogApiController@authSuspicious');
        $r->get('/logs/auth/suspicious-count', 'Api\V1\Admin\LogApiController@authSuspiciousCount');
        $r->get('/logs/auth/export', 'Api\V1\Admin\LogApiController@authExport');

        // Cache & Security
        $r->post('/cache/clear', 'Api\V1\Admin\SystemApiController@clearCache', [$mw->csrf()]);
        $r->post('/security/block-ip', 'Api\V1\Admin\LogApiController@blockIp', [$mw->csrf()]);
    });
} // end FEATURE_BACKOFFICE
