<?php

/**
 * @var bool $success
 * @var string $message
 * @var string|null $email
 */

$pageTitle = 'Confirmación de suscripción';
ob_start();
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-5 text-center">
                    <?php if ($success): ?>
                        <!-- Éxito -->
                        <div class="mb-4">
                            <svg width="80" height="80" viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg" style="color: #28a745;">
                                <circle cx="40" cy="40" r="38" stroke="currentColor" stroke-width="4" fill="none" />
                                <path d="M25 40L35 50L55 30" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </div>

                        <h1 class="h3 mb-3" style="color: #8b4513;">¡Suscripción confirmada!</h1>

                        <p class="text-muted mb-4">
                            Gracias por unirte a la comunidad Komorebi. Hemos enviado un email de bienvenida a
                            <strong><?= htmlspecialchars($email ?? '', ENT_QUOTES, 'UTF-8') ?></strong>.
                        </p>

                        <div class="alert alert-light border" style="background-color: rgba(201, 169, 89, 0.1);">
                            <p class="mb-0" style="color: #8b4513;">
                                <strong>¡Recibiste un cupón de bienvenida!</strong><br>
                                Revisa tu correo para obtener un <strong>5% de descuento</strong> en tu primera visita.
                            </p>
                        </div>

                        <div class="mt-4">
                            <a href="/cafes" class="btn btn-lg" style="background-color: #c9a959; color: white; padding: 12px 40px;">
                                Explorar cafés
                            </a>
                        </div>

                    <?php else: ?>
                        <!-- Error -->
                        <div class="mb-4">
                            <svg width="80" height="80" viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg" style="color: #dc3545;">
                                <circle cx="40" cy="40" r="38" stroke="currentColor" stroke-width="4" fill="none" />
                                <path d="M30 30L50 50M50 30L30 50" stroke="currentColor" stroke-width="4" stroke-linecap="round" />
                            </svg>
                        </div>

                        <h1 class="h3 mb-3" style="color: #8b4513;">Error en la confirmación</h1>

                        <p class="text-muted mb-4">
                            <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
                        </p>

                        <div class="mt-4">
                            <a href="/" class="btn btn-outline-secondary">
                                Volver al inicio
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/main.php';
