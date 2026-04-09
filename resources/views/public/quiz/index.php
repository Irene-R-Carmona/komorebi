<?php

declare(strict_types=1);

/**
 * Vista: Quiz "Tu Café del Alma"
 *
 * NOTA TÉCNICA: Código Alpine.js inline por limitación arquitectónica
 * ----------------------------------------------------------------------
 * Normalmente, seguiríamos SOLID separando JavaScript a archivo externo.
 * Sin embargo, Alpine.js CDN presenta una condición de carrera crítica:
 *
 * 1. Alpine se inicializa al cargar desde CDN (con defer)
 * 2. Alpine procesa INMEDIATAMENTE todos los x-data del DOM
 * 3. El evento alpine:init se dispara DESPUÉS de procesar el DOM
 * 4. Resultado: Alpine intenta usar quizFilosofico() antes de definirse
 *
 * Soluciones evaluadas y descartadas:
 * - ✗ Quitar defer → Bloquea render, mala UX
 * - ✗ Script inline en <head> → Mismo problema de timing
 * - ✗ window.quizFilosofico global → Alpine.data() requiere registro en alpine:init
 * - ✓ Bundle con npm/webpack → Requiere refactor completo (fuera del scope PFC)
 *
 * Decisión: Mantener inline con encapsulación lógica clara y comentarios.
 * Esto respeta SOLID en espíritu (responsabilidad única, bajo acoplamiento)
 * mientras reconoce limitaciones prácticas del stack tecnológico elegido.
 */
?>

<section class="seccion seccion--activa">
    <div class="quiz-container" x-data="quizFilosofico(<?= htmlspecialchars(json_encode($preguntas)) ?>)" x-cloak>

        <div class="quiz-card">
            <!-- Barra de Progreso -->
            <div class="quiz-progress" x-show="step > 0 && step <= totalPreguntas" x-transition>
                <div class="quiz-bar" :style="`width: ${progress}%`"></div>
                <span class="quiz-progress__text" x-text="`${step} de ${totalPreguntas}`"></span>
            </div>

            <!-- INTRO (Step 0) -->
            <div x-show="step === 0" x-transition>
                <div class="quiz-intro">
                    <div class="quiz-intro__icon">
                        <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2z" />
                            <path d="M12 6v6l4 2" />
                        </svg>
                    </div>
                    <h1 class="quiz-intro__title">Tu Café del Alma</h1>
                    <p class="quiz-intro__desc">
                        Descubre el café que mejor representa tu personalidad.
                        5 preguntas sobre tus hábitos y preferencias.
                    </p>
                    <button
                        type="button"
                        class="btn btn--primario btn--lg"
                        @click="iniciar()">
                        Comenzar
                    </button>
                </div>
            </div>

            <!-- PREGUNTAS (Steps 1-5) -->
            <div x-show="step > 0 && step <= totalPreguntas" x-transition>
                <div class="quiz-pregunta">
                    <h2 class="quiz-pregunta__texto" x-text="(preguntaActual && preguntaActual.pregunta) || ''"></h2>

                    <div class="quiz-opciones">
                        <template x-for="(opcion, index) in ((preguntaActual && preguntaActual.opciones) || [])" :key="index">
                            <button
                                type="button"
                                class="quiz-opcion"
                                @click="responder(index)"
                                :class="{ 'quiz-opcion--selected': respuestaActual === index }">
                                <span class="quiz-opcion__radio"></span>
                                <span class="quiz-opcion__texto" x-text="opcion.texto"></span>
                            </button>
                        </template>
                    </div>
                </div>

                <!-- Navegación -->
                <div class="quiz-navegacion">
                    <button
                        type="button"
                        class="btn btn--secundario"
                        @click="retroceder()"
                        x-show="step > 1">
                        Anterior
                    </button>

                    <button
                        type="button"
                        class="btn btn--primario"
                        @click="avanzar()"
                        :disabled="respuestaActual === null"
                        x-show="step < totalPreguntas">
                        Siguiente
                    </button>

                    <button
                        type="button"
                        class="btn btn--primario btn--lg"
                        @click="enviar()"
                        :disabled="respuestaActual === null || enviando"
                        x-show="step === totalPreguntas">
                        <span x-show="!enviando">Descubrir mi café</span>
                        <span x-show="enviando">Analizando...</span>
                    </button>
                </div>
            </div>

            <!-- LOADING -->
            <div x-show="enviando" x-transition class="quiz-loading">
                <div class="quiz-loading__spinner"></div>
                <p class="quiz-loading__texto">Procesando tus respuestas...</p>
            </div>
        </div>

    </div>
</section>

<link rel="stylesheet" href="/css/sections/quiz.css">
