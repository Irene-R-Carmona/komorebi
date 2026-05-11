<?php

declare(strict_types=1);

/**
 * Detalle de un animal disponible para adopción + formulario de solicitud.
 *
 * Variables disponibles:
 *  - $animal:            array<string, mixed>  Datos del animal (v_adoptable_animals)
 *  - $already_requested: bool                  El usuario ya tiene solicitud pendiente
 *  - $is_logged_in:      bool                  Sesión iniciada
 *  - $csrf_token:        string                Token CSRF para el formulario
 */
?>

<section class="seccion seccion--activa">
    <div class="seccion__container seccion__container--estrecha">

        <a href="/adopciones" class="adopcion-detalle__volver">
            <i class="bi bi-arrow-left" aria-hidden="true"></i> Volver a adopciones
        </a>

        <article class="adopcion-detalle" aria-label="Ficha de <?= htmlspecialchars((string) $animal['name'], ENT_QUOTES, 'UTF-8') ?>">

            <!-- Foto -->
            <div class="adopcion-detalle__foto-wrapper">
                <?php if (!empty($animal['photo_url'])): ?>
                    <img
                        class="adopcion-detalle__foto"
                        src="<?= htmlspecialchars((string) $animal['photo_url'], ENT_QUOTES, 'UTF-8') ?>"
                        alt="Foto de <?= htmlspecialchars((string) $animal['name'], ENT_QUOTES, 'UTF-8') ?>"
                        width="600"
                        height="450">
                <?php else: ?>
                    <div class="adopcion-detalle__foto adopcion-detalle__foto--placeholder" aria-hidden="true">
                        <i class="bi bi-camera" style="font-size: 4rem;"></i>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Info -->
            <div class="adopcion-detalle__info">
                <h1 class="adopcion-detalle__nombre"><?= htmlspecialchars((string) $animal['name'], ENT_QUOTES, 'UTF-8') ?></h1>

                <dl class="adopcion-detalle__ficha">
                    <div class="adopcion-detalle__ficha-fila">
                        <dt>Especie</dt>
                        <dd><?= htmlspecialchars((string) $animal['species_type'], ENT_QUOTES, 'UTF-8') ?></dd>
                    </div>
                    <?php if (!empty($animal['cafe_name'])): ?>
                        <div class="adopcion-detalle__ficha-fila">
                            <dt>Café</dt>
                            <dd><?= htmlspecialchars((string) $animal['cafe_name'], ENT_QUOTES, 'UTF-8') ?></dd>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($animal['age_years'])): ?>
                        <div class="adopcion-detalle__ficha-fila">
                            <dt>Edad aproximada</dt>
                            <dd><?= (int) $animal['age_years'] ?> <?= (int) $animal['age_years'] === 1 ? 'año' : 'años' ?></dd>
                        </div>
                    <?php endif; ?>
                </dl>

                <?php if (!empty($animal['description'])): ?>
                    <p class="adopcion-detalle__descripcion">
                        <?= htmlspecialchars((string) $animal['description'], ENT_QUOTES, 'UTF-8') ?>
                    </p>
                <?php endif; ?>

                <!-- Acciones -->
                <div class="adopcion-detalle__acciones">
                    <?php if (!$is_logged_in): ?>
                        <p class="adopcion-detalle__aviso">
                            <a href="/login">Inicia sesión</a> para solicitar la adopción.
                        </p>
                    <?php elseif ($already_requested): ?>
                        <p class="adopcion-detalle__aviso adopcion-detalle__aviso--ok">
                            <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
                            Ya tienes una solicitud pendiente para este animal.
                            Nos pondremos en contacto contigo pronto.
                        </p>
                    <?php else: ?>
                        <form
                            method="POST"
                            action="/adopciones/<?= (int) $animal['id'] ?>/solicitar"
                            class="adopcion-detalle__form"
                            aria-label="Formulario de solicitud de adopción">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">

                            <div class="form-group">
                                <label for="message" class="form-label">
                                    ¿Por qué te gustaría adoptar a <?= htmlspecialchars((string) $animal['name'], ENT_QUOTES, 'UTF-8') ?>?
                                    <span class="form-label__opcional">(opcional)</span>
                                </label>
                                <textarea
                                    id="message"
                                    name="message"
                                    class="form-control"
                                    rows="4"
                                    maxlength="1000"
                                    placeholder="Cuéntanos sobre tu hogar, experiencia con animales, estilo de vida..."
                                    aria-describedby="message-hint"></textarea>
                                <p id="message-hint" class="form-hint">Máximo 1000 caracteres. Esta información ayuda al keeper a tomar la mejor decisión.</p>
                            </div>

                            <button type="submit" class="btn btn--primario">
                                <i class="bi bi-heart" aria-hidden="true"></i>
                                Enviar solicitud de adopción
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </article>
    </div>
</section>
