<section class="seccion seccion--activa">
    <?php
    $cafesSimple = array_map(fn($c) => [
        'tipo' => $c['animal_type'],
        'nombre' => $c['name'],
        'ubicacion' => $c['location'],
    ], $cafes);
    ?>
    <div class="seccion__container" x-data="catalogoApp(<?= json_encode($favoritos, JSON_THROW_ON_ERROR) ?>, <?= json_encode($cafesSimple, JSON_THROW_ON_ERROR) ?>)" x-cloak>
        <header class="seccion__header">
            <h2 class="seccion__titulo">Nuestros Cafés</h2>
            <p class="seccion__subtitulo">Encuentra tu lugar perfecto para relajarte</p>

            <p class="seccion__hora">
                <i class="bi bi-clock seccion__hora-icon" aria-hidden="true"></i>
                Hora local: <span><?= date('H:i') ?></span>
            </p>
        </header>

        <!-- FILTROS -->
        <div class="filtros" role="search">
            <div class="filtros__busqueda">
                <label for="busqueda-cafes" class="visually-hidden">Buscar café por nombre o ubicación</label>
                <input id="busqueda-cafes"
                    class="filtros__input"
                    x-model="busqueda"
                    @input="watchBusqueda()"
                    type="search"
                    placeholder="Buscar café por nombre o ubicación..."
                    autocomplete="off">
            </div>

            <div class="filtros__tipos">
                <button class="filtros__btn" :class="{ 'filtros__btn--activo': filtroTipo === 'todos' }"
                    @click="setFiltro('todos')">Todos
                </button>
                <button class="filtros__btn" :class="{ 'filtros__btn--activo': filtroTipo === 'gato' }"
                    @click="setFiltro('gato')">Gatos
                </button>
                <button class="filtros__btn" :class="{ 'filtros__btn--activo': filtroTipo === 'buho' }"
                    @click="setFiltro('buho')">Búhos
                </button>
                <button class="filtros__btn" :class="{ 'filtros__btn--activo': filtroTipo === 'conejo' }"
                    @click="setFiltro('conejo')">Conejos
                </button>
                <button class="filtros__btn" :class="{ 'filtros__btn--activo': filtroTipo === 'erizo' }"
                    @click="setFiltro('erizo')">Erizos
                </button>
                <button class="filtros__btn" :class="{ 'filtros__btn--activo': filtroTipo === 'capybara' }"
                    @click="setFiltro('capybara')">Capibaras
                </button>
            </div>

            <!-- Indicador y control de filtros guardados -->
            <div class="filtros__saved" x-show="filtrosGuardados" x-transition>
                <span class="filtros__saved-icon">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M15 2H5C4.447 2 4 2.447 4 3V17C4 17.553 4.447 18 5 18H15C15.553 18 16 17.553 16 17V3C16 2.447 15.553 2 15 2Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                        <path d="M12 2V6H8V2M7 11H13M7 14H10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                    </svg>
                </span>
                <span class="filtros__saved-text">Tus filtros se guardan automáticamente</span>
                <button class="filtros__saved-clear" @click="limpiarFiltrosGuardados()" type="button" title="Eliminar filtros guardados">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M2 4H14M6 2H10M6 7V12M10 7V12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                        <path d="M3 4L4 14H12L13 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    Eliminar filtros guardados
                </button>
            </div>
        </div>

        <!-- GRID DE CAFÉS -->
        <div class="catalogo__grid" aria-live="polite" aria-atomic="false">
            <?php foreach ($cafes as $cafe):
                // Objeto JSON mínimo para el filtro JS
                $cafeJs = json_encode([
                    'tipo' => $cafe['animal_type'],
                    'nombre' => $cafe['name'],
                    'ubicacion' => $cafe['location'],
                ], JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT);
            ?>
                <!-- Card -->
                <article class="card"
                    x-show="filtrar(<?= $cafeJs ?>)"
                    x-transition.duration.300ms>

                    <div class="card__imagen">
                        <img src="<?= e($cafe['image_url'] ?? '/images/ui/placeholder-cafe.svg') ?>"
                            alt="<?= e($cafe['name']) ?>"
                            class="card__img"
                            loading="lazy"
                            onerror="this.onerror=null; this.src='/images/ui/placeholder-cafe.svg'">
                        <div class="card__tipo-badge"><?= ucfirst(e($cafe['animal_type'])) ?></div>
                    </div>

                    <div class="card__contenido">
                        <div class="card__header">
                            <div class="card__titulos">
                                <h3 class="card__nombre"><?= e($cafe['name']) ?></h3>
                                <span class="card__nombre-jp"><?= e($cafe['japanese_name']) ?></span>
                            </div>
                        </div>

                        <div class="card__ubicacion"><?= e($cafe['location']) ?></div>

                        <div class="card__horario">
                            <i class="bi bi-clock card__horario-icon" aria-hidden="true"></i>
                            <?= substr($cafe['opening_time'], 0, 5) ?> - <?= substr($cafe['closing_time'], 0, 5) ?>
                        </div>

                        <p class="card__descripcion"><?= e($cafe['description']) ?></p>

                        <div class="card__footer">
                            <div class="card__rating">
                                <span class="star--filled" aria-hidden="true">★</span>
                                <span class="visually-hidden">Valoración:</span> <?= $cafe['rating_avg'] ?? '—' ?>
                            </div>
                        </div>

                        <div class="card__acciones">
                            <a href="/cafes/<?= $cafe['slug'] ?>" class="card__btn card__btn--animales">
                                Ver Animales
                            </a>
                            <a href="/reservas?cafe=<?= $cafe['id'] ?>" class="card__btn card__btn--reservar">
                                Reservar
                            </a>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <!-- Mensaje Vacío -->
        <div class="empty-state"
            x-show="!hayResultados"
            x-transition
            style="display: none;">
            <i class="bi bi-cup-hot empty-state__icon" aria-hidden="true"></i>
            <h3 class="empty-state__title">Sin coincidencias</h3>
            <p class="empty-state__body">Intenta buscar con otro nombre o cambia el tipo de animal seleccionado.</p>
            <button @click="limpiarFiltros()" class="btn-komorebi btn-komorebi--secondary" type="button">Limpiar filtros</button>
        </div>
    </div>
</section>
