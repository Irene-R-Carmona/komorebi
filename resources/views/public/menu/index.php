<?php

declare(strict_types=1);

use App\Support\CurrencyFormatting;

/**
 * Vista: Menú
 *
 * Variables:
 * - array $categorias (incluye slug)
 * - array $productos (por category_id)
 * - array $allergens (todos los alérgenos con iconos FontAwesome)
 * - array $excludeAllergens (IDs de alérgenos a filtrar)
 */

// Diccionario solo para ITEMS (no pases)
$diccionarioProductos = [];
foreach ($productos as $catProds) {
    foreach ($catProds as $p) {
        if (($p['product_type'] ?? 'item') !== 'item') {
            continue;
        }

        $diccionarioProductos[(int) $p['id']] = [
            'name' => (string) $p['name'],
            'price' => (float) $p['price'],
        ];
    }
}

$productDictJson = json_encode($diccionarioProductos, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE
    | JSON_UNESCAPED_SLASHES
    | JSON_HEX_TAG
    | JSON_HEX_AMP
    | JSON_HEX_APOS
    | JSON_HEX_QUOT);
if ($productDictJson === false) {
    $productDictJson = '{}';
}

$initialTab = (int) ($categorias[0]['id'] ?? 1);
$allergens ??= [];
$excludeAllergens ??= [];
?>

<?php if (!empty($dbError)) : ?>
    <div role="alert" style="background:var(--color-error,#c0392b);color:#fff;padding:.75rem 1.25rem;text-align:center;font-weight:600;">
        <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
        El menú no está disponible en este momento. Estamos trabajando para restaurar el servicio.
    </div>
<?php endif; ?>

