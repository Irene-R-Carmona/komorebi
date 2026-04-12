<?php

declare(strict_types=1);

/**
 * Vista: Gestión de Reservas del Manager
 * Ruta: GET /manager/reservations
 *
 * @var string $titulo
 * @var array  $reservations Lista de reservas (id, reservation_date, reservation_time, guest_count, status, user_id, ...)
 * @var array  $filters      ['status' => string|null, 'date' => string|null]
 * @var string $csrf_token   Token CSRF (disponible para acciones futuras)
 */

/** @var array<string, array{class: string, label: string}> $statusLabels */
$statusLabels = [
    'pending'   => ['class' => 'reservation-badge--pending',   'label' => 'Pendiente'],
    'confirmed' => ['class' => 'reservation-badge--confirmed', 'label' => 'Confirmada'],
    'active'    => ['class' => 'reservation-badge--active',    'label' => 'Activa'],
    'completed' => ['class' => 'reservation-badge--completed', 'label' => 'Completada'],
    'cancelled' => ['class' => 'reservation-badge--cancelled', 'label' => 'Cancelada'],
    'no_show'   => ['class' => 'reservation-badge--no-show',   'label' => 'No Show'],
];

$hasActiveFilters = ($filters['status'] !== null || $filters['date'] !== null);
$total            = count($reservations);
?>

<div class="container-fluid">
    <div class="dashboard-header">
        <div>
            <h1 class="dashboard-header__title"><?= e($titulo) ?></h1>
            <p class="dashboard-header__subtitle">Listado de reservas de tu café</p>
        </div>
    </div>

    <!-- Filtros -->
    <div class="glass-card mt-3 p-3">
        <form method="GET" action="/manager/reservations" role="search"
            aria-label="Filtrar reservas">
            <div class="reservations-filter">
                <div class="filter-group">
                    <label class="filter-label" for="filter-date">Fecha</label>
                    <input
                        id="filter-date"
                        class="filter-input"
                        type="date"
                        name="date"
                        value="<?= e($filters['date'] ?? '') ?>">
                </div>
                <div class="filter-group">
                    <label class="filter-label" for="filter-status">Estado</label>
                    <select id="filter-status" class="filter-select" name="status">
                        <option value="">Todos</option>
                        <?php foreach ($statusLabels as $value => $meta): ?>
                            <option value="<?= e($value) ?>"
                                <?= ($filters['status'] ?? '') === $value ? 'selected' : '' ?>>
                                <?= e($meta['label']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-actions">
                    <button type="submit" class="filter-btn filter-btn--primary"
                        aria-label="Aplicar filtros">
                        <i class="bi bi-search" aria-hidden="true"></i>
                        Filtrar
                    </button>
                    <?php if ($hasActiveFilters): ?>
                        <a href="/manager/reservations"
                            class="filter-btn filter-btn--secondary"
                            aria-label="Limpiar filtros">
                            <i class="bi bi-x-lg" aria-hidden="true"></i>
                            Limpiar
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>

    <!-- Tabla de reservas -->
    <div class="glass-card mt-2">
        <?php if (empty($reservations)): ?>
            <div class="reservations-empty">
                <div class="reservations-empty__icon" aria-hidden="true">
                    <i class="bi bi-calendar3"></i>
                </div>
                <p class="reservations-empty__title">Sin reservas</p>
                <p class="reservations-empty__message">
                    No hay reservas<?= $hasActiveFilters ? ' con los filtros seleccionados' : '' ?>.
                </p>
            </div>
        <?php else: ?>
            <div class="reservations-table-wrapper">
                <table class="reservations-table" aria-label="Listado de reservas">
                    <thead>
                        <tr>
                            <th scope="col">ID</th>
                            <th scope="col">Fecha</th>
                            <th scope="col">Hora</th>
                            <th scope="col" class="reservations-table th--center">Personas</th>
                            <th scope="col">Estado</th>
                            <th scope="col">Creación</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reservations as $r): ?>
                            <?php
                            $rstatus = $r['status'] ?? 'pending';
                            $badge   = $statusLabels[$rstatus]
                                ?? ['class' => 'reservation-badge--completed', 'label' => e($rstatus)];
                            ?>
                            <tr>
                                <td class="reservations-table__id">
                                    #<?= (int) $r['id'] ?>
                                </td>
                                <td class="reservations-table__date">
                                    <?= e($r['reservation_date'] ?? '') ?>
                                </td>
                                <td class="reservations-table__time">
                                    <?= e(substr((string) ($r['reservation_time'] ?? ''), 0, 5)) ?>
                                </td>
                                <td class="reservations-table__count">
                                    <?= (int) ($r['guest_count'] ?? 0) ?>
                                </td>
                                <td>
                                    <span class="reservation-badge <?= e($badge['class']) ?>">
                                        <?= e($badge['label']) ?>
                                    </span>
                                </td>
                                <td class="reservations-table__meta">
                                    <?= e(substr((string) ($r['created_at'] ?? ''), 0, 10)) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="reservations-table__footer">
                <?= $total ?> reserva<?= $total !== 1 ? 's' : '' ?> mostrada<?= $total !== 1 ? 's' : '' ?>
            </p>
        <?php endif; ?>
    </div>
</div>
