<?php

declare(strict_types=1);

/**
 * Vista: Gestión de Reseñas del Manager
 * Ruta: GET /manager/reviews
 *
 * @var string $titulo
 * @var array  $reviews    Lista de reseñas del café (id, user_id, cafe_id, rating, title, body, status, created_at)
 * @var array  $stats      ['average' => float, 'count' => int, 'distribution' => [1=>int,...,5=>int]]
 * @var string $csrf_token Token CSRF para formularios POST
 */
?>

<div class="container-fluid">
    <div class="dashboard-header">
        <div>
            <h1 class="dashboard-header__title"><?= e($titulo) ?></h1>
            <p class="dashboard-header__subtitle">Moderación y respuesta a reseñas</p>
        </div>
    </div>

    <!-- Resumen de estadísticas -->
    <div class="stats-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:1rem;margin:1.5rem 0;">
        <div class="glass-card" style="padding:1.25rem;text-align:center;">
            <div style="font-size:2rem;font-weight:700;color:var(--primary-600,#2563eb);">
                <?= number_format((float) ($stats['average'] ?? 0), 1) ?>
            </div>
            <div style="font-size:0.85rem;color:var(--text-secondary,#6b7280);margin-top:0.25rem;">Valoración media</div>
        </div>
        <div class="glass-card" style="padding:1.25rem;text-align:center;">
            <div style="font-size:2rem;font-weight:700;color:var(--primary-600,#2563eb);">
                <?= (int) ($stats['count'] ?? 0) ?>
            </div>
            <div style="font-size:0.85rem;color:var(--text-secondary,#6b7280);margin-top:0.25rem;">Reseñas aprobadas</div>
        </div>
        <div class="glass-card" style="padding:1.25rem;text-align:center;">
            <div style="font-size:2rem;font-weight:700;color:var(--primary-600,#2563eb);">
                <?= count($reviews) ?>
            </div>
            <div style="font-size:0.85rem;color:var(--text-secondary,#6b7280);margin-top:0.25rem;">Reseñas visibles</div>
        </div>
    </div>

    <!-- Tabla de reseñas -->
    <div class="glass-card" style="margin-top:1rem;">
        <?php if (empty($reviews)): ?>
            <div style="padding:3rem;text-align:center;">
                <div style="font-size:3rem;margin-bottom:1rem;">⭐</div>
                <p style="color:var(--text-secondary,#6b7280);">No hay reseñas todavía en tu café.</p>
            </div>
        <?php else: ?>
            <div style="overflow-x:auto;">
                <table class="data-table" style="width:100%;border-collapse:collapse;">
                    <thead>
                        <tr style="border-bottom:2px solid var(--border-color,#e5e7eb);">
                            <th style="padding:0.75rem 1rem;text-align:left;">Valoración</th>
                            <th style="padding:0.75rem 1rem;text-align:left;">Reseña</th>
                            <th style="padding:0.75rem 1rem;text-align:left;">Estado</th>
                            <th style="padding:0.75rem 1rem;text-align:left;">Fecha</th>
                            <th style="padding:0.75rem 1rem;text-align:center;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reviews as $review): ?>
                            <?php
                            $statusColors = [
                                'pending'  => ['bg' => '#fef3c7', 'text' => '#92400e', 'label' => 'Pendiente'],
                                'approved' => ['bg' => '#d1fae5', 'text' => '#065f46', 'label' => 'Aprobada'],
                                'rejected' => ['bg' => '#fee2e2', 'text' => '#991b1b', 'label' => 'Rechazada'],
                            ];
                            $status      = $review['status'] ?? 'pending';
                            $statusStyle = $statusColors[$status] ?? $statusColors['pending'];
                            $rating      = (int) ($review['rating'] ?? 0);
                            $stars       = str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
                            ?>
                            <tr style="border-bottom:1px solid var(--border-color,#e5e7eb);">
                                <td style="padding:0.75rem 1rem;">
                                    <span style="color:#f59e0b;font-size:1.1rem;letter-spacing:0.05em;" title="<?= $rating ?>/5">
                                        <?= $stars ?>
                                    </span>
                                    <br>
                                    <small style="color:var(--text-secondary,#6b7280);"><?= $rating ?>/5</small>
                                </td>
                                <td style="padding:0.75rem 1rem;max-width:400px;">
                                    <?php if (!empty($review['title'])): ?>
                                        <strong style="display:block;margin-bottom:0.25rem;"><?= e($review['title']) ?></strong>
                                    <?php endif; ?>
                                    <p style="margin:0;color:var(--text-secondary,#6b7280);font-size:0.9rem;line-height:1.5;">
                                        <?= e($review['body'] ?? '') ?>
                                    </p>
                                </td>
                                <td style="padding:0.75rem 1rem;">
                                    <span style="display:inline-block;padding:0.25rem 0.75rem;border-radius:9999px;font-size:0.8rem;font-weight:600;background:<?= $statusStyle['bg'] ?>;color:<?= $statusStyle['text'] ?>;">
                                        <?= $statusStyle['label'] ?>
                                    </span>
                                </td>
                                <td style="padding:0.75rem 1rem;white-space:nowrap;color:var(--text-secondary,#6b7280);font-size:0.85rem;">
                                    <?= e($review['created_at'] ?? '') ?>
                                </td>
                                <td style="padding:0.75rem 1rem;">
                                    <div style="display:flex;gap:0.5rem;justify-content:center;flex-wrap:wrap;">
                                        <!-- Aprobar -->
                                        <?php if ($status !== 'approved'): ?>
                                            <form method="POST" action="/manager/reviews/<?= (int) $review['id'] ?>/approve"
                                                onsubmit="return confirm('¿Aprobar esta reseña?');"
                                                style="display:inline;">
                                                <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                                                <input type="hidden" name="id" value="<?= (int) $review['id'] ?>">
                                                <button type="submit"
                                                    style="padding:0.4rem 0.8rem;background:#10b981;color:#fff;border:none;border-radius:0.375rem;cursor:pointer;font-size:0.8rem;font-weight:600;">
                                                    ✓ Aprobar
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <!-- Rechazar -->
                                        <?php if ($status !== 'rejected'): ?>
                                            <details style="display:inline-block;position:relative;">
                                                <summary style="padding:0.4rem 0.8rem;background:#ef4444;color:#fff;border-radius:0.375rem;cursor:pointer;font-size:0.8rem;font-weight:600;list-style:none;">
                                                    ✗ Rechazar
                                                </summary>
                                                <div style="position:absolute;right:0;top:calc(100% + 0.5rem);z-index:10;background:#fff;border:1px solid #e5e7eb;border-radius:0.5rem;padding:1rem;width:280px;box-shadow:0 4px 6px rgba(0,0,0,0.1);">
                                                    <form method="POST" action="/manager/reviews/<?= (int) $review['id'] ?>/reject">
                                                        <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                                                        <input type="hidden" name="id" value="<?= (int) $review['id'] ?>">
                                                        <label style="display:block;font-size:0.85rem;font-weight:600;margin-bottom:0.5rem;">
                                                            Motivo del rechazo
                                                        </label>
                                                        <textarea name="reason" required minlength="5" maxlength="500"
                                                            placeholder="Indica el motivo (5-500 caracteres)…"
                                                            style="width:100%;padding:0.5rem;border:1px solid #d1d5db;border-radius:0.375rem;font-size:0.85rem;resize:vertical;min-height:80px;box-sizing:border-box;"></textarea>
                                                        <button type="submit"
                                                            style="margin-top:0.5rem;width:100%;padding:0.5rem;background:#ef4444;color:#fff;border:none;border-radius:0.375rem;cursor:pointer;font-weight:600;">
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
</div>

.btn--primary:hover {
background: var(--primary-700, #1d4ed8);
}
</style>
