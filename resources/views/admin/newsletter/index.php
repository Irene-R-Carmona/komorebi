<?php

/**
 * Vista: Gestión de Newsletter (Admin) — SSR
 * Ruta: GET /admin/newsletter
 *
 * @var string $titulo
 * @var string $csrf_token
 * @var array  $stats     { total, confirmed, pending, unsubscribed, this_month }
 * @var array  $items     Lista de suscriptores (página actual)
 * @var int    $total     Total de registros sin paginar
 * @var int    $page      Página actual
 * @var int    $per_page  Registros por página
 * @var bool   $has_next  Hay página siguiente
 * @var array  $filters   Filtros activos { email, status }
 */

$stats ??= ['total' => 0, 'confirmed' => 0, 'pending' => 0, 'unsubscribed' => 0, 'this_month' => 0];
$items ??= [];
$total ??= 0;
$page ??= 1;
$per_page ??= 25;
$has_next ??= false;
$filters ??= [];
$csrf_token ??= '';

$alpineData = json_encode([
    'csrf' => $csrf_token,
    'confirmEmail' => null,
    'showConfirm' => false,
    'loading' => false,
    'toast' => null,
], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
?>

<div class="container-fluid" x-data='<?= $alpineData ?>' x-cloak>

    <!-- Header -->
    <div class="page-header mb-4">
        <div class="page-header__content">
            <h1 class="page-header__title">&#128140; Newsletter</h1>
            <p class="page-header__subtitle">Gestiona los suscriptores de la lista de correo</p>
        </div>
        <div class="page-header__actions">
            <a href="/api/v1/admin/newsletter/export"
                class="btn btn-komorebi-secondary">
                &#11015; Exportar CSV
            </a>
        </div>
    </div>

    <!-- Notificación toast -->
    <template x-if="toast">
        <div class="alert"
            :class="toast.type === 'success' ? 'alert-success' : 'alert-danger'"
            x-text="toast.msg"
            x-init="setTimeout(() => toast = null, 3500)">
        </div>
    </template>

    <!-- Estadísticas -->
    <div class="stats-grid mb-4">
        <div class="stat-card">
            <div class="stat-card__inner">
                <div class="stat-card__icon stat-card__icon--primary">&#128140;</div>
                <div class="stat-card__content">
                    <div class="stat-card__label">Total suscriptores</div>
                    <div class="stat-card__value"><?= (int) ($stats['total'] ?? 0) ?></div>
                </div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card__inner">
                <div class="stat-card__icon stat-card__icon--success">&#9989;</div>
                <div class="stat-card__content">
                    <div class="stat-card__label">Confirmados</div>
                    <div class="stat-card__value"><?= (int) ($stats['confirmed'] ?? 0) ?></div>
                </div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card__inner">
                <div class="stat-card__icon stat-card__icon--warning">&#9203;</div>
                <div class="stat-card__content">
                    <div class="stat-card__label">Pendientes</div>
                    <div class="stat-card__value"><?= (int) ($stats['pending'] ?? 0) ?></div>
                </div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card__inner">
                <div class="stat-card__icon stat-card__icon--error">&#10060;</div>
                <div class="stat-card__content">
                    <div class="stat-card__label">Desuscritos</div>
                    <div class="stat-card__value"><?= (int) ($stats['unsubscribed'] ?? 0) ?></div>
                </div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card__inner">
                <div class="stat-card__icon stat-card__icon--info">&#128197;</div>
                <div class="stat-card__content">
                    <div class="stat-card__label">Este mes</div>
                    <div class="stat-card__value"><?= (int) ($stats['this_month'] ?? 0) ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="filter-bar mb-4">
        <form method="GET" action="/admin/newsletter" class="filter-bar__row">
            <div class="filter-bar__filters">
                <div>
                    <label for="email" class="form-label mb-1">Email</label>
                    <input type="email" name="email" id="email"
                        value="<?= htmlspecialchars($filters['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        placeholder="Buscar por email…"
                        class="form-control form-control-sm">
                </div>
                <div>
                    <label for="status" class="form-label mb-1">Estado</label>
                    <select name="status" id="status" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <option value="confirmed" <?= ($filters['status'] ?? '') === 'confirmed' ? 'selected' : '' ?>>Confirmado</option>
                        <option value="pending" <?= ($filters['status'] ?? '') === 'pending' ? 'selected' : '' ?>>Pendiente</option>
                        <option value="unsubscribed" <?= ($filters['status'] ?? '') === 'unsubscribed' ? 'selected' : '' ?>>Desuscrito</option>
                    </select>
                </div>
            </div>
            <div class="filter-bar__actions d-flex gap-2 align-items-end">
                <button type="submit" class="btn btn-komorebi-primary">Filtrar</button>
                <a href="/admin/newsletter" class="btn btn-komorebi-secondary">Limpiar</a>
            </div>
        </form>
    </div>

    <!-- Tabla -->
    <div class="card-admin">
        <?php if (empty($items)): ?>
            <div class="empty-state">
                <div class="fs-1 mb-3">&#128140;</div>
                <p class="mb-0">No hay suscriptores con los filtros seleccionados</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Email</th>
                            <th>Estado</th>
                            <th>Suscrito</th>
                            <th>Confirmado</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td><?= htmlspecialchars($item['email'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                <td>
                                    <?php $status = $item['status'] ?? 'pending'; ?>
                                    <?php if ($status === 'confirmed'): ?>
                                        <span class="badge bg-success">Confirmado</span>
                                    <?php elseif ($status === 'unsubscribed'): ?>
                                        <span class="badge bg-secondary">Desuscrito</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Pendiente</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($item['created_at'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($item['confirmed_at'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="text-end">
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-danger"
                                        @click="confirmEmail = <?= htmlspecialchars(json_encode($item['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>; showConfirm = true">
                                        Eliminar
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <?php if ($page > 1 || $has_next): ?>
                <div class="d-flex justify-content-between align-items-center px-3 py-2 border-top">
                    <span class="text-muted small">
                        Página <?= (int) $page ?> &mdash; <?= (int) $total ?> registros totales
                    </span>
                    <div class="d-flex gap-2">
                        <?php if ($page > 1): ?>
                            <a href="?<?= http_build_query(array_merge($filters, ['page' => $page - 1])) ?>"
                                class="btn btn-sm btn-komorebi-secondary">&#8592; Anterior</a>
                        <?php endif; ?>
                        <?php if ($has_next): ?>
                            <a href="?<?= http_build_query(array_merge($filters, ['page' => $page + 1])) ?>"
                                class="btn btn-sm btn-komorebi-secondary">Siguiente &#8594;</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Modal de confirmación de eliminación -->
    <div class="modal fade"
        :class="{ show: showConfirm, 'd-block': showConfirm }"
        tabindex="-1"
        role="dialog"
        x-show="showConfirm"
        style="background: rgba(0,0,0,.5)">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmar eliminación</h5>
                    <button type="button" class="btn-close" @click="showConfirm = false; confirmEmail = null"></button>
                </div>
                <div class="modal-body">
                    <p>¿Seguro que quieres eliminar la suscripción de
                        <strong x-text="confirmEmail"></strong>?
                    </p>
                    <p class="text-muted small mb-0">Esta acción no se puede deshacer.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-komorebi-secondary"
                        @click="showConfirm = false; confirmEmail = null">
                        Cancelar
                    </button>
                    <button type="button"
                        class="btn btn-danger"
                        :disabled="loading"
                        @click="
                                loading = true;
                                fetch('/api/v1/admin/newsletter/subscribers/' + encodeURIComponent(confirmEmail), {
                                    method: 'DELETE',
                                    headers: { 'X-CSRF-Token': csrf, 'Accept': 'application/json' }
                                })
                                .then(r => r.json())
                                .then(d => {
                                    showConfirm = false;
                                    confirmEmail = null;
                                    toast = d.ok
                                        ? { type: 'success', msg: 'Suscriptor eliminado correctamente.' }
                                        : { type: 'error', msg: d.error ?? 'Error al eliminar.' };
                                    if (d.ok) setTimeout(() => location.reload(), 1200);
                                })
                                .catch(() => {
                                    toast = { type: 'error', msg: 'Error de red. Intenta de nuevo.' };
                                })
                                .finally(() => loading = false)
                            ">
                        <span x-show="!loading">Eliminar</span>
                        <span x-show="loading">Eliminando…</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

</div>
