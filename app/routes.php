<?php

declare(strict_types=1);

/**
 * Definicion de rutas PSR-7/PSR-15 - Version 12-Factor
 *
 * Dispatcher - cada modulo vive en app/routes/:
 *   public.php  - rutas publicas + health + CORS + error handlers
 *   auth.php    - autenticacion (guest + user SSR + API autenticada)
 *   admin.php   - backoffice admin / manager / supervisor (FEATURE_BACKOFFICE)
 *   ops.php     - reception / kitchen / keeper (FEATURE_OPS, FEATURE_KEEPER)
 */

use App\Core\Http\ResponseFactory;
use App\Core\MiddlewareFactory;
use App\Core\Router;

$responseFactory = new ResponseFactory();
$mw = new MiddlewareFactory($responseFactory);
$router = new Router($responseFactory);
$router->setControllerNamespace('App\Http\Controllers');

require __DIR__ . '/routes/public.php';
require __DIR__ . '/routes/auth.php';
require __DIR__ . '/routes/admin.php';
require __DIR__ . '/routes/ops.php';

return $router;
