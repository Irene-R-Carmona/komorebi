<?php

declare(strict_types=1);

/** @var array<int, array<string, mixed>> $processed */
$processed ??= [];
?>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-1">
                        <i class="bi bi-clock-history text-primary"></i>
                        Historial de Adopciones
                    </h1>
                    <p class="text-muted mb-0">Solicitudes aprobadas y rechazadas</p>
                </div>
                <a href="/keeper/adopciones" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Volver a pendientes
                </a>
            </div>
        </div>
    </div>

    <?php if (empty($processed)): ?>
        <div class="card shadow-sm">
            <div class="card-body text-center py-5">
                <i class="bi bi-inbox display-4 text-muted"></i>
                <p class="text-muted mt-3 mb-0">No hay solicitudes procesadas todavía.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="card shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 text-primary">
                    <i class="bi bi-table"></i> Solicitudes procesadas (<?= count($processed) ?>)
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Animal</th>
                                <th>Solicitante</th>
                                <th>Decisión</th>
                                <th>Revisor</th>
                                <th>Notas del keeper</th>
                                <th>Fecha</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($processed as $row): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <?php if (!empty($row['image_url'])): ?>
                                                <img src="<?= htmlspecialchars((string) $row['image_url'], ENT_QUOTES, 'UTF-8') ?>"
                                                    alt="<?= htmlspecialchars((string) $row['animal_name'], ENT_QUOTES, 'UTF-8') ?>"
                                                    class="rounded-circle object-fit-cover"
                                                    style="width:36px;height:36px;">
                                            <?php else: ?>
                                                <span class="rounded-circle bg-light d-flex align-items-center justify-content-center"
                                                    style="width:36px;height:36px;">
                                                    <i class="bi bi-heart-fill text-muted small"></i>
                                                </span>
                                            <?php endif; ?>
                                            <div>
                                                <div class="fw-semibold"><?= htmlspecialchars((string) $row['animal_name'], ENT_QUOTES, 'UTF-8') ?></div>
                                                <small class="text-muted"><?= htmlspecialchars(ucfirst((string) ($row['species_type'] ?? '')), ENT_QUOTES, 'UTF-8') ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars((string) $row['applicant_name'], ENT_QUOTES, 'UTF-8') ?></div>
                                        <small class="text-muted"><?= htmlspecialchars((string) ($row['applicant_email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></small>
                                    </td>
                                    <td>
                                        <?php if ($row['status'] === 'approved'): ?>
                                            <span class="badge bg-success">Aprobada</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Rechazada</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars((string) ($row['reviewer_name'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td>
                                        <?php if (!empty($row['keeper_notes'])): ?>
                                            <span class="text-muted" title="<?= htmlspecialchars((string) $row['keeper_notes'], ENT_QUOTES, 'UTF-8') ?>">
                                                <?= htmlspecialchars(\mb_strimwidth((string) $row['keeper_notes'], 0, 60, '…'), ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-nowrap">
                                        <?php
                                        $fecha = $row['updated_at'] ?? $row['reviewed_at'] ?? null;
                                        echo $fecha
                                            ? htmlspecialchars((new DateTimeImmutable((string) $fecha))->format('d/m/Y H:i'), ENT_QUOTES, 'UTF-8')
                                            : '—';
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
