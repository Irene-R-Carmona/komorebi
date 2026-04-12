<?php

declare(strict_types=1);

/**
 * Vista de Detalle de Chequeo de Salud
 *
 * Muestra toda la información de un chequeo histórico específico.
 */
?>

<div class="container py-4">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-1">
                        <i class="bi bi-file-medical text-primary"></i>
                        Detalle de Chequeo
                    </h1>
                    <p class="text-muted mb-0">
                        Fecha: <?= date('d/m/Y H:i', strtotime($check['created_at'])) ?>
                    </p>
                </div>
                <div>
                    <a href="/keeper/health-checks" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Volver al Dashboard
                    </a>
                    <a href="/keeper/health-checks/history/<?= $check['animal_id'] ?>" class="btn btn-outline-info">
                        <i class="bi bi-clock-history"></i> Ver Historial Completo
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Información del Animal y Keeper -->
    <div class="row mb-4">
        <div class="col-md-6 mb-3">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-paw"></i> Animal</h5>
                </div>
                <div class="card-body">
                    <h4><?= htmlspecialchars($check['animal_name'], ENT_QUOTES, 'UTF-8') ?></h4>
                    <p class="mb-1">
                        <strong>Especie:</strong>
                        <span class="badge bg-info"><?= htmlspecialchars($check['species_type'], ENT_QUOTES, 'UTF-8') ?></span>
                    </p>
                    <p class="mb-0">
                        <strong>Estado:</strong>
                        <span class="badge bg-secondary"><?= htmlspecialchars($check['current_status'], ENT_QUOTES, 'UTF-8') ?></span>
                    </p>
                </div>
            </div>
        </div>
        <div class="col-md-6 mb-3">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="bi bi-person-badge"></i> Keeper</h5>
                </div>
                <div class="card-body">
                    <h5><?= htmlspecialchars($check['keeper_name'], ENT_QUOTES, 'UTF-8') ?></h5>
                    <p class="mb-1">
                        <strong>Fecha del chequeo:</strong> <?= date('d/m/Y', strtotime($check['check_date'])) ?>
                    </p>
                    <p class="mb-0">
                        <strong>Hora de registro:</strong> <?= date('H:i:s', strtotime($check['created_at'])) ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Alertas (si existen) -->
    <?php if (!empty($check['alerts']) && is_array($check['alerts'])): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow border-danger">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            Alertas Detectadas (<?= count($check['alerts']) ?>)
                        </h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush">
                            <?php foreach ($check['alerts'] as $alert): ?>
                                <li class="list-group-item d-flex align-items-center">
                                    <i class="bi bi-shield-fill-exclamation text-danger me-3 fs-4"></i>
                                    <span><?= htmlspecialchars($alert, ENT_QUOTES, 'UTF-8') ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Métricas y Observaciones -->
    <div class="row">
        <!-- Métricas Físicas -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="bi bi-thermometer-half"></i> Métricas Físicas</h5>
                </div>
                <div class="card-body">
                    <table class="table table-borderless mb-0">
                        <tbody>
                            <tr>
                                <th class="w-50">Peso:</th>
                                <td>
                                    <?php if ($check['weight_kg']): ?>
                                        <strong><?= number_format((float)$check['weight_kg'], 2) ?> kg</strong>
                                    <?php else: ?>
                                        <span class="text-muted">No medido</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Temperatura:</th>
                                <td>
                                    <?php if ($check['temperature_c']): ?>
                                        <?php
                                        $temp = (float)$check['temperature_c'];
                                        $tempClass = $temp > 39.5 ? 'text-danger' : ($temp < 36 ? 'text-warning' : 'text-success');
                                        ?>
                                        <strong class="<?= $tempClass ?>">
                                            <?= number_format($temp, 1) ?>°C
                                        </strong>
                                    <?php else: ?>
                                        <span class="text-muted">No medida</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Condición del Pelaje:</th>
                                <td>
                                    <?php
                                    $coatColors = [
                                        'excellent' => 'success',
                                        'good' => 'primary',
                                        'fair' => 'warning',
                                        'poor' => 'danger',
                                    ];
                                    $coatColor = $coatColors[$check['coat_condition']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?= $coatColor ?>">
                                        <?= ucfirst(htmlspecialchars($check['coat_condition'], ENT_QUOTES, 'UTF-8')) ?>
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Estado General -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-heart-pulse"></i> Estado General</h5>
                </div>
                <div class="card-body">
                    <table class="table table-borderless mb-0">
                        <tbody>
                            <tr>
                                <th class="w-50">Apetito:</th>
                                <td>
                                    <?php
                                    $appetiteColors = ['normal' => 'success', 'reduced' => 'warning', 'none' => 'danger'];
                                    $appetiteColor = $appetiteColors[$check['appetite']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?= $appetiteColor ?>">
                                        <?= ucfirst(htmlspecialchars($check['appetite'], ENT_QUOTES, 'UTF-8')) ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th>Nivel de Energía:</th>
                                <td>
                                    <?php
                                    $energyColors = ['high' => 'primary', 'normal' => 'success', 'low' => 'warning'];
                                    $energyColor = $energyColors[$check['energy_level']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?= $energyColor ?>">
                                        <?= ucfirst(htmlspecialchars($check['energy_level'], ENT_QUOTES, 'UTF-8')) ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th>Ojos Claros:</th>
                                <td>
                                    <?php if ($check['eyes_clear']): ?>
                                        <i class="bi bi-check-circle-fill text-success"></i> Sí
                                    <?php else: ?>
                                        <i class="bi bi-x-circle-fill text-danger"></i> No (secreción)
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Respiración Normal:</th>
                                <td>
                                    <?php if ($check['breathing_normal']): ?>
                                        <i class="bi bi-check-circle-fill text-success"></i> Sí
                                    <?php else: ?>
                                        <i class="bi bi-x-circle-fill text-danger"></i> No (dificultad)
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Movilidad Normal:</th>
                                <td>
                                    <?php if ($check['mobility_normal']): ?>
                                        <i class="bi bi-check-circle-fill text-success"></i> Sí
                                    <?php else: ?>
                                        <i class="bi bi-x-circle-fill text-danger"></i> No (cojera/limitación)
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Notas del Keeper -->
    <?php if (!empty(trim($check['notes'] ?? ''))): ?>
        <div class="row">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0"><i class="bi bi-journal-text"></i> Notas del Keeper</h5>
                    </div>
                    <div class="card-body">
                        <p class="mb-0" style="white-space: pre-wrap;"><?= htmlspecialchars($check['notes'], ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
