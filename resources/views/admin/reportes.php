<?php

/**
 * Vista: Reportes y Estadísticas
 * Ruta: GET /admin/reportes
 */

use App\Core\View;
use App\Support\CurrencyFormatting;

$titulo ??= 'Reportes y Estadísticas';
?>

<?php $cspNonce = $GLOBALS['cspNonce'] ?? ''; ?>
<script<?= $cspNonce ? ' nonce="' . htmlspecialchars($cspNonce, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
    window.reportsConfig = {
    chartData: <?= $chartData ?? 'null' ?>,
    cafeData: <?= $cafeData ?? 'null' ?>
    };
    </script>

    <div class="container-fluid py-4">

        <!-- Header -->
        <?= View::componentToString('components/admin/page-header', [
            'icon' => 'graph-up',
            'title' => 'Reportes y Estadísticas',
            'subtitle' => 'Análisis y métricas del sistema',
        ]) ?>

        <div x-data="reportsManagement()">

            <!-- Loading -->
            <div x-show="loading" class="text-center py-5">
                <output class="spinner-border text-primary" aria-busy="true">
                    <span class="visually-hidden">Cargando reportes...</span>
                </output>
            </div>

            <!-- Content -->
            <div x-show="!loading" x-cloak>

                <!-- Stats Grid -->
                <div class="stats-grid stats-grid--4 mb-4">
                    <?= View::componentToString('components/admin/stat-card', [
                        'icon' => 'people',
                        'variant' => 'primary',
                        'label' => 'Total Usuarios',
                        'value' => $stats['total_users'] ?? 0,
                    ]) ?>

                    <?= View::componentToString('components/admin/stat-card', [
                        'icon' => 'calendar-check',
                        'variant' => 'success',
                        'label' => 'Reservas del Mes',
                        'value' => $stats['monthly_reservations'] ?? 0,
                    ]) ?>

                    <?= View::componentToString('components/admin/stat-card', [
                        'icon' => 'star',
                        'variant' => 'warning',
                        'label' => 'Reseñas Totales',
                        'value' => $stats['total_reviews'] ?? 0,
                    ]) ?>

                    <?= View::componentToString('components/admin/stat-card', [
                        'icon' => 'cash-stack',
                        'variant' => 'info',
                        'label' => 'Ingresos del Mes',
                        'value' => CurrencyFormatting::euro((int) ($stats['monthly_revenue'] ?? 0)),
                    ]) ?>
                </div>

                <!-- Charts Section -->
                <div class="row">
                    <div class="col-lg-8 mb-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Reservas por Mes</h5>
                                <canvas id="reservationsChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4 mb-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Cafés Más Populares</h5>
                                <canvas id="popularCafesChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Export Options -->
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Exportar Reportes</h5>
                        <div class="btn-group" role="group" aria-label="Formatos de exportación">
                            <button type="button" class="btn btn-outline-primary" @click="exportPDF()">
                                <i class="bi bi-file-pdf"></i> Exportar PDF
                            </button>
                            <button type="button" class="btn btn-outline-success" @click="exportExcel()">
                                <i class="bi bi-file-excel"></i> Exportar Excel
                            </button>
                            <button type="button" class="btn btn-outline-secondary" @click="exportCSV()">
                                <i class="bi bi-file-csv"></i> Exportar CSV
                            </button>
                        </div>
                    </div>
                </div>

            </div>

        </div>

    </div>
