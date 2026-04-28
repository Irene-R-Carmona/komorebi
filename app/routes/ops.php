<?php

declare(strict_types=1);

use App\Core\Env;
use App\Core\Router;
use Psr\Http\Server\MiddlewareInterface;

/** @var \App\Core\Router $router */
/** @var \App\Core\MiddlewareFactory $mw */

// ============================================================================
// BACKOFFICE - OPS (FEATURE_OPS) — reception / kitchen
// ============================================================================

if (Env::bool('FEATURE_OPS', true)) {

    // ============================================================================
    // BACKOFFICE - RECEPTION
    // ============================================================================

    /** @var array<int, MiddlewareInterface> $receptionMiddleware */
    $receptionMiddleware = [$mw->auth(), $mw->role(['admin', 'manager', 'supervisor', 'reception'])];
    $router->group(['prefix' => '/ops/reception', 'middleware' => $receptionMiddleware], function (Router $r): void {
        $r->get('', 'Reception\ReceptionController@index');
        $r->get('/reservations', 'Reception\ReceptionController@todayReservations');
    });

    // ============================================================================
    // BACKOFFICE - KITCHEN
    // ============================================================================

    /** @var array<int, MiddlewareInterface> $kitchenMiddleware */
    $kitchenMiddleware = [$mw->auth(), $mw->role(['admin', 'manager', 'kitchen', 'supervisor'])];
    $router->group(['prefix' => '/ops/kitchen', 'middleware' => $kitchenMiddleware], function (Router $r): void {
        $r->get('', 'Kitchen\KitchenController@index');
        $r->get('/history', 'Kitchen\KitchenController@history');
        $r->get('/orders', 'Kitchen\KitchenController@activeOrders');
    });

    // API Ops — Reception + Kitchen (FASE 4D)
    /** @var array<int, MiddlewareInterface> $opsReceptionApiMiddleware */
    $opsReceptionApiMiddleware = [$mw->cors(), $mw->apiAuth(), $mw->apiRole(['admin', 'manager', 'supervisor', 'reception'])];
    $router->group(['prefix' => '/api/v1/ops/reception', 'middleware' => $opsReceptionApiMiddleware], function (Router $r) use ($mw): void {
        $r->get('/reservations',                'Api\V1\Ops\ReceptionApiController@todayReservations');
        $r->post('/reservations/{id}/checkin',  'Api\V1\Ops\ReceptionApiController@checkIn',  [$mw->csrf()]);
        $r->post('/reservations/{id}/checkout', 'Api\V1\Ops\ReceptionApiController@checkOut', [$mw->csrf()]);
    });

    /** @var array<int, MiddlewareInterface> $opsKitchenApiMiddleware */
    $opsKitchenApiMiddleware = [$mw->cors(), $mw->apiAuth(), $mw->apiRole(['admin', 'manager', 'kitchen', 'supervisor'])];
    $router->group(['prefix' => '/api/v1/ops/kitchen', 'middleware' => $opsKitchenApiMiddleware], function (Router $r) use ($mw): void {
        $r->get('/orders',                      'Api\V1\Ops\KitchenApiController@activeOrders');
        $r->post('/orders/{id}/complete',       'Api\V1\Ops\KitchenApiController@completeOrder', [$mw->csrf()]);
    });
} // end FEATURE_OPS

// ============================================================================
// BACKOFFICE - KEEPER (FEATURE_KEEPER)
// ============================================================================

if (Env::bool('FEATURE_KEEPER', true)) {

    /** @var array<int, MiddlewareInterface> $keeperMiddleware */
    $keeperMiddleware = [$mw->auth(), $mw->role(['admin', 'keeper']), $mw->ownsCafe()];

    // Dashboard del keeper: accesible a manager y supervisor sin restricción de café
    $router->get('/keeper/dashboard', 'Keeper\AnimalDashboardController@dashboard', [
        $mw->auth(),
        $mw->role(['admin', 'keeper', 'manager', 'supervisor']),
    ]);

    $router->group(['prefix' => '/keeper', 'middleware' => $keeperMiddleware], function (Router $r) use ($mw) {
        $r->get('/animals', 'Keeper\AnimalDashboardController@index');
        $r->get('/animals/{id}', 'Keeper\AnimalDashboardController@show');

        // Health Checks - Sistema de chequeos diarios de salud (flujo web)
        $r->get('/health-checks', 'Keeper\HealthCheckController@index');
        $r->get('/health-checks/create/{animalId}', 'Keeper\HealthCheckController@create');
        $r->post('/health-checks', 'Keeper\HealthCheckController@store', [$mw->csrf()]);
        $r->get('/health-checks/{checkId}', 'Keeper\HealthCheckController@show');
        $r->get('/health-checks/history/{animalId}', 'Keeper\HealthCheckController@history');

        // Incidentes de animales - flujo web (GETs)
        $r->get('/incidents', 'Keeper\AnimalIncidentController@index');
        $r->get('/incidents/create', 'Keeper\AnimalIncidentController@create');
        $r->get('/incidents/{id}', 'Keeper\AnimalIncidentController@show');
    });

    // API Keeper — mutaciones AJAX (FASE 4B)
    /** @var array<int, MiddlewareInterface> $keeperApiMiddleware */
    $keeperApiMiddleware = [$mw->cors(), $mw->apiAuth(), $mw->apiRole(['admin', 'keeper'])];
    $router->group(['prefix' => '/api/v1/keeper', 'middleware' => $keeperApiMiddleware], function (Router $r) use ($mw): void {
        $r->post('/animals/{id}/photo',        'Api\V1\KeeperApiController@uploadPhoto');
        $r->post('/animals/{id}/care-log',     'Api\V1\KeeperApiController@createCareLog',   [$mw->csrf()]);
        $r->patch('/animals/{id}/health',      'Api\V1\KeeperApiController@updateHealth',    [$mw->csrf()]);
        $r->patch('/animals/{id}/toggle',      'Api\V1\KeeperApiController@toggleActive',    [$mw->csrf()]);
        $r->post('/incidents',                 'Api\V1\KeeperApiController@createIncident',  [$mw->csrf()]);
        $r->patch('/incidents/{id}/resolve',   'Api\V1\KeeperApiController@resolveIncident', [$mw->csrf()]);
    });
} // end FEATURE_KEEPER
