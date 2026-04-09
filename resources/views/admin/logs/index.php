<?php

/**
 * Vista: Logs del Sistema
 * Ruta: GET /admin/logs
 */

use App\Core\View;

$titulo = 'Logs del Sistema';
?>

<div class="container-fluid">
    <!-- Header -->
    <?= View::componentToString('components/admin/page-header', [
        'icon' => 'clock-history',
        'title' => 'Logs del Sistema',
        'subtitle' => 'Monitoreo de auditoría y autenticación',
    ]) ?>

    <div class="row g-4 mt-3">
        <!-- Logs de Auditoría -->
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title">
                        <i class="bi bi-journal-text"></i> Logs de Auditoría
                    </h5>
                    <p class="card-text text-muted">
                        Registros de acciones administrativas y cambios en el sistema.
                    </p>
                    <a href="/admin/logs/audit" class="btn btn-primary">
                        <i class="bi bi-eye"></i> Ver Logs de Auditoría
                    </a>
                </div>
            </div>
        </div>

        <!-- Logs de Autenticación -->
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title">
                        <i class="bi bi-shield-lock"></i> Logs de Autenticación
                    </h5>
                    <p class="card-text text-muted">
                        Historial de inicios de sesión, intentos fallidos y sesiones activas.
                    </p>
                    <a href="/admin/logs/auth" class="btn btn-primary">
                        <i class="bi bi-eye"></i> Ver Logs de Autenticación
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
