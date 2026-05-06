<?php

use App\Core\Csrf;

/**
 * Vista: Confirmación de eliminación de cuenta
 * Ruta: GET /account/delete
 *
 * @var string $email
 * @var string $nombre
 */

$email ??= '';
$nombre ??= '';

?>

<?php $this->extend('layouts/main.php'); ?>

<?php $this->start('content'); ?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">

            <!-- Cabecera -->
            <div class="text-center mb-4">
                <div class="account-danger-icon" aria-hidden="true">
                    <i class="bi bi-person-x-fill"></i>
                </div>
                <h1 class="h3 fw-bold account-page-title mb-2">
                    Eliminar mi cuenta
                </h1>
                <p class="text-danger mb-0">Esta acción es <strong>permanente e irreversible</strong>.</p>
            </div>

            <!-- Qué se elimina -->
            <div class="settings-box settings-box--danger mb-4">
                <div class="settings-header">
                    <span class="settings-icon"><i class="bi bi-list-ul" aria-hidden="true"></i></span>
                    <h2 class="settings-title">Se eliminará de forma permanente</h2>
                </div>
                <ul class="small mb-0 ps-3">
                    <li class="mb-1">Tu cuenta (<strong><?= e($email) ?></strong>) y datos de perfil</li>
                    <li class="mb-1">Historial de reservas</li>
                    <li class="mb-1">Reseñas publicadas</li>
                    <li class="mb-1">Puntos y canjes de fidelización</li>
                    <li class="mb-1">Productos guardados en favoritos</li>
                    <li class="mb-1">Artículos en tu carrito</li>
                    <li class="mb-1">Inscripciones a lista de espera</li>
                    <li>Todas las sesiones activas</li>
                </ul>
            </div>

            <!-- Aviso -->
            <div class="alert alert-danger d-flex gap-2 small mb-4" role="alert">
                <i class="bi bi-exclamation-triangle-fill flex-shrink-0 mt-1" aria-hidden="true"></i>
                <p class="mb-0">
                    Una vez confirmada la eliminación, <strong>no podrás recuperar ningún dato</strong>.
                    Si tienes reservas activas, considera cancelarlas antes.
                </p>
            </div>

            <!-- Formulario de confirmación -->
            <form method="POST" action="/account/delete"
                x-data="{ confirmEmail: '', expected: '<?= e($email) ?>' }"
                @submit.prevent="if (confirmEmail === expected) $el.submit(); else alert('El email no coincide. Escribe exactamente: <?= e($email) ?>')">

                <div class="form-komorebi__group mb-4">
                    <label for="confirm-email" class="form-komorebi__label form-komorebi__label--required">
                        Para confirmar, escribe tu dirección de correo electrónico
                    </label>
                    <input
                        id="confirm-email"
                        type="email"
                        autocomplete="off"
                        x-model="confirmEmail"
                        placeholder="<?= e($email) ?>"
                        class="form-komorebi__input"
                        required>
                </div>

                <?= Csrf::field() ?>

                <button type="submit" class="btn-komorebi btn-komorebi-danger btn-komorebi--full justify-content-center">
                    <i class="bi bi-trash3" aria-hidden="true"></i>
                    Eliminar mi cuenta definitivamente
                </button>
            </form>

            <div class="text-center mt-4">
                <a href="/account/security" class="account-back-link">
                    <i class="bi bi-arrow-left" aria-hidden="true"></i> Cancelar y volver a Seguridad
                </a>
            </div>

        </div>
    </div>
</div>

<?php $this->end(); ?>
