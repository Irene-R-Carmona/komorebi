<?php

declare(strict_types=1);

/**
 * Vista: Gestión de Reseñas del Manager
 * Ruta: GET /manager/reviews
 *
 * @var string      $titulo
 * @var array       $reviews       Lista de reseñas del café
 * @var array       $stats         ['average' => float, 'count' => int, 'distribution' => [...]]
 * @var string      $csrf_token    Token CSRF para formularios POST
 * @var string|null $activeStatus  Filtro de estado activo
 * @var array       $meta          Metadatos de paginación {page, has_next_page}
 * @var array       $currentParams Query params para paginationLinks
 */

use App\Support\DateFormatting;
use App\Support\StatusLabeling;
use App\Support\ViewHelpers;

$meta ??= ['page' => 1, 'has_next_page' => false];
$currentParams ??= [];
$activeStatus ??= null;

$statusOptions = ['pending', 'approved', 'rejected'];
?>

<div class="container-fluid">
    <div class="page-header mb-4">
        <div class="page-header__content">
            <h1 class="page-header__title"><?= e($titulo) ?></h1>
            <p class="page-header__subtitle">Moderación y respuesta a reseñas de tu café</p>
        </div>
    </div>

    <!-- Resumen de estadísticas -->
    <div class="stats-grid mb-4">
        <div class="stat-card">
            <div class="stat-card__inner">
                <div class="stat-card__icon stat-card__icon--warning">
                    <i class="bi bi-star-fill" aria-hidden="true"></i>
                </div>
                <div class="stat-card__content">
                    <div class="stat-card__label">Valoración media</div>
                    <div class="stat-card__value"><?= number_format((float) ($stats['average'] ?? 0), 1) ?></div>
                </div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card__inner">
                <div class="stat-card__icon stat-card__icon--success">
                    <i class="bi bi-check-circle" aria-hidden="true"></i>
                </div>
                <div class="stat-card__content">
                    <div class="stat-card__label">Reseñas aprobadas</div>
                    <div class="stat-card__value"><?= (int) ($stats['count'] ?? 0) ?></div>
                </div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card__inner">
                <div class="stat-card__icon stat-card__icon--info">
                    <i class="bi bi-eye" aria-hidden="true"></i>
                </div>
                <div class="stat-card__content">
                    <div class="stat-card__label">Reseñas visibles</div>
                    <div class="stat-card__value"><?= (int) ($stats['count'] ?? 0) ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtro de estado -->
    <div class="glass-card mt-3 p-3 mb-3">
        <form method="GET" action="/manager/reviews" role="search"
            aria-label="Filtrar reseñas por estado" class="d-flex flex-wrap align-items-end gap-2">
            <div>
                <label for="filter-status" class="form-label small fw-semibold mb-1">Estado</label>
                <select id="filter-status" name="status" class="form-select form-select-sm">
                    <option value="">Todos los estados</option>
                    <?php foreach ($statusOptions as $optValue): ?>
                        <option value="<?= e($optValue) ?>"
                            <?= $activeStatus === $optValue ? 'selected' : '' ?>>
                            <?= e(StatusLabeling::reviewLabel($optValue)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-sm btn-komorebi-primary">Filtrar</button>
            <?php if ($activeStatus !== null): ?>
                <a href="/manager/reviews" class="btn btn-sm btn-outline-secondary">Limpiar</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Tabla de reseñas -->
    <div class="card-admin">
        <?php if (empty($reviews)): ?>
            <div class="empty-state">
                <div class="empty-state__icon" aria-hidden="true">
                    <i class="bi bi-star"></i>
                </div>
                <h4 class="empty-state__title">Sin reseñas todavía</h4>
                <p class="empty-state__text">No hay reseñas registradas en tu café.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Valoración</th>
                            <th>Reseña</th>
                            <th>Estado</th>
                            <th class="text-nowrap">Fecha</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reviews as $review):
                            $rstatus = (string) ($review['status'] ?? 'pending');
                            $rating = (int) ($review['rating'] ?? 0);
                            $stars = str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
                        ?>
                            <tr>
                                <td>
                                    <span class="star-rating" title="<?= $rating ?>/5">
                                        <?= $stars ?>
                                    </span>
                                    <br>
                                    <small class="text-muted"><?= $rating ?>/5</small>
                                </td>
                                <td class="text-break">
                                    <?php if (!empty($review['title'])): ?>
                                        <strong class="d-block mb-1"><?= e($review['title']) ?></strong>
                                    <?php endif; ?>
                                    <p class="mb-0 text-muted small lh-sm">
                                        <?= e($review['body'] ?? '') ?>
                                    </p>
                                </td>
                                <td>
                                    <span class="badge-status <?= e(StatusLabeling::reviewBadge($rstatus)) ?>">
                                        <?= e(StatusLabeling::reviewLabel($rstatus)) ?>
                                    </span>
                                </td>
                                <td class="text-muted small text-nowrap">
                                    <?= e(DateFormatting::toSpanishDate(substr((string) ($review['created_at'] ?? ''), 0, 10))) ?>
                                </td>
                                <td>
                                    <div class="d-flex gap-2 justify-content-center flex-wrap">
                                        <!-- Aprobar -->
                                        <?php if ($rstatus !== 'approved'): ?>
                                            <form method="POST"
                                                action="/manager/reviews/<?= (int) $review['id'] ?>/approve"
                                                x-data
                                                @submit.prevent="if(confirm('¿Aprobar esta reseña?')) $el.submit()"
                                                class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                                                <input type="hidden" name="id" value="<?= (int) $review['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-komorebi-primary">
                                                    <i class="bi bi-check-lg" aria-hidden="true"></i> Aprobar
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <!-- Rechazar -->
                                        <?php if ($rstatus !== 'rejected'): ?>
                                            <details class="reject-details">
                                                <summary class="btn btn-sm btn-komorebi-danger">
                                                    <i class="bi bi-x-lg" aria-hidden="true"></i> Rechazar
                                                </summary>
                                                <div class="reject-popover p-3">
                                                    <form method="POST"
                                                        action="/manager/reviews/<?= (int) $review['id'] ?>/reject">
                                                        <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                                                        <input type="hidden" name="id" value="<?= (int) $review['id'] ?>">
                                                        <label class="form-label fw-semibold small">
                                                            Motivo del rechazo
                                                        </label>
                                                        <textarea name="reason" required minlength="5" maxlength="500"
                                                            placeholder="Indica el motivo (5-500 caracteres)…"
                                                            class="form-control form-control-sm mb-2"
                                                            rows="3"></textarea>
                                                        <button type="submit"
                                                            class="btn btn-sm btn-komorebi-danger w-100">
                                                            Confirmar rechazo
                                                        </button>
                                                    </form>
                                                </div>
                                            </details>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <?= ViewHelpers::paginationLinks($meta, $currentParams) ?>

</div>
