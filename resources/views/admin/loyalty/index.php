<?php

/**
 * Vista: Gestión de Fidelización (Admin) — SSR
 * Ruta: GET /admin/loyalty
 *
 * @var string $titulo
 * @var string $csrf_token
 * @var array  $tier_distribution   { bronze: int, silver: int, gold: int, platinum: int }
 * @var array  $catalog             Lista de items del catálogo
 * @var array  $redemption_stats    { total: int, used: int, pending: int, expired: int, last_30_days: int }
 * @var array  $recent_redemptions  Canjes recientes (máx. 10)
 */

$tier_distribution ??= ['bronze' => 0, 'silver' => 0, 'gold' => 0, 'platinum' => 0];
$catalog ??= [];
$redemption_stats ??= ['total' => 0, 'used' => 0, 'pending' => 0, 'expired' => 0, 'last_30_days' => 0];
$recent_redemptions ??= [];
$csrf_token ??= '';

$alpineData = json_encode([
    'csrf' => $csrf_token,
    'activeTab' => 'dashboard',
    'toast' => null,
    'loading' => false,
    'toastDismissMs' => 3500,
    'reloadDelayMs' => 1000,
], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);

$tierLabels = [
    'bronze' => 'Bronce',
    'silver' => 'Plata',
    'gold' => 'Oro',
    'platinum' => 'Platino',
];
?>

