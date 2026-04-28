<?php

declare(strict_types=1);

/**
 * Timeline / Historial de Chequeos de un Animal
 *
 * Muestra el historial completo de chequeos de salud de un animal específico.
 */
?>

<div class="container py-4">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h1 class="h3 mb-1">
                                <i class="bi bi-clock-history text-primary"></i>
                                Historial de Chequeos
                            </h1>
                            <h5 class="text-muted mb-0">
                                <?= htmlspecialchars($animal['name'], ENT_QUOTES, 'UTF-8') ?>
                                <span class="badge bg-info ms-2"><?= htmlspecialchars($animal['species'], ENT_QUOTES, 'UTF-8') ?></span>
                            </h5>
                            <p class="text-muted small mb-0 mt-1">
                                Estado Actual: <span class="badge bg-secondary"><?= htmlspecialchars($animal['is_active'] ? 'Activo' : 'Inactivo', ENT_QUOTES, 'UTF-8') ?></span> •
                                Mostrando últimos <?= $limit ?> chequeos
                            </p>
                        </div>
                        <div class="col-md-4 text-md-end">
                            <a href="/keeper/health-checks" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left"></i> Volver
                            </a>
                            <a href="/keeper/health-checks/create/<?= $animal['id'] ?>" class="btn btn-primary">
                                <i class="bi bi-plus-circle"></i> Nuevo Chequeo
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Estadísticas del Animal -->
    <div class="row mb-4">
        <div class="col-md-4 mb-3">
            <div class="card shadow-sm h-100">
                <div class="card-body text-center">
                    <i class="bi bi-clipboard-data fs-1 text-primary mb-2"></i>
                    <h4 class="mb-0"><?= count($history) ?></h4>
                    <p class="text-muted mb-0">Chequeos Registrados</p>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card shadow-sm h-100">
                <div class="card-body text-center">
                    <i class="bi bi-calendar-check fs-1 text-success mb-2"></i>
                    <h4 class="mb-0">
                        <?php if (!empty($history)): ?>
                            <?= date('d/m/Y', strtotime($history[0]['check_date'])) ?>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </h4>
                    <p class="text-muted mb-0">Último Chequeo</p>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card shadow-sm h-100">
                <div class="card-body text-center">
                    <i class="bi bi-exclamation-triangle fs-1 text-warning mb-2"></i>
                    <h4 class="mb-0">
                        <?php
                        $alertCount = 0;
                        foreach ($history as $check) {
                            if (!empty($check['alerts']) && is_array($check['alerts'])) {
                                $alertCount += count($check['alerts']);
                            }
                        }
                        echo $alertCount;
                        ?>
                    </h4>
                    <p class="text-muted mb-0">Alertas Totales</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Timeline de Chequeos -->
    <div class="row">
        <div class="col-12">
            <?php if (empty($history)): ?>
                <div class="alert alert-info shadow-sm" role="alert">
                    <i class="bi bi-info-circle-fill"></i>
                    <strong>Sin historial.</strong> No hay chequeos registrados para este animal aún.
                    <a href="/keeper/health-checks/create/<?= $animal['id'] ?>" class="alert-link">Realizar el primer chequeo</a>
                </div>
            <?php else: ?>
                <?php foreach ($history as $index => $check): ?>
                    <div class="card shadow-sm mb-3">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <div>
                                <strong>
                                    <i class="bi bi-calendar3"></i>
                                    <?= date('d/m/Y', strtotime($check['check_date'])) ?>
                                </strong>
                                <span class="text-muted ms-2">
                                    • Registrado <?= date('H:i', strtotime($check['created_at'])) ?> por
                                    <?= htmlspecialchars($check['keeper_name'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </div>
                            <div>
                                <?php if (!empty($check['alerts']) && is_array($check['alerts'])): ?>
                                    <span class="badge bg-danger">
                                        <i class="bi bi-exclamation-triangle-fill"></i>
                                        <?= count($check['alerts']) ?> alerta(s)
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-success">
                                        <i class="bi bi-check-circle"></i> OK
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <!-- Métricas Físicas -->
                                <div class="col-md-6 mb-3 mb-md-0">
                                    <h6 class="text-muted mb-2">
                                        <i class="bi bi-thermometer-half"></i> Métricas Físicas
                                    </h6>
                                    <ul class="list-unstyled mb-0">
                                        <li>
                                            <strong>Peso:</strong>
                                            <?= $check['weight_kg'] ? number_format((float) $check['weight_kg'], 2) . ' kg' : '<span class="text-muted">-</span>' ?>
                                        </li>
                                        <li>
                                            <strong>Temperatura:</strong>
                                            <?php if ($check['temperature_c']): ?>
                                                <?php
                                                $temp = (float) $check['temperature_c'];
                                                $tempClass = $temp > 39.5 ? 'text-danger' : ($temp < 36 ? 'text-warning' : '');
                                                ?>
                                                <span class="<?= $tempClass ?>">
                                                    <?= number_format($temp, 1) ?>°C
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </li>
                                        <li>
                                            <strong>Pelaje:</strong>
                                            <span class="badge bg-<?= $check['coat_condition'] === 'poor' ? 'danger' : ($check['coat_condition'] === 'excellent' ? 'success' : 'secondary') ?>">
                                                <?= ucfirst($check['coat_condition']) ?>
                                            </span>
                                        </li>
                                    </ul>
                                </div>

                                <!-- Estado General -->
                                <div class="col-md-6">
                                    <h6 class="text-muted mb-2">
                                        <i class="bi bi-heart-pulse"></i> Estado General
                                    </h6>
                                    <ul class="list-unstyled mb-0">
                                        <li>
                                            <strong>Apetito:</strong>
                                            <span class="badge bg-<?= $check['appetite'] === 'none' ? 'danger' : ($check['appetite'] === 'normal' ? 'success' : 'warning') ?>">
                                                <?= ucfirst($check['appetite']) ?>
                                            </span>
                                        </li>
                                        <li>
                                            <strong>Energía:</strong>
                                            <span class="badge bg-<?= $check['energy_level'] === 'low' ? 'warning' : ($check['energy_level'] === 'high' ? 'primary' : 'success') ?>">
                                                <?= ucfirst($check['energy_level']) ?>
                                            </span>
                                        </li>
                                        <li>
                                            <strong>Checks:</strong>
                                            <?php if ($check['eyes_clear'] && $check['breathing_normal'] && $check['mobility_normal']): ?>
                                                <i class="bi bi-check-circle-fill text-success"></i> Todos OK
                                            <?php else: ?>
                                                <?php if (!$check['eyes_clear']): ?>
                                                    <i class="bi bi-eye-slash text-danger"></i>
                                                <?php endif; ?>
                                                <?php if (!$check['breathing_normal']): ?>
                                                    <i class="bi bi-lungs text-danger"></i>
                                                <?php endif; ?>
                                                <?php if (!$check['mobility_normal']): ?>
                                                    <i class="bi bi-activity text-danger"></i>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </li>
                                    </ul>
                                </div>
                            </div>

                            <!-- Alertas (si existen) -->
                            <?php if (!empty($check['alerts']) && is_array($check['alerts'])): ?>
                                <div class="row mt-3">
                                    <div class="col-12">
                                        <div class="alert alert-danger mb-0">
                                            <h6 class="alert-heading mb-2">
                                                <i class="bi bi-exclamation-triangle-fill"></i> Alertas Detectadas:
                                            </h6>
                                            <ul class="mb-0 ps-3">
                                                <?php foreach ($check['alerts'] as $alert): ?>
                                                    <li><?= htmlspecialchars($alert, ENT_QUOTES, 'UTF-8') ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Notas (si existen) -->
                            <?php if (!empty(trim($check['notes'] ?? ''))): ?>
                                <div class="row mt-2">
                                    <div class="col-12">
                                        <small class="text-muted">
                                            <i class="bi bi-journal-text"></i>
                                            <strong>Notas:</strong>
                                            <?= htmlspecialchars($check['notes'], ENT_QUOTES, 'UTF-8') ?>
                                        </small>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Botón Ver Detalle -->
                            <div class="row mt-3">
                                <div class="col-12 text-end">
                                    <a href="/keeper/health-checks/<?= $check['id'] ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye"></i> Ver Detalle Completo
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Paginación (si hay más registros) -->
    <?php if (count($history) >= $limit): ?>
        <div class="row mt-4">
            <div class="col-12 text-center">
                <p class="text-muted">
                    Mostrando los últimos <?= $limit ?> chequeos.
                    <?php if ($limit < 100): ?>
                        <a href="?limit=<?= min($limit + 30, 100) ?>" class="btn btn-sm btn-outline-secondary ms-2">
                            <i class="bi bi-arrow-down-circle"></i> Ver más
                        </a>
                    <?php endif; ?>
                </p>
            </div>
        </div>
    <?php endif; ?>
</div>
