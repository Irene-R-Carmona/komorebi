<?php

declare(strict_types=1);

use App\Core\Csrf;

$name = (string) ($profile['name'] ?? '');
$email = (string) ($profile['email'] ?? '');
$createdAt = $profile['created_at'] ?? null;

$avatarLetter = $name !== '' ? mb_strtoupper(mb_substr($name, 0, 1)) : 'U';
$memberYear = !empty($createdAt) ? date('Y', strtotime((string) $createdAt)) : '';

$reservasCount = (int) ($stats['reservasCount'] ?? 0);

$nivelNum = (int) ($nivel['nivel'] ?? 1);
$nivelName = (string) ($nivel['nombre'] ?? 'Aprendiz');
$progreso = (int) ($nivel['progreso'] ?? 0);
$siguiente = (int) ($nivel['siguiente'] ?? 0);

$next = $nextReservation ?? null;

// Toast
$toastClass = null;
$toastIcon = null;
$toastMsg = null;

if (!empty($flash) && isset($flash['type'], $flash['message'])) {
    $toastMsg = (string) $flash['message'];
    if ($flash['type'] === 'success') {
        $toastClass = 'toast--exito';
        $toastIcon = '✨';
    } elseif ($flash['type'] === 'error') {
        $toastClass = 'toast--error';
        $toastIcon = '⚠️';
    } else {
        $toastClass = 'toast--info';
        $toastIcon = 'ℹ️';
    }
}

// Formateo fecha/hora próxima reserva
$nextDateHuman = '';
$nextTimeHuman = '';
$nextTs = null;

if (is_array($next) && !empty($next['reservation_date']) && !empty($next['reservation_time'])) {
    $nextTs = strtotime($next['reservation_date'] . ' ' . $next['reservation_time']);
    if ($nextTs !== false) {
        $nextDateHuman = date('d/m/Y', $nextTs);
        $nextTimeHuman = date('H:i', $nextTs);
    }
}

// Estado -> etiqueta + clase
$status = is_array($next) ? (string) ($next['status'] ?? '') : '';
$statusMap = [
    'pending' => ['label' => 'Pendiente', 'class' => 'status-pill--pending'],
    'confirmed' => ['label' => 'Confirmada', 'class' => 'status-pill--confirmed'],
    'active' => ['label' => 'Activa', 'class' => 'status-pill--active'],
];
$statusLabel = $statusMap[$status]['label'] ?? ($status !== '' ? strtoupper($status) : '');
$statusClass = $statusMap[$status]['class'] ?? '';

// Cancelable: solo pending/confirmed y futura
$isCancelable = is_array($next)
    && in_array($status, ['pending', 'confirmed'], true)
    && is_int($nextTs)
    && $nextTs > time();
?>