<div class="container-fluid" x-data='<?= $alpineData ?>' x-cloak>

    <!-- Header -->
    <div class="page-header mb-4">
        <div class="page-header__content">
            <h1 class="page-header__title">&#127942; Fidelización</h1>
            <p class="page-header__subtitle">Panel de gestión del programa de fidelización</p>
        </div>
    </div>

    <!-- Notificación toast -->
    <template x-if="toast">
        <div class="alert"
            :class="toast.type === 'success' ? 'alert-success' : 'alert-danger'"
            x-text="toast.msg"
            x-init="setTimeout(() => toast = null, toastDismissMs)">
        </div>
    </template>

    <!-- Tabs -->
    <div class="mb-4">
        <ul class="nav nav-tabs">
            <li class="nav-item">
                <button class="nav-link" :class="{ active: activeTab === 'dashboard' }"
                    @click="activeTab = 'dashboard'">Dashboard</button>
            </li>
            <li class="nav-item">
                <button class="nav-link" :class="{ active: activeTab === 'catalogo' }"
                    @click="activeTab = 'catalogo'">Cat&aacute;logo</button>
            </li>
            <li class="nav-item">
                <button class="nav-link" :class="{ active: activeTab === 'canjes' }"
                    @click="activeTab = 'canjes'">Canjes recientes</button>
            </li>
        </ul>
    </div>

    <!-- Tab: Dashboard -->
    <div x-show="activeTab === 'dashboard'">

        <!-- Distribución por tier -->
        <h2 class="h5 mb-3">Distribución por nivel</h2>
        <div class="stats-grid mb-4">
            <?php foreach (['bronze', 'silver', 'gold', 'platinum'] as $tier): ?>
                <div class="stat-card">
                    <div class="stat-card__inner">
                        <div class="stat-card__icon tier-icon--<?= htmlspecialchars($tier, ENT_QUOTES, 'UTF-8') ?>">
                            &#127942;
                        </div>
                        <div class="stat-card__content">
                            <div class="stat-card__label"><?= $tierLabels[$tier] ?></div>
                            <div class="stat-card__value"><?= (int) ($tier_distribution[$tier] ?? 0) ?></div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Stats de canjes -->
        <h2 class="h5 mb-3">Estad&iacute;sticas de canjes</h2>
        <div class="stats-grid mb-4">
            <div class="stat-card">
                <div class="stat-card__inner">
                    <div class="stat-card__icon stat-card__icon--primary">&#128203;</div>
                    <div class="stat-card__content">
                        <div class="stat-card__label">Total canjes</div>
                        <div class="stat-card__value"><?= (int) ($redemption_stats['total'] ?? 0) ?></div>
                    </div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-card__inner">
                    <div class="stat-card__icon stat-card__icon--success">&#9989;</div>
                    <div class="stat-card__content">
                        <div class="stat-card__label">Usados</div>
                        <div class="stat-card__value"><?= (int) ($redemption_stats['used'] ?? 0) ?></div>
                    </div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-card__inner">
                    <div class="stat-card__icon stat-card__icon--warning">&#9203;</div>
                    <div class="stat-card__content">
                        <div class="stat-card__label">Pendientes</div>
                        <div class="stat-card__value"><?= (int) ($redemption_stats['pending'] ?? 0) ?></div>
                    </div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-card__inner">
                    <div class="stat-card__icon stat-card__icon--error">&#10060;</div>
                    <div class="stat-card__content">
                        <div class="stat-card__label">Expirados</div>
                        <div class="stat-card__value"><?= (int) ($redemption_stats['expired'] ?? 0) ?></div>
                    </div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-card__inner">
                    <div class="stat-card__icon stat-card__icon--info">&#128197;</div>
                    <div class="stat-card__content">
                        <div class="stat-card__label">&Uacute;ltimos 30 d&iacute;as</div>
                        <div class="stat-card__value"><?= (int) ($redemption_stats['last_30_days'] ?? 0) ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tab: Catálogo -->
    <div x-show="activeTab === 'catalogo'">
        <div class="card-admin">
            <?php if (empty($catalog)): ?>
                <div class="empty-state">
                    <div class="fs-1 mb-3">&#127942;</div>
                    <p class="mb-0">No hay recompensas en el cat&aacute;logo</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Recompensa</th>
                                <th>Sellos</th>
                                <th>Tier m&iacute;nimo</th>
                                <th>Validez (d&iacute;as)</th>
                                <th>Estado</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($catalog as $item): ?>
                                <?php $itemId = (int) ($item['id'] ?? 0); ?>
                                <tr>
                                    <td><?= htmlspecialchars($item['name_es'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= (int) ($item['stamps_required'] ?? 0) ?></td>
                                    <td>
                                        <?php $tierReq = $item['tier_required'] ?? 'bronze'; ?>
                                        <?php $validTiers = ['bronze', 'silver', 'gold', 'platinum']; ?>
                                        <span class="badge tier-badge--<?= in_array($tierReq, $validTiers, true) ? htmlspecialchars($tierReq, ENT_QUOTES, 'UTF-8') : 'default' ?>">
                                            <?= $tierLabels[$tierReq] ?? htmlspecialchars($tierReq, ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    </td>
                                    <td><?= (int) ($item['validity_days'] ?? 30) ?></td>
                                    <td>
                                        <?php if ($item['is_active']): ?>
                                            <span class="badge bg-success">Activo</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inactivo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-secondary"
                                            :disabled="loading"
                                            @click="
                                                loading = true;
                                                fetch(window.AppRoutes.adminLoyaltyCatalog + '/<?= $itemId ?>/toggle', {
                                                    method: 'PATCH',
                                                    headers: {
                                                        'Content-Type': 'application/json',
                                                        'X-CSRF-Token': csrf,
                                                        'Accept': 'application/json'
                                                    },
                                                    body: JSON.stringify({ is_active: <?= $item['is_active'] ? 'false' : 'true' ?> })
                                                })
                                                .then(r => r.json())
                                                .then(d => {
                                                    toast = d.ok
                                                        ? { type: 'success', msg: 'Estado actualizado.' }
                                                        : { type: 'error', msg: d.error ?? 'Error al actualizar.' };
                                                    if (d.ok) setTimeout(() => location.reload(), reloadDelayMs);
                                                })
                                                .catch(() => toast = { type: 'error', msg: 'Error de red.' })
                                                .finally(() => loading = false)
                                            ">
                                            <?= $item['is_active'] ? 'Desactivar' : 'Activar' ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tab: Canjes recientes -->
    <div x-show="activeTab === 'canjes'">
        <div class="card-admin">
            <?php if (empty($recent_redemptions)): ?>
                <div class="empty-state">
                    <div class="fs-1 mb-3">&#127942;</div>
                    <p class="mb-0">No hay canjes recientes</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Usuario</th>
                                <th>Tipo</th>
                                <th>Sellos</th>
                                <th>Estado</th>
                                <th>C&oacute;digo</th>
                                <th>Canjeado</th>
                                <th>Expira</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_redemptions as $r): ?>
                                <tr>
                                    <td>
                                        <div><?= htmlspecialchars($r['user_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                                        <div class="text-muted small"><?= htmlspecialchars($r['user_email'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                                    </td>
                                    <td><?= htmlspecialchars($r['reward_type'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= (int) ($r['stamps_cost'] ?? 0) ?></td>
                                    <td>
                                        <?php $rStatus = $r['status'] ?? 'pending'; ?>
                                        <?php if ($rStatus === 'used'): ?>
                                            <span class="badge bg-success">Usado</span>
                                        <?php elseif ($rStatus === 'expired'): ?>
                                            <span class="badge bg-secondary">Expirado</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark">Pendiente</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><code><?= htmlspecialchars($r['redemption_code'] ?? '', ENT_QUOTES, 'UTF-8') ?></code></td>
                                    <td class="small"><?= htmlspecialchars($r['redeemed_at'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="small"><?= htmlspecialchars($r['expires_at'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="px-3 py-2 border-top">
                    <a href="/api/v1/admin/loyalty/redemptions" class="text-muted small">
                        Ver todos los canjes v&iacute;a API &rarr;
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>
