<?php

/** @var array $waitlists Lista de waitlists activas */
/** @var array $summary Resumen por estado */
/** @var array $filters Filtros activos */
?>

<!-- Page Header -->
<div class="page-header mb-4">
    <div class="page-header__content">
        <h1 class="page-header__title">&#128203; Gesti&oacute;n de Listas de Espera</h1>
        <p class="page-header__subtitle">Monitorea y gestiona todas las reservas en lista de espera</p>
    </div>
</div>

<!-- Resumen de estados -->
<div class="stats-grid mb-4">
    <div class="stat-card">
        <div class="stat-card__inner">
            <div class="stat-card__icon stat-card__icon--warning">&#9203;</div>
            <div class="stat-card__content">
                <div class="stat-card__label">En Espera</div>
                <div class="stat-card__value"><?= (int) ($summary['waiting'] ?? 0) ?></div>
            </div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card__inner">
            <div class="stat-card__icon stat-card__icon--info">&#128276;</div>
            <div class="stat-card__content">
                <div class="stat-card__label">Notificados</div>
                <div class="stat-card__value"><?= (int) ($summary['notified'] ?? 0) ?></div>
            </div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card__inner">
            <div class="stat-card__icon stat-card__icon--success">&#9989;</div>
            <div class="stat-card__content">
                <div class="stat-card__label">Confirmados</div>
                <div class="stat-card__value"><?= (int) ($summary['confirmed'] ?? 0) ?></div>
            </div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card__inner">
            <div class="stat-card__icon stat-card__icon--error">&#10006;</div>
            <div class="stat-card__content">
                <div class="stat-card__label">Cancelados</div>
                <div class="stat-card__value"><?= (int) ($summary['cancelled'] ?? 0) ?></div>
            </div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card__inner">
            <div class="stat-card__icon stat-card__icon--primary">&#128194;</div>
            <div class="stat-card__content">
                <div class="stat-card__label">Expirados</div>
                <div class="stat-card__value"><?= (int) ($summary['expired'] ?? 0) ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Filtros -->
<div class="filter-bar mb-4">
    <form method="GET" action="/admin/waitlists" class="filter-bar__row">
        <div class="filter-bar__filters">
            <div>
                <label for="status" class="form-label mb-1">Estado</label>
                <select name="status" id="status" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <option value="waiting" <?= ($filters['status'] ?? '') === 'waiting' ? 'selected' : '' ?>>En Espera</option>
                    <option value="notified" <?= ($filters['status'] ?? '') === 'notified' ? 'selected' : '' ?>>Notificados</option>
                    <option value="confirmed" <?= ($filters['status'] ?? '') === 'confirmed' ? 'selected' : '' ?>>Confirmados</option>
                    <option value="cancelled" <?= ($filters['status'] ?? '') === 'cancelled' ? 'selected' : '' ?>>Cancelados</option>
                    <option value="expired" <?= ($filters['status'] ?? '') === 'expired' ? 'selected' : '' ?>>Expirados</option>
                </select>
            </div>
            <div>
                <label for="date" class="form-label mb-1">Fecha</label>
                <input type="date" name="date" id="date"
                    value="<?= htmlspecialchars($filters['date'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                    class="form-control form-control-sm">
            </div>
        </div>
        <div class="filter-bar__actions d-flex gap-2 align-items-end">
            <button type="submit" class="btn btn-komorebi-primary">Filtrar</button>
            <a href="/admin/waitlists" class="btn btn-komorebi-secondary">Limpiar</a>
        </div>
    </form>
</div>

<!-- Tabla de waitlists -->
<div class="card-admin">
    <?php if (empty($waitlists)): ?>
        <div class="empty-state">
            <div class="fs-1 mb-3">&#128203;</div>
            <p class="mb-0">No hay listas de espera con los filtros seleccionados</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Usuario</th>
                        <th>Caf&eacute;</th>
                        <th>Fecha / Hora</th>
                        <th>Posici&oacute;n</th>
                        <th>Personas</th>
                        <th>Estado</th>
                        <th>Creado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $statusLabels = [
                        'waiting'   => 'En Espera',
                        'notified'  => 'Notificado',
                        'confirmed' => 'Confirmado',
                        'cancelled' => 'Cancelado',
                        'expired'   => 'Expirado',
                    ];
                    foreach ($waitlists as $w):
                        $statusKey = isset($statusLabels[$w['status']]) ? $w['status'] : 'expired';
                        $statusLabel = $statusLabels[$statusKey];
                    ?>
                        <tr>
                            <td class="text-muted small"><?= htmlspecialchars((string) $w['id'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <div class="fw-semibold"><?= htmlspecialchars($w['user_name'], ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="small text-muted"><?= htmlspecialchars($w['user_email'], ENT_QUOTES, 'UTF-8') ?></div>
                            </td>
                            <td><?= htmlspecialchars($w['cafe_name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <div><?= date('d/m/Y', strtotime($w['slot_date'])) ?></div>
                                <div class="small text-muted"><?= date('H:i', strtotime($w['slot_time'])) ?></div>
                            </td>
                            <td>
                                <span class="badge-position">
                                    <?= htmlspecialchars((string) $w['position'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars((string) $w['guest_count'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <span class="badge-status badge-status--<?= $statusKey ?>">
                                    <?= $statusLabel ?>
                                </span>
                            </td>
                            <td class="small text-muted"><?= date('d/m H:i', strtotime($w['created_at'])) ?></td>
                            <td>
                                <?php if ($w['status'] === 'waiting' || $w['status'] === 'notified'): ?>
                                    <form method="POST" action="/admin/waitlists/<?= (int) $w['id'] ?>/cancel"
                                        data-action="confirm" data-confirm="&iquest;Cancelar esta waitlist?"
                                        class="d-inline">
                                        <?= Csrf::field() ?>
                                        <button type="submit" class="btn btn-sm btn-komorebi-danger">
                                            Cancelar
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-muted">&mdash;</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Total -->
<p class="small text-muted mt-3">
    Total: <strong><?= count($waitlists) ?></strong> resultado(s)
</p>
