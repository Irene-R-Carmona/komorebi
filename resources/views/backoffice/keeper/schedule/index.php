<?php

declare(strict_types=1);

/**
 * Mis Turnos — Keeper
 *
 * Muestra los próximos turnos asignados al keeper autenticado.
 */
?>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-1">
                        <i class="bi bi-calendar-week text-primary"></i>
                        Mis Turnos
                    </h1>
                    <p class="text-muted mb-0">Próximos turnos asignados</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabla de turnos -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 fw-bold text-primary">
                        <i class="bi bi-list-ul"></i> Turnos programados
                    </h6>
                </div>
                <div class="card-body">
                    <?php if (empty($shifts)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-calendar-x fs-1 d-block mb-3"></i>
                            <p class="mb-0">No tienes turnos programados próximamente.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th scope="col"><i class="bi bi-calendar3 me-1"></i>Fecha</th>
                                        <th scope="col"><i class="bi bi-clock me-1"></i>Inicio</th>
                                        <th scope="col"><i class="bi bi-clock-history me-1"></i>Fin</th>
                                        <th scope="col"><i class="bi bi-shop me-1"></i>Sede</th>
                                        <th scope="col"><i class="bi bi-chat-text me-1"></i>Notas</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($shifts as $shift): ?>
                                        <tr>
                                            <td class="fw-semibold">
                                                <?= htmlspecialchars(
                                                    new DateTimeImmutable($shift['shift_date'])->format('d/m/Y'),
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>
                                            </td>
                                            <td><?= htmlspecialchars(substr((string) $shift['shift_start'], 0, 5), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars(substr((string) $shift['shift_end'], 0, 5), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars((string) ($shift['cafe_name'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="text-muted small">
                                                <?= htmlspecialchars((string) ($shift['notes'] ?? '—'), ENT_QUOTES, 'UTF-8') ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