<section class="seccion seccion--activa">
    <script src="/js/sections/menu.js?v=<?= time() ?>"></script>

    <!-- Page data container: evitamos inline scripts para respetar CSP -->
    <div id="komorebi-page-meta" data-product-dict='<?= htmlspecialchars($productDictJson, ENT_QUOTES, 'UTF-8') ?>' style="display:none;"></div>

    <div class="seccion__container"
        x-data='menuApp(<?= $initialTab ?>)'
        x-init="excludedAllergens = <?= json_encode($excludeAllergens) ?>">

        <!-- HEADER -->
        <header class="menu-header-center">
            <h2 class="seccion__titulo">Carta & Delicias</h2>
            <p class="seccion__subtitulo">Sabores tradicionales con un toque animal</p>

            <div class="menu-search-wrapper">
                <label for="menu-busqueda" class="visually-hidden">Buscar productos en el menú</label>
                <input type="search"
                    id="menu-busqueda"
                    x-model="search"
                    placeholder="Buscar en el menú..."
                    class="menu-search-input"
                    autocomplete="off">
            </div>

            <!-- Filtro de Tipos de Café -->
            <?php if (!empty($cafeTypes)): ?>
                <div class="cafe-type-filter-container">
                    <p class="filter-label">Disponible en:</p>
                    <div class="cafe-type-pills">
                        <button @click="selectedCafeType = null"
                            type="button"
                            class="cafe-type-pill"
                            :class="{ 'cafe-type-pill--active': selectedCafeType === null }">
                            Todos los cafés
                        </button>
                        <?php foreach ($cafeTypes as $type): ?>
                            <button @click="selectedCafeType = '<?= $type['value'] ?>'"
                                type="button"
                                class="cafe-type-pill"
                                :class="{ 'cafe-type-pill--active': selectedCafeType === '<?= $type['value'] ?>' }">
                                <i class="<?= e($type['icon']) ?>" aria-hidden="true"></i> <?= e($type['label']) ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Filtro de Alérgenos -->
            <?php if (!empty($allergens)): ?>
                <div class="allergen-filter-container">
                    <button @click="showAllergenFilter = !showAllergenFilter"
                        type="button"
                        class="allergen-filter-toggle">
                        <i class="bi bi-funnel me-2"></i>
                        Filtrar por alérgenos
                        <span x-show="excludedAllergens.length > 0"
                            class="badge bg-danger ms-2"
                            x-text="excludedAllergens.length"></span>
                    </button>

                    <div class="allergen-filter-panel"
                        x-show="showAllergenFilter"
                        x-transition.opacity.duration.300ms
                        style="display: none;">
                        <div class="allergen-filter-header">
                            <h4>Excluir productos con:</h4>
                            <button @click="clearAllergenFilters()" type="button" class="btn-link">
                                Limpiar filtros
                            </button>
                        </div>

                        <div class="allergen-grid">
                            <?php foreach ($allergens as $allergen): ?>
                                <label class="allergen-filter-item">
                                    <input type="checkbox"
                                        value="<?= $allergen['id'] ?>"
                                        x-model="excludedAllergens"
                                        @change="applyAllergenFilter()">
                                    <span class="allergen-filter-label">
                                        <span><?= e($allergen['name']) ?></span>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>

                        <?php if (!empty($excludeAllergens)): ?>
                            <div class="allergen-filter-active">
                                <span>Filtrando <?= count($excludeAllergens) ?> alérgeno(s)</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

        </header>

        <!-- TABS -->
        <div class="menu__tabs">
            <?php foreach ($categorias as $cat): ?>
                <?php $catId = (int) $cat['id']; ?>
                <button class="menu__tab"
                    :class="{ 'menu__tab--activo': activeTab === <?= $catId ?> }"
                    @click="setTab(<?= $catId ?>)"
                    type="button">
                    <?= ucfirst((string) $cat['name']) ?>
                </button>
            <?php endforeach; ?>
        </div>

        <!-- GRID -->
        <div class="menu__container" aria-live="polite" aria-atomic="false">
            <?php foreach ($categorias as $cat): ?>
                <?php
                $catId = (int) $cat['id'];
                $catSlug = (string) ($cat['slug'] ?? '');
                $isExperiencias = ($catSlug === 'experiencias');

                // Seleccionar productos o pases según categoría
                if ($isExperiencias) {
                    // Mostrar SOLO pases de esta categoría
                    $prods = [];
                    foreach ($pases as $pase) {
                        if ((int) $pase['category_id'] === $catId) {
                            $prods[] = $pase;
                        }
                    }
                } else {
                    // Mostrar SOLO items de esta categoría
                    $prods = $productos[$catId] ?? [];
                }
                ?>

                <div class="menu__grid"
                    x-show="activeTab === <?= $catId ?>"
                    x-transition.opacity.duration.300ms
                    x-ref="grid<?= $catId ?>">

                    <?php if (empty($prods)): ?>
                        <div class="menu-empty-msg">
                            <p>Próximamente nuevos productos en esta sección. <i class="bi bi-cup" aria-hidden="true"></i></p>
                        </div>
                    <?php else: ?>

                        <?php foreach ($prods as $prod): ?>
                            <?php
                            $prodId = (int) $prod['id'];
                            $img = $prod['image_url'] ?? '/images/ui/placeholder.jpg';

                            // Decodificar target_cafe_types para filtrado
                            // Ahora viene como array PHP (decodificado en MenuService)
                            $targetCafeTypes = $prod['target_cafe_types'] ?? [];

                            // Validar que solo contenga valores válidos: lounge, playroom, farm, zen
                            $validTypes = ['lounge', 'playroom', 'farm', 'zen'];
                            $cafeTypesArray = is_array($targetCafeTypes)
                                ? array_filter($targetCafeTypes, fn($t) => in_array($t, $validTypes, true))
                                : [];

                            // Si está vacío = disponible en todos los cafés (no poner atributo)
                            $cafeTypesAttr = empty($cafeTypesArray) ? '' : implode(',', $cafeTypesArray);
                            ?>

                            <?php if ($isExperiencias): ?>
                                <!-- EXPERIENCIAS: CTA a Reservas (no carrito) -->
                                <article class="producto-card"
                                    data-name="<?= htmlspecialchars($prod['name'], ENT_QUOTES, 'UTF-8') ?>"
                                    data-desc="<?= htmlspecialchars($prod['description'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                    <?php if ($cafeTypesAttr): ?>data-cafe-types="<?= htmlspecialchars($cafeTypesAttr, ENT_QUOTES, 'UTF-8') ?>" <?php endif; ?>
                                    x-show="matchesNode($el)">

                                    <div class="producto-card__img-container">
                                        <img src="<?= $img ?>"
                                            alt="<?= $prod['name'] ?>"
                                            class="producto-card__img"
                                            width="280"
                                            height="210"
                                            loading="lazy"
                                            @error="$event.target.src='/images/ui/placeholder.svg'">

                                        <div class="producto-card__badges">
                                            <span class="badge-mini badge-popular">Pase</span>
                                            <?php if (!empty($prod['duration_minutes'])): ?>
                                                <span class="badge-mini"><?= (int) $prod['duration_minutes'] ?>m</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="producto-card__body">
                                        <span class="producto-card__jp"><?= $prod['japanese_name'] ?? '' ?></span>
                                        <h3 class="producto-card__titulo"><?= $prod['name'] ?></h3>
                                        <p class="producto-card__desc line-clamp-2"><?= $prod['description'] ?></p>

                                        <div class="producto-card__footer">
                                            <div class="producto-info-row">
                                                <span class="producto-card__precio"><?= e(CurrencyFormatting::euro((int) $prod['price'])) ?></span>
                                                <?php if (!empty($prod['min_pax']) || !empty($prod['max_pax'])): ?>
                                                    <span class="badge-mini">
                                                        Pax <?= (int) ($prod['min_pax'] ?? 1) ?><?= !empty($prod['max_pax']) ? ('-' . (int) $prod['max_pax']) : '+' ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>

                                            <a class="btn btn--primario" href="/reservas?pass=<?= $prodId ?>">
                                                Reservar este pase
                                            </a>
                                        </div>
                                    </div>
                                </article>

                            <?php else: ?>
                                <!-- ITEMS: carrito -->
                                <?php if (($prod['product_type'] ?? 'item') !== 'item') {
                                    continue;
                                } ?>

                                <article class="producto-card"
                                    data-name="<?= htmlspecialchars($prod['name'], ENT_QUOTES, 'UTF-8') ?>"
                                    data-desc="<?= htmlspecialchars($prod['description'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                    data-allergens="<?= htmlspecialchars(implode(',', array_map(fn($a) => (string) ($a['id'] ?? ''), $prod['allergens_list'] ?? [])), ENT_QUOTES, 'UTF-8') ?>"
                                    <?php if ($cafeTypesAttr): ?>data-cafe-types="<?= htmlspecialchars($cafeTypesAttr, ENT_QUOTES, 'UTF-8') ?>" <?php endif; ?>
                                    :class="{ 'producto-card--active': getQty(<?= $prodId ?>) > 0 }"
                                    x-show="matchesNode($el)">

                                    <div class="producto-card__img-container">
                                        <img src="<?= $img ?>"
                                            alt="<?= $prod['name'] ?>"
                                            class="producto-card__img"
                                            width="280"
                                            height="210"
                                            loading="lazy"
                                            @error="$event.target.src='/images/ui/placeholder.svg'">

                                        <div class="producto-card__badges">
                                            <?php if ($prod['attrs']['popular'] ?? false): ?>
                                                <span class="badge-mini badge-popular">Popular</span>
                                            <?php endif; ?>
                                            <?php if ($prod['attrs']['vegano'] ?? false): ?>
                                                <span class="badge-mini badge-vegano">Vegano</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="producto-card__body">
                                        <span class="producto-card__jp"><?= $prod['japanese_name'] ?? '' ?></span>
                                        <h3 class="producto-card__titulo"><?= $prod['name'] ?></h3>
                                        <p class="producto-card__desc line-clamp-2"><?= $prod['description'] ?></p>

                                        <div class="producto-card__footer">
                                            <div class="producto-info-row">
                                                <span class="producto-card__precio"><?= e(CurrencyFormatting::euro((int) $prod['price'])) ?></span>

                                                <?php if (!empty($prod['allergens_list'])): ?>
                                                    <div class="producto-card__alergenos">
                                                        <?php foreach ($prod['allergens_list'] as $allergen): ?>
                                                            <span class="allergen-badge" title="<?= e($allergen['name']) ?>">
                                                                <?php
                                                                $iconVal = (string) ($allergen['icon'] ?? '');

                                                                // Intentar resolver una imagen PNG en /images/allergens
                                                                $assetSrc = null;
                                                                $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? (__DIR__ . '/../../../public');

                                                                $candidates = [];
                                                                $clean = trim($iconVal);
                                                                if ($clean !== '') {
                                                                    $candidates[] = $clean;
                                                                    $candidates[] = strtolower($clean);
                                                                    $candidates[] = strtoupper($clean);
                                                                    $candidates[] = preg_replace('/\.[a-z0-9]+$/i', '', $clean); // sin extensión
                                                                }
                                                                $slugName = preg_replace('/[^a-z0-9]+/', '-', strtolower($allergen['name'] ?? ''));
                                                                $candidates[] = $slugName;

                                                                foreach ($candidates as $cand) {
                                                                    if (!$cand) {
                                                                        continue;
                                                                    }
                                                                    $rel = '/images/alergenos/' . $cand . '.png';
                                                                    $full = $docRoot . $rel;
                                                                    if (file_exists($full)) {
                                                                        $assetSrc = $rel;
                                                                        break;
                                                                    }
                                                                    // comprobar mayúsculas/extensiones alternativas
                                                                    $rel2 = '/images/alergenos/' . $cand . '.PNG';
                                                                    if (file_exists($docRoot . $rel2)) {
                                                                        $assetSrc = $rel2;
                                                                        break;
                                                                    }
                                                                }

                                                                // Si no se encontró exactamente, intentar búsqueda flexible en el directorio
                                                                if (!$assetSrc && is_dir($docRoot . '/images/alergenos')) {
                                                                    $normalize = function (string $s): string {
                                                                        // eliminar acentos y caracteres no alfanum, minúsculas
                                                                        $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s;
                                                                        $s = strtolower($s);
                                                                        $s = preg_replace('/[^a-z0-9]+/', '', $s);

                                                                        return $s;
                                                                    };

                                                                    $needle1 = $normalize($iconVal);
                                                                    $needle2 = $normalize($allergen['name'] ?? '');

                                                                    $files = glob($docRoot . '/images/alergenos/*.*');
                                                                    foreach ($files as $file) {
                                                                        $base = basename($file);
                                                                        $nameOnly = pathinfo($base, PATHINFO_FILENAME);
                                                                        $norm = $normalize($nameOnly);
                                                                        if ($needle1 !== '') {
                                                                            if ($norm === $needle1 || strpos($norm, $needle1) === 0 || substr($norm, -strlen($needle1)) === $needle1) {
                                                                                $assetSrc = '/images/alergenos/' . $base;
                                                                                break;
                                                                            }
                                                                        }
                                                                        if ($needle2 !== '') {
                                                                            if ($norm === $needle2 || strpos($norm, $needle2) === 0 || substr($norm, -strlen($needle2)) === $needle2) {
                                                                                $assetSrc = '/images/alergenos/' . $base;
                                                                                break;
                                                                            }
                                                                        }
                                                                    }
                                                                }
                                                                ?>

                                                                <?php if ($assetSrc): ?>
                                                                    <img src="<?= e($assetSrc) ?>" alt="<?= e($allergen['name']) ?>" class="allergen-img" loading="lazy" width="24" height="24" @error="$event.target.style.display='none'">
                                                                <?php else:
                                                                    // Fallback: si es clase de icon-font renderizar <i>, si no mostrar código textual
                                                                    $isFontClass = (strpos($iconVal, 'bi-') === 0) || (strpos($iconVal, 'fa-') === 0) || (strpos($iconVal, ' ') !== false);
                                                                ?>
                                                                    <?php if ($isFontClass): ?>
                                                                        <i class="<?= e($iconVal) ?>"></i>
                                                                    <?php else: ?>
                                                                        <span class="allergen-code" aria-hidden="true"><?= htmlspecialchars($iconVal, ENT_QUOTES, 'UTF-8') ?></span>
                                                                    <?php endif; ?>
                                                                <?php endif; ?>
                                                            </span>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>

                                            <div class="qty-selector">
                                                <button class="qty-btn"
                                                    type="button"
                                                    @click="updateQty(<?= $prodId ?>, -1)"
                                                    :disabled="getQty(<?= $prodId ?>) <= 0">-
                                                </button>

                                                <span class="qty-val" x-text="getQty(<?= $prodId ?>)"></span>

                                                <button class="qty-btn"
                                                    type="button"
                                                    @click="updateQty(<?= $prodId ?>, 1)">+
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </article>
                            <?php endif; ?>

                        <?php endforeach; ?>

                        <div class="menu-empty-msg"
                            x-show="(search !== '' || selectedCafeType !== null || excludedAllergens.length > 0) && visibleCount($refs.grid<?= $catId ?>) === 0"
                            style="display:none;">
                            <p x-show="search !== ''" x-cloak>No se encontraron productos con "<span x-text="search"></span>" en esta categoría.</p>
                            <p x-show="selectedCafeType !== null && search === '' && excludedAllergens.length === 0" x-cloak>No hay productos disponibles para este tipo de café en esta categoría.</p>
                            <p x-show="excludedAllergens.length > 0 && search === ''" x-cloak>No hay productos sin los alérgenos seleccionados en esta categoría.</p>
                        </div>

                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- SIMULADOR DE PRECIO -->
        <div class="comanda-bar"
            :class="{ 'comanda-bar--visible': simulatorTotalQty() > 0 }"
            style="cursor:default;">

            <div class="comanda-info">
                <span class="comanda-total"><span x-text="(simulatorTotal / 100).toFixed(2).replace('.', ',') + ' €'"></span></span>
                <span class="comanda-items">Estimación de precio (<span x-text="simulatorTotalQty()"></span> items)</span>
            </div>

            <button class="comanda-btn"
                type="button"
                @click.stop="simulatorItems = {}; simulatorTotal = 0;">
                Limpiar
            </button>
        </div>

    </div>
</section>
