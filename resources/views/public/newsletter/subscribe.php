<?php

/**
 * @var bool $success
 * @var string $message
 * @var string|null $email
 */

$pageTitle = 'Suscripción al Newsletter';
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
                            <svg width="80" height="80" viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Mensaje enviado" style="color: var(--color-acento);">
                                <path d="M40 10 L70 30 L70 60 L40 70 L10 60 L10 30 Z" stroke="currentColor" stroke-width="3" fill="none" />
                                <circle cx="40" cy="35" r="3" fill="currentColor" />
                                <line x1="40" y1="45" x2="40" y2="55" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
                            </svg>
                        </div>

                        <h1 class="h3 mb-3" style="color: var(--color-primario);">¡Casi listo!</h1>

                        <p class="text-muted mb-4">
                            Hemos enviado un email de confirmación a<br>
                            <strong><?= htmlspecialchars($email ?? '', ENT_QUOTES, 'UTF-8') ?></strong>
                        </p>

                        <div class="alert alert-light border" style="background-color: var(--color-acento-suave, rgba(201, 169, 89, 0.1));">
                            <p class="mb-0" style="color: var(--color-primario);">
                                <strong><i class="bi bi-envelope" aria-hidden="true"></i> Revisa tu correo</strong><br>
                                Haz click en el enlace de confirmación para completar tu suscripción.
                            </p>
                        </div>

                        <p class="small text-muted mt-4">
                            ¿No lo encuentras? Revisa tu carpeta de spam.<br>
                            El email viene de <strong>noreply@komorebi.cafe</strong>
                        </p>

                        <div class="mt-4">
                            <a href="/" class="btn btn-outline-secondary">
                                Volver al inicio
                            </a>
                        </div>

                    <?php else: ?>
                        <!-- Error -->
                        <div class="mb-4">
                            <svg width="80" height="80" viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Error" style="color: var(--color-error);">
                                <circle cx="40" cy="40" r="35" stroke="currentColor" stroke-width="3" fill="none" />
                                <line x1="30" y1="30" x2="50" y2="50" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
                                <line x1="50" y1="30" x2="30" y2="50" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
                            </svg>
                        </div>

                        <h1 class="h3 mb-3 text-danger">Error en la suscripción</h1>

                        <p class="text-muted mb-4">
                            <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
                        </p>

                        <div class="mt-4">
                            <a href="/" class="btn btn-outline-secondary me-2">
                                Volver al inicio
                            </a>
                            <a href="#" x-data @click.prevent="history.back()" class="btn-komorebi btn-komorebi-primary">
                                Reintentar
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Mensaje de privacidad -->
            <div class="text-center mt-4">
                <p class="small text-muted">
                    <svg width="16" height="16" fill="currentColor" class="bi bi-shield-check me-1" viewBox="0 0 16 16">
                        <path d="M5.338 1.59a61.44 61.44 0 0 0-2.837.856.481.481 0 0 0-.328.39c-.554 4.157.726 7.19 2.253 9.188a10.725 10.725 0 0 0 2.287 2.233c.346.244.652.42.893.533.12.057.218.095.293.118a.55.55 0 0 0 .101.025.615.615 0 0 0 .1-.025c.076-.023.174-.061.294-.118.24-.113.547-.29.893-.533a10.726 10.726 0 0 0 2.287-2.233c1.527-1.997 2.807-5.031 2.253-9.188a.48.48 0 0 0-.328-.39c-.651-.213-1.75-.56-2.837-.855C9.552 1.29 8.531 1.067 8 1.067c-.53 0-1.552.223-2.662.524zM5.072.56C6.157.265 7.31 0 8 0s1.843.265 2.928.56c1.11.3 2.229.655 2.887.87a1.54 1.54 0 0 1 1.044 1.262c.596 4.477-.787 7.795-2.465 9.99a11.775 11.775 0 0 1-2.517 2.453 7.159 7.159 0 0 1-1.048.625c-.28.132-.581.24-.829.24s-.548-.108-.829-.24a7.158 7.158 0 0 1-1.048-.625 11.777 11.777 0 0 1-2.517-2.453C1.928 10.487.545 7.169 1.141 2.692A1.54 1.54 0 0 1 2.185 1.43 62.456 62.456 0 0 1 5.072.56z" />
                        <path d="M10.854 5.146a.5.5 0 0 1 0 .708l-3 3a.5.5 0 0 1-.708 0l-1.5-1.5a.5.5 0 1 1 .708-.708L7.5 7.793l2.646-2.647a.5.5 0 0 1 .708 0z" />
                    </svg>
                    Tu privacidad es importante. Lee nuestra
                    <a href="/legal/privacidad" style="color: var(--color-primario);">política de privacidad</a>.
                </p>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/main.php';