<section class="seccion seccion--activa">
    <div class="seccion__container">

        <?php if ($toastClass && $toastMsg): ?>
            <div class="toast <?= $toastClass ?> toast-wrapper">
                <span class="toast__icono"><?= $toastIcon ?></span>
                <span class="toast__mensaje"><?= $toastMsg ?></span>
            </div>
        <?php endif; ?>

        <div class="profile-container">

            <!-- DASHBOARD HEADER: SOLO 2 BLOQUES -->
            <div class="dashboard-header">

                <!-- Membresía -->
                <aside class="member-card">
                    <div class="member-card__top">
                        <div class="member-card__logo">Komorebi Club</div>
                        <div class="member-card__tier">Nivel <?= $nivelNum ?> — <?= $nivelName ?></div>
                    </div>

                    <div class="member-card__user">
                        <!-- Avatar con upload -->
                        <div
                            class="member-card__avatar-wrapper"
                            x-data="avatarUpload"
                            :data-current-avatar="'<?= $profile['avatar_url'] ?? '' ?>'">
                            <?php
                            $avatarSrc = '';
                            if (!empty($profile['avatar_url'])) {
                                $avatarSrc = (string) $profile['avatar_url'];
                            } else {
                                $seed = (int) crc32((string) ($profile['email'] ?? $name));
                                $gender = ($seed % 2 === 0) ? 'men' : 'women';
                                $id = abs($seed) % 100;
                                $avatarSrc = sprintf('https://randomuser.me/api/portraits/%s/%d.jpg', $gender, $id);
                            }
                            ?>

                            <img
                                src="<?= htmlspecialchars($avatarSrc) ?>"
                                alt="Avatar de <?= htmlspecialchars($name) ?>"
                                class="member-card__avatar-img"
                                x-show="!previewUrl">

                            <!-- Preview durante upload -->
                            <img
                                :src="previewUrl"
                                alt="Preview"
                                class="member-card__avatar-img"
                                x-show="previewUrl"
                                x-cloak>

                            <!-- Botones de acción -->
                            <div class="avatar-actions" x-show="!isUploading">
                                <label class="avatar-btn avatar-btn--upload" title="Cambiar avatar">
                                    📷
                                    <input
                                        type="file"
                                        accept="image/jpeg,image/png,image/webp"
                                        @change="handleFileInput"
                                        x-ref="fileInput"
                                        hidden>
                                </label>

                                <?php if (!empty($profile['avatar_url'])): ?>
                                    <button
                                        type="button"
                                        @click="deleteAvatar()"
                                        class="avatar-btn avatar-btn--delete"
                                        title="Eliminar avatar">
                                        🗑️
                                    </button>
                                <?php endif; ?>
                            </div>

                            <!-- Loading -->
                            <div class="avatar-loading" x-show="isUploading" x-cloak>
                                <div class="spinner"></div>
                            </div>

                            <!-- Mensajes -->
                            <div class="avatar-messages" x-cloak>
                                <div class="alert alert--error" x-show="error" x-text="error"></div>
                                <div class="alert alert--success" x-show="success" x-text="success"></div>
                            </div>
                        </div>

                        <div class="member-card__info">
                            <h2><?= $name !== '' ? $name : 'Usuario' ?></h2>
                            <p><?= $email ?></p>

                            <div class="member-card__stats">
                                <span><strong><?= $reservasCount ?></strong> reservas</span>
                                <span> · </span>
                                <span>miembro desde <strong><?= $memberYear !== '' ? $memberYear : '—' ?></strong></span>
                            </div>

                            <!-- Progreso visual -->
                            <?php if ($siguiente > 0): ?>
                                <div class="member-progress" style="--progress: <?= max(0, min(100, $progreso)) ?>%;">
                                    <div class="member-progress__bar">
                                        <div class="member-progress__fill"></div>
                                    </div>

                                    <div class="member-card__stats" style="margin-top:.35rem;">
                                        Progreso <strong><?= $progreso ?>%</strong>
                                        <?php if ($siguiente < 999999): ?>
                                            <span style="opacity:.85;"> · siguiente nivel en <?= $siguiente ?></span>
                                        <?php else: ?>
                                            <span style="opacity:.85;"> · nivel máximo</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </aside>

                <!-- Próxima aventura -->
                <?php if (is_array($next)): ?>
                    <section class="next-adventure">
                        <div class="next-adventure__label">Próxima aventura</div>

                        <div class="next-adventure__card">
                            <img
                                class="next-adventure__img"
                                alt=""
                                src="data:image/svg+xml;utf8,<?=
                                                                rawurlencode("<svg xmlns='http://www.w3.org/2000/svg' width='80' height='80'>
                                    <rect width='80' height='80' rx='12' fill='%23f3efe9'/>
                                    <text x='40' y='48' font-size='34' text-anchor='middle'>🍵</text>
                                    </svg>")
                                                                ?>" />

                            <div class="next-adventure__details">
                                <h3><?= $next['cafe_name'] ?? 'Café' ?></h3>
                                <p><?= $nextDateHuman ?> · <?= $nextTimeHuman ?></p>
                                <p><?= (int) ($next['guest_count'] ?? 1) ?> persona(s)</p>

                                <?php if ($statusLabel !== ''): ?>
                                    <div class="status-pill <?= $statusClass ?>">
                                        Estado: <?= $statusLabel ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="next-adventure__actions">
                            <a class="btn btn--primario" href="/reservas">Ver mis reservas</a>

                            <?php if ($isCancelable): ?>
                                <form method="POST" action="/reservas/cancelar"
                                    data-action="confirm" data-confirm="¿Cancelar esta reserva?">
                                    <?= Csrf::field() ?>
                                    <input type="hidden" name="id" value="<?= (int) $next['id'] ?>">
                                    <button type="submit" class="btn-danger-outline">
                                        Cancelar
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </section>
                <?php else: ?>
                    <section class="next-adventure next-adventure--empty">
                        <div class="next-adventure__label">Próxima aventura</div>
                        <p style="margin:0;">
                            Aún no tienes una visita programada.<br>
                            Reserva tu próximo paseo por el bosque.
                        </p>

                        <div class="next-adventure__actions">
                            <a class="btn btn--primario" href="/reservas">Reservar</a>
                        </div>
                    </section>
                <?php endif; ?>

            </div>

            <!-- QUICK ACCESS: LOYALTY & WAITLIST -->
            <style>
                .dashboard-quick-access {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                    gap: 1.5rem;
                    margin: 2rem 0;
                }

                .quick-access-card {
                    background: linear-gradient(135deg, #fff 0%, #f8f9fa 100%);
                    border: 2px solid #e9ecef;
                    border-radius: 15px;
                    padding: 1.5rem;
                    transition: all 0.3s ease;
                    text-decoration: none;
                    color: inherit;
                    display: block;
                }

                .quick-access-card:hover {
                    transform: translateY(-5px);
                    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
                    border-color: #667eea;
                }

                .quick-access-card__header {
                    display: flex;
                    align-items: center;
                    gap: 1rem;
                    margin-bottom: 1rem;
                }

                .quick-access-card__icon {
                    font-size: 2.5rem;
                    flex-shrink: 0;
                }

                .quick-access-card__title {
                    margin: 0;
                    font-size: 1.25rem;
                    font-weight: 600;
                    color: #333;
                }

                .quick-access-card__description {
                    color: #666;
                    margin: 0 0 1rem 0;
                    font-size: 0.95rem;
                    line-height: 1.5;
                }

                .quick-access-card__cta {
                    color: #667eea;
                    font-weight: 600;
                    font-size: 0.95rem;
                }

                .quick-access-card:hover .quick-access-card__cta {
                    color: #764ba2;
                }
            </style>

            <div class="dashboard-quick-access">

                <!-- Tarjeta de Fidelización -->
                <a href="/loyalty/card" class="quick-access-card">
                    <div class="quick-access-card__header">
                        <span class="quick-access-card__icon">🎴</span>
                        <h3 class="quick-access-card__title">Mi Tarjeta de Fidelización</h3>
                    </div>
                    <p class="quick-access-card__description">
                        Acumula sellos con cada visita y canjea recompensas exclusivas
                    </p>
                    <div class="quick-access-card__cta">
                        Ver mi tarjeta →
                    </div>
                </a>

                <!-- Listas de Espera -->
                <a href="/user/waitlists" class="quick-access-card">
                    <div class="quick-access-card__header">
                        <span class="quick-access-card__icon">⏳</span>
                        <h3 class="quick-access-card__title">Mis Listas de Espera</h3>
                    </div>
                    <p class="quick-access-card__description">
                        Consulta tu posición y confirma plazas disponibles
                    </p>
                    <div class="quick-access-card__cta">
                        Ver mis listas →
                    </div>
                </a>

            </div>

            <!-- CONFIGURACIÓN -->
            <div class="settings-grid">

                <div class="settings-box">
                    <div class="settings-header">
                        <span class="settings-icon">👤</span>
                        <h3 class="settings-title">Datos Personales</h3>
                    </div>

                    <form action="/perfil/update" method="POST" autocomplete="on">
                        <?= Csrf::field() ?>

                        <div class="form-group">
                            <label class="form-label" for="name">Nombre visible</label>
                            <input id="name" type="text" name="name" class="form-input" value="<?= $name ?>" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="email">Correo electrónico</label>
                            <input id="email" type="email" name="email" class="form-input" value="<?= $email ?>"
                                required>
                        </div>

                        <button type="submit" class="btn-update">Guardar cambios</button>
                    </form>
                </div>

                <div class="settings-box">
                    <div class="settings-header">
                        <span class="settings-icon">🔒</span>
                        <h3 class="settings-title">Seguridad</h3>
                    </div>

                    <form action="/perfil/password" method="POST" data-validate="password" autocomplete="on">
                        <?= Csrf::field() ?>

                        <div class="form-group">
                            <label class="form-label" for="current_password">Contraseña actual</label>
                            <input id="current_password" type="password" name="current_password" class="form-input"
                                required>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="new_password">Nueva contraseña</label>
                            <input id="new_password" type="password" name="new_password" class="form-input" required
                                minlength="8">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="new_password_confirm">Repetir nueva</label>
                            <input id="new_password_confirm" type="password" name="new_password_confirm"
                                class="form-input" required>
                        </div>

                        <button type="submit" class="btn-danger-outline">Actualizar clave</button>
                    </form>
                </div>

            </div>

            <!-- MIS RESEÑAS -->
            <div class="my-reviews-section">
                <div class="settings-header">
                    <span class="settings-icon">✍️</span>
                    <h3 class="settings-title">Mis Reseñas</h3>
                </div>

                <?php
                $userReviews = (array) ($userReviews ?? []);
                if (empty($userReviews)): ?>
                    <div class="reviews-empty">
                        <p>Aún no has dejado reseñas. Cuando visites un café que ya hayas visitado, podrás compartir tu opinión.</p>
                        <a href="/cafes" class="btn btn--primario btn--pequeno">Explorar cafés</a>
                    </div>
                <?php else: ?>
                    <div class="my-reviews-list">
                        <?php foreach ($userReviews as $review): ?>
                            <article class="my-review-card">
                                <!-- Header -->
                                <div class="my-review__header">
                                    <div class="my-review__top">
                                        <h4 class="my-review__title">
                                            <?= e($review['title'] ?? '') ?>
                                        </h4>
                                        <span class="my-review__cafe">
                                            <?= e($review['cafe_name'] ?? '') ?>
                                        </span>
                                    </div>

                                    <!-- Status badge -->
                                    <?php
                                    $status = $review['status'] ?? 'pending';
                                    $statusLabels = [
                                        'pending' => ['label' => 'Pendiente', 'class' => 'status-pending'],
                                        'approved' => ['label' => 'Aprobada', 'class' => 'status-approved'],
                                        'rejected' => ['label' => 'Rechazada', 'class' => 'status-rejected'],
                                    ];
                                    $statusInfo = $statusLabels[$status] ?? ['label' => $status, 'class' => ''];
                                    ?>
                                    <span class="my-review__status my-review__status--<?= $statusInfo['class'] ?>">
                                        <?= $statusInfo['label'] ?>
                                    </span>
                                </div>

                                <!-- Rating -->
                                <div class="my-review__rating">
                                    <?php
                                    $rating = (int) ($review['rating'] ?? 0);
                                    for ($i = 1; $i <= 5; $i++):
                                        $filled = $i <= $rating ? 'review-star--filled' : '';
                                    ?>
                                        <span class="review-star <?= $filled ?>">★</span>
                                    <?php endfor; ?>
                                </div>

                                <!-- Body -->
                                <p class="my-review__body">
                                    <?= e($review['body'] ?? '') ?>
                                </p>

                                <!-- Meta -->
                                <div class="my-review__meta">
                                    <time>
                                        <?= date('d \d\e F, Y', strtotime($review['created_at'] ?? 'now')) ?>
                                    </time>
                                </div>

                                <!-- Actions -->
                                <div class="my-review__actions">
                                    <form method="POST" action="/reviews/update" class="form-inline" style="display: contents;">
                                        <?= Csrf::field() ?>
                                        <input type="hidden" name="id" value="<?= (int) $review['id'] ?>">
                                        <button type="button" class="btn-icon-text" @click="editReview(<?= (int) $review['id'] ?>)">
                                            ✏️ Editar
                                        </button>
                                    </form>

                                    <form method="POST" action="/reviews/delete" class="form-inline" style="display: contents;" data-action="confirm" data-confirm="¿Eliminar esta reseña?">
                                        <?= Csrf::field() ?>
                                        <input type="hidden" name="id" value="<?= (int) $review['id'] ?>">
                                        <button type="submit" class="btn-icon-text btn-icon-text--danger">
                                            🗑️ Eliminar
                                        </button>
                                    </form>
                                </div>

                                <!-- Motivo de rechazo si aplica -->
                                <?php if ($status === 'rejected' && !empty($review['rejection_reason'])): ?>
                                    <div class="my-review__rejection-reason">
                                        <strong>Motivo del rechazo:</strong> <?= e($review['rejection_reason']) ?>
                                    </div>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>
</section>
