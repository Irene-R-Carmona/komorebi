<?php

/**
 * @var bool $success
 * @var string $message
 */

$pageTitle = 'Baja de newsletter';
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
                            <svg width="80" height="80" viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Dado de baja" style="color: var(--color-texto-suave);">
                                <circle cx="40" cy="40" r="38" stroke="currentColor" stroke-width="4" fill="none" />
                                <path d="M30 40L50 40" stroke="currentColor" stroke-width="4" stroke-linecap="round" />
                            </svg>
                        </div>

                        <h1 class="h3 mb-3" style="color: var(--color-primario);">Te hemos dado de baja</h1>

                        <p class="text-muted mb-4">
                            Ya no recibirás nuestras newsletters. Si cambias de opinión, siempre puedes volver a suscribirte desde nuestra página.
                        </p>

                        <div class="alert alert-light border">
                            <p class="mb-0 text-muted small">
                                Sentimos verte partir. Si fue por algún problema, nos encantaría saberlo para mejorar.
                            </p>
                        </div>

                        <div class="mt-4">
                            <a href="/" class="btn btn-outline-secondary me-2">
                                Volver al inicio
                            </a>
                            <a href="/contacto" class="btn btn-link" style="color: var(--color-acento);">
                                Enviar feedback
                            </a>
                        </div>

                    <?php else: ?>
                        <!-- Error -->
                        <div class="mb-4">
                            <svg width="80" height="80" viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Error" style="color: var(--color-error);">
                                <circle cx="40" cy="40" r="38" stroke="currentColor" stroke-width="4" fill="none" />
                                <path d="M30 30L50 50M50 30L30 50" stroke="currentColor" stroke-width="4" stroke-linecap="round" />
                            </svg>
                        </div>

                        <h1 class="h3 mb-3" style="color: var(--color-primario);">Error al darse de baja</h1>

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
