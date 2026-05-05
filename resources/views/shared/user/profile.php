<?php

declare(strict_types=1);

use App\Core\Csrf;

// Toast (server-side, desde Flash)
$toastClass = null;
$toastIcon = null;
$toastMsg = null;

if (!empty($flash) && isset($flash['type'], $flash['message'])) {
    $toastMsg = (string) $flash['message'];
    if ($flash['type'] === 'success') {
        $toastClass = 'toast--exito';
        $toastIcon = 'bi bi-check-circle-fill';
    } elseif ($flash['type'] === 'error') {
        $toastClass = 'toast--error';
        $toastIcon = 'bi bi-exclamation-triangle-fill';
    } else {
        $toastClass = 'toast--info';
        $toastIcon = 'bi bi-info-circle';
    }
}
?>

<section class="seccion seccion--activa" x-data="profileApp()">
    <div class="seccion__container">

        <?php if ($toastClass && $toastMsg): ?>
            <output aria-live="polite" aria-atomic="true" class="toast-live-region">
                <div class="toast <?= $toastClass ?> toast-wrapper">
                    <span class="toast__icono"><i class="<?= e($toastIcon) ?>" aria-hidden="true"></i></span>
                    <span class="toast__mensaje"><?= $toastMsg ?></span>
                </div>
            </output>
        <?php endif; ?>

        <div class="profile-container">

            <!-- DASHBOARD HEADER: SOLO 2 BLOQUES -->
            <div class="dashboard-header">

                <!-- Membresía -->
                <aside class="member-card">
                    <div class="member-card__top">
                        <div class="member-card__logo">Komorebi Club</div>
                        <div class="member-card__tier">Nivel <span x-text="level.nivel">1</span> — <span x-text="level.nombre">Aprendiz</span></div>
                    </div>

                    <div class="member-card__user">
                        <!-- Avatar con selector preset -->
                        <div
                            class="member-card__avatar-wrapper"
                            x-data="avatarUpload"
                            :data-current-avatar="profile.avatar_url ?? ''">

                            <img
                                :src="currentAvatar || profile.avatar_url || '/images/avatars/default.svg'"
                                :alt="'Avatar de ' + (profile.name || 'Usuario')"
                                class="member-card__avatar-img">

                            <!-- Botones de acción -->
                            <div class="avatar-actions" x-show="!isUploading">
                                <button
                                    type="button"
                                    @click="openPicker()"
                                    class="avatar-btn avatar-btn--upload"
                                    title="Cambiar avatar">
                                    <i class="bi bi-palette" aria-hidden="true"></i>
                                </button>

                                <button
                                    type="button"
                                    @click="deleteAvatar()"
                                    x-show="currentAvatar || profile.avatar_url"
                                    class="avatar-btn avatar-btn--delete"
                                    title="Eliminar avatar">
                                    <i class="bi bi-trash" aria-hidden="true"></i>
                                </button>
                            </div>

                            <!-- Loading -->
                            <div class="avatar-loading" x-show="isUploading" x-cloak>
                                <div class="spinner"></div>
                            </div>

                            <!-- Picker modal -->
                            <dialog class="avatar-picker"
                                aria-labelledby="avatar-picker-title"
                                x-show="showPicker"
                                x-cloak
                                @click.outside="closePicker()"
                                @keydown.escape.document="showPicker && closePicker()">
                                <p class="avatar-picker__title" id="avatar-picker-title">Elige tu avatar</p>
                                <div class="avatar-picker__grid">
                                    <template x-for="opt in options" :key="opt.id">
                                        <button
                                            type="button"
                                            class="avatar-picker__item"
                                            :class="{'avatar-picker__item--active': (currentAvatar || profile.avatar_url) === opt.url}"
                                            :title="opt.label"
                                            @click="selectAvatar(opt.id)">
                                            <template x-if="opt.url">
                                                <img :src="opt.url" :alt="opt.label" width="48" height="48">
                                            </template>
                                            <template x-if="!opt.url">
                                                <span class="avatar-picker__initials" x-text="(profile.name || '?')[0].toUpperCase()"></span>
                                            </template>
                                        </button>
                                    </template>
                                </div>
                                <button type="button" class="avatar-picker__close" @click="closePicker()" aria-label="Cerrar selector de avatar">Cancelar</button>
                            </dialog>

                            <!-- Mensajes -->
                            <div class="avatar-messages" x-cloak>
                                <div class="alert alert--error" x-show="error" x-text="error"></div>
                                <div class="alert alert--success" x-show="success" x-text="success"></div>
                            </div>
                        </div>

                        <div class="member-card__info">
                            <h2 x-text="profile.name || 'Usuario'"></h2>
                            <p x-text="profile.email"></p>

                            <div class="member-card__stats">
                                <span><strong x-text="reservationsCount">0</strong> reservas</span>
                                <span> · </span>
                                <span>miembro desde <strong x-text="memberYear || '—'"></strong></span>
                            </div>

                            <!-- Progreso visual -->
                            <div class="member-progress"
                                :style="'--progress: ' + Math.min(100, Math.max(0, level.progreso || 0)) + '%'"
                                x-show="level.siguiente > 0">
                                <progress
                                    class="sr-only"
                                    :value="Math.min(100, Math.max(0, level.progreso || 0))"
                                    max="100"
                                    :aria-label="'Progreso al siguiente nivel: ' + (level.progreso || 0) + '%'">
                                </progress>
                                <div class="member-progress__bar" aria-hidden="true">
                                    <div class="member-progress__fill"></div>
                                </div>

                                <div class="member-card__stats member-card__stats--progress">
                                    Progreso <strong x-text="(level.progreso || 0) + '%'">0%</strong>
                                    <span class="member-card__level-hint" x-show="level.siguiente < 999999"> · siguiente nivel en <span x-text="level.siguiente"></span></span>
                                    <span class="member-card__level-hint" x-show="level.siguiente >= 999999"> · nivel máximo</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </aside>

                <!-- Próxima aventura -->
                <section class="next-adventure" x-show="nextReservation" x-cloak>
                    <div class="next-adventure__label">Próxima aventura</div>

                    <div class="next-adventure__card">
                        <img
                            class="next-adventure__img"
                            alt=""
                            src="data:image/svg+xml;utf8,<?=
                                                            rawurlencode("<svg xmlns='http://www.w3.org/2000/svg' width='80' height='80'>
                                <rect width='80' height='80' rx='12' fill='%23f3efe9'/>
                                <text x='40' y='48' font-size='34' text-anchor='middle'>茶</text>
                                </svg>")
?>" />

                        <div class="next-adventure__details">
                            <h3 x-text="nextReservation?.cafe_name ?? 'Café'"></h3>
                            <p><span x-text="nextDateHuman"></span> · <span x-text="nextTimeHuman"></span></p>
                            <p><span x-text="nextReservation?.guest_count ?? 1"></span> persona(s)</p>

                            <div class="status-pill" :class="nextStatusClass" x-show="nextStatusLabel !== ''">
                                Estado: <span x-text="nextStatusLabel"></span>
                            </div>
                        </div>
                    </div>

                    <div class="next-adventure__actions">
                        <a class="btn-komorebi btn-komorebi-primary" href="/reservas/mis-reservas">Ver mis reservas</a>

                        <a :href="'/reservas/mis-reservas/' + (nextReservation?.id ?? '') + '/cancelar'"
                            x-show="nextReservationIsCancelable"
                            class="btn-komorebi btn-komorebi-secondary">
                            Cancelar
                        </a>
                    </div>
                </section>

                <section class="next-adventure next-adventure--empty" x-show="!nextReservation" x-cloak>
                    <div class="next-adventure__label">Próxima aventura</div>
                    <p class="mb-0">
                        Aún no tienes una visita programada.<br>
                        Reserva tu próximo paseo por el bosque.
                    </p>

                    <div class="next-adventure__actions">
                        <a class="btn-komorebi btn-komorebi-primary" href="/reservas">Reservar</a>
                    </div>
                </section>

            </div>

            <!-- QUICK ACCESS: LOYALTY & WAITLIST -->
            <div class="dashboard-quick-access">

                <!-- Tarjeta de Fidelización -->
                <a href="/loyalty/card" class="quick-access-card">
                    <div class="quick-access-card__header">
                        <span class="quick-access-card__icon"><i class="bi bi-card-checklist" aria-hidden="true"></i></span>
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
                        <span class="quick-access-card__icon"><i class="bi bi-hourglass-split" aria-hidden="true"></i></span>
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
                        <span class="settings-icon"><i class="bi bi-person" aria-hidden="true"></i></span>
                        <h3 class="settings-title">Datos Personales</h3>
                    </div>

                    <form action="/profile/update" method="POST" autocomplete="on">
                        <?= Csrf::field() ?>

                        <div class="form-komorebi__group">
                            <label class="form-komorebi__label" for="name">Nombre visible</label>
                            <input id="name" type="text" name="name" class="form-komorebi__input" :value="profile.name" required>
                        </div>

                        <div class="form-komorebi__group">
                            <label class="form-komorebi__label" for="email">Correo electrónico</label>
                            <input id="email" type="email" name="email" class="form-komorebi__input" :value="profile.email"
                                required>
                        </div>

                        <button type="submit" class="btn-komorebi btn-komorebi-primary">Guardar cambios</button>
                    </form>

                    <div class="settings-divider"></div>

                    <div class="settings-export">
                        <p class="settings-export__hint">Descarga todos tus datos personales (GDPR Art. 20)</p>
                        <a href="/account/export-data" class="btn-icon-text" download>
                            <i class="bi bi-download" aria-hidden="true"></i>
                            Exportar mis datos
                        </a>
                    </div>
                </div>

                <div class="settings-box">
                    <div class="settings-header">
                        <span class="settings-icon"><i class="bi bi-lock" aria-hidden="true"></i></span>
                        <h3 class="settings-title">Seguridad</h3>
                    </div>

                    <form action="/account/change-password" method="POST" data-validate="password" autocomplete="on">
                        <?= Csrf::field() ?>

                        <div class="form-komorebi__group">
                            <label class="form-komorebi__label" for="current_password">Contraseña actual</label>
                            <input id="current_password" type="password" name="current_password" class="form-komorebi__input"
                                required>
                        </div>

                        <div class="form-komorebi__group">
                            <label class="form-komorebi__label" for="new_password">Nueva contraseña</label>
                            <input id="new_password" type="password" name="new_password" class="form-komorebi__input" required
                                minlength="8">
                        </div>

                        <div class="form-komorebi__group">
                            <label class="form-komorebi__label" for="new_password_confirm">Repetir nueva</label>
                            <input id="new_password_confirm" type="password" name="new_password_confirm"
                                class="form-komorebi__input" required>
                        </div>

                        <button type="submit" class="btn-komorebi btn-komorebi-primary">Actualizar clave</button>
                    </form>

                    <div class="settings-divider"></div>

                    <a href="/account/security" class="btn-icon-text">
                        <i class="bi bi-shield-check" aria-hidden="true"></i>
                        Historial de seguridad y zona peligrosa
                    </a>
                </div>

            </div>

            <!-- MIS RESEÑAS -->
            <div class="my-reviews-section">
                <div class="settings-header">
                    <span class="settings-icon"><i class="bi bi-pen" aria-hidden="true"></i></span>
                    <h3 class="settings-title">Mis Reseñas</h3>
                </div>

                <div class="reviews-empty" x-show="!loading && reviews.length === 0" x-cloak>
                    <p>Aún no has dejado reseñas. Cuando visites un café que ya hayas visitado, podrás compartir tu opinión.</p>
                    <a href="/cafes" class="btn-komorebi btn-komorebi-primary btn-komorebi--sm">Explorar cafés</a>
                </div>

                <div class="my-reviews-list" x-show="reviews.length > 0" x-cloak>
                    <template x-for="rev in reviews" :key="rev.id">
                        <article class="my-review-card">
                            <!-- Header -->
                            <div class="my-review__header">
                                <div class="my-review__top">
                                    <h4 class="my-review__title" x-text="rev.title ?? ''"></h4>
                                    <span class="my-review__cafe" x-text="rev.cafe_name ?? ''"></span>
                                </div>

                                <!-- Status badge -->
                                <span class="my-review__status" :class="'my-review__status--' + reviewStatusClass(rev.status)" x-text="reviewStatusLabel(rev.status)"></span>
                            </div>

                            <!-- Rating -->
                            <div class="my-review__rating">
                                <template x-for="i in 5" :key="i">
                                    <span class="review-star" :class="i <= (rev.rating ?? 0) ? 'review-star--filled' : ''">★</span>
                                </template>
                            </div>

                            <!-- Body -->
                            <p class="my-review__body" x-text="rev.body ?? ''"></p>

                            <!-- Meta -->
                            <div class="my-review__meta">
                                <time x-text="reviewDateHuman(rev.created_at)"></time>
                            </div>

                            <!-- Actions -->
                            <div class="my-review__actions">
                                <form method="POST" action="/reviews/update" class="form-inline">
                                    <?= Csrf::field() ?>
                                    <input type="hidden" name="id" :value="rev.id">
                                    <button type="button" class="btn-icon-text" @click="editReview(rev.id)">
                                        <i class="bi bi-pencil" aria-hidden="true"></i> Editar
                                    </button>
                                </form>

                                <form method="POST" action="/reviews/delete" class="form-inline" data-action="confirm" data-confirm="¿Eliminar esta reseña?">
                                    <?= Csrf::field() ?>
                                    <input type="hidden" name="id" :value="rev.id">
                                    <button type="submit" class="btn-icon-text btn-icon-text--danger">
                                        <i class="bi bi-trash" aria-hidden="true"></i> Eliminar
                                    </button>
                                </form>
                            </div>

                            <!-- Motivo de rechazo si aplica -->
                            <template x-if="rev.status === 'rejected' && rev.rejection_reason">
                                <div class="my-review__rejection-reason">
                                    <strong>Motivo del rechazo:</strong> <span x-text="rev.rejection_reason"></span>
                                </div>
                            </template>
                        </article>
                    </template>
                </div>
            </div>

        </div>
    </div>
</section>
