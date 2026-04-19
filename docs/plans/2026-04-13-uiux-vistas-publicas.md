# Plan: UI/UX — Vistas Públicas (FASE 3)

**Fecha:** 13 de abril de 2026
**Estado:** 🟢 Implementación completa — todas las tareas ✅; pendiente verificación
**Dependencias:** Ninguna (independiente)
**Rama sugerida:** `feature/uiux-public-views`

---

## Contexto

El sistema de diseño está sólido (design tokens, dark mode, Alpine.js). Esta fase
corrige inconsistencias concretas: CLS por ausencia de `width`/`height` en imágenes,
ausencia de dark mode filter en imágenes, ausencia de skeleton loaders, empty states
incompletos, y utilidades tipográficas faltantes.

**LCP de home.php:** El hero de `home.php` NO tiene `<img>` — es texto + badges SVG.
`fetchpriority="high"` no aplica. Este ítem se elimina del scope de FASE 3.

---

## Tareas

### TASK 1 — Dark mode: filtro de imágenes

**Archivo:** `public/css/global.css`

**Problema:** En modo oscuro, las imágenes mantienen su brillo original, creando
contraste excesivo sobre fondos oscuros.

**Dónde añadir:** Después del bloque `[data-tema="oscuro"] body { ... }` (línea ~135).

```css
/* Imágenes atenuadas en dark mode para reducir contraste
   Excluir con data-no-filter en imágenes decorativas que deben verse al 100% */
[data-tema="oscuro"] img:not([data-no-filter]) {
  filter: brightness(0.82) saturate(0.9);
  transition: filter var(--transicion);
}
```

**Verificación:** Activar dark mode en el navegador, visitar `/cafes`. Las imágenes de
cafés deben verse ligeramente más tenues.

---

### TASK 2 — Tipografía: `text-wrap` y `line-clamp`

**Archivo:** `public/css/global.css`

**Dónde añadir:** Al final del bloque `03. TIPOGRAFÍA` (después de `h3 { font-size... }`).

```css
h1,
h2 {
  text-wrap: balance;
}

/* Utilidades de recorte de texto multipanel */
.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.line-clamp-3 {
  display: -webkit-box;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
```

**Nota:** `.visually-hidden` ya está siendo usado en vistas (cafes/index.php, menu/index.php).
Si no está definido, añadir también:

```css
.visually-hidden {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border-width: 0;
}
```

---

### TASK 3 — Catálogo de cafés: imágenes con dimensiones explícitas

**Archivo:** `resources/views/public/cafes/index.php`

**Problema:** `<img>` sin `width`/`height` → layout shift (CLS > 0) mientras carga.

**Cambio en línea ~91** (imagen de card):

```html
<!-- ANTES -->
<img src="..."
    alt="..."
    class="card__img"
    loading="lazy"
    onerror="...">

<!-- DESPUÉS -->
<img src="..."
    alt="..."
    class="card__img"
    width="400"
    height="266"
    loading="lazy"
    onerror="...">
```

**Nota:** El ratio 400×266 ≈ 3:2 es el estándar visual para las cards de cafés.
El CSS ya usa `object-fit: cover` por lo que las dimensiones son solo hints para el browser.

---

### TASK 4 — Catálogo de cafés: line-clamp en descripción

**Archivo:** `resources/views/public/cafes/index.php`

**Problema:** Descripciones largas rompen el layout del grid (cards de diferente altura).

**Cambio en línea ~113** (párrafo descripción):

```html
<!-- ANTES -->
<p class="card__descripcion"><?= e($cafe['description']) ?></p>

<!-- DESPUÉS -->
<p class="card__descripcion line-clamp-3"><?= e($cafe['description']) ?></p>
```

---

### TASK 5 — Catálogo de cafés: skeleton loader

**Archivo:** `resources/views/public/cafes/index.php`

**Problema:** Con `x-cloak` en el div de Alpine, el catálogo es invisible hasta que
Alpine inicializa. En conexiones lentas esto produce un "blank flash" de varios segundos.

**Estrategia:** Skeleton PHP estático fuera del `x-cloak`, eliminado vía `x-init`.

**Cambio en línea ~9** — añadir ANTES del div `x-data`:

```html
<!-- Skeleton estático, visible hasta que Alpine inicializa (sin JS dependencia) -->
<div id="catalogo-skeleton" class="catalogo__grid" aria-hidden="true">
    <?php for ($i = 0; $i < 6; $i++): ?>
        <div class="skeleton-card">
            <div class="skeleton skeleton-image skeleton-image--4-3"></div>
            <div style="padding: 1rem; display:flex; flex-direction:column; gap:0.5rem;">
                <div class="skeleton skeleton-text skeleton-text--heading"></div>
                <div class="skeleton skeleton-text skeleton-text--sm"></div>
                <div class="skeleton skeleton-text"></div>
                <div class="skeleton skeleton-text skeleton-text--sm"></div>
            </div>
        </div>
    <?php endfor; ?>
</div>
```

**Cambio en el div `x-data`** — añadir `x-init` para eliminar skeleton:

```html
<!-- ANTES -->
<div class="seccion__container" x-data="catalogoApp(...)" x-cloak>

<!-- DESPUÉS -->
<div class="seccion__container"
    x-data="catalogoApp(...)"
    x-init="document.getElementById('catalogo-skeleton')?.remove()"
    x-cloak>
```

**Verificación:** Con JS deshabilitado, el skeleton debe ser visible. Con JS habilitado,
desaparece cuando Alpine monta el componente.

---

### TASK 6 — Menú: imágenes con dimensiones explícitas

**Archivo:** `resources/views/public/menu/index.php`

**Problema:** Dos ocurrencias de `<img class="producto-card__img">` sin `width`/`height`.

**Cambio en línea ~229** (product type = experiencias):

```html
<!-- ANTES -->
<img src="<?= $img ?>"
    alt="<?= $prod['name'] ?>"
    class="producto-card__img"
    loading="lazy">

<!-- DESPUÉS -->
<img src="<?= $img ?>"
    alt="<?= $prod['name'] ?>"
    class="producto-card__img"
    width="280"
    height="210"
    loading="lazy">
```

**Cambio en línea ~279** (product type = item — misma estructura):

```html
<img src="<?= $img ?>"
    alt="<?= $prod['name'] ?>"
    class="producto-card__img"
    width="280"
    height="210"
    loading="lazy">
```

**Ratio:** 280×210 = 4:3, estándar para product cards.

---

### TASK 7 — Menú: line-clamp en descripción de productos

**Archivo:** `resources/views/public/menu/index.php`

**Problema:** Descripciones largas crean cards de alturas dispares en el grid.
Hay dos ocurrencias (experiencias + items).

**Cambio línea ~255** (experiencias):

```html
<!-- ANTES -->
<p class="producto-card__desc"><?= $prod['description'] ?></p>

<!-- DESPUÉS -->
<p class="producto-card__desc line-clamp-2"><?= $prod['description'] ?></p>
```

**Cambio línea ~305** (items):

```html
<p class="producto-card__desc line-clamp-2"><?= $prod['description'] ?></p>
```

---

### TASK 8 — Menú: empty state para filtro de tipo de café

**Archivo:** `resources/views/public/menu/index.php`

**Problema:** Cuando `selectedCafeType !== null` y ningún producto de la categoría
activa coincide con ese tipo, la grid queda visualmente vacía sin mensaje de usuario.
El mensaje existente solo cubre búsqueda por texto.

**Cambio en línea ~416** — actualizar el bloque `menu-empty-msg`:

```html
<!-- ANTES -->
<div class="menu-empty-msg"
    x-show="search !== '' && visibleCount($refs.grid<?= $catId ?>) === 0"
    style="display:none;">
    <p>No se encontraron productos con "<span x-text="search"></span>" en esta categoría.</p>
</div>

<!-- DESPUÉS -->
<div class="menu-empty-msg"
    x-show="(search !== '' || selectedCafeType !== null) && visibleCount($refs.grid<?= $catId ?>) === 0"
    style="display:none;">
    <p x-show="search !== ''" x-cloak>No se encontraron productos con "<span x-text="search"></span>" en esta categoría.</p>
    <p x-show="search === '' && selectedCafeType !== null" x-cloak>
        Esta categoría no tiene productos disponibles para el café seleccionado.
    </p>
</div>
```

---

## Orden de ejecución recomendado

```
TASK 1 (CSS dark mode img)
TASK 2 (CSS tipografía + utilidades)
TASK 3 (cafes img dimensions)
TASK 4 (cafes line-clamp)
TASK 5 (cafes skeleton)
TASK 6 (menu img dimensions)
TASK 7 (menu line-clamp)
TASK 8 (menu empty state)
```

## Verificación final

```bash
# 1. Análisis estático
docker compose exec app php vendor/bin/phpstan analyse --no-progress

# 2. Suite de tests (unit + integración)
docker compose exec app php vendor/bin/phpunit --testdox

# 3. Visual: abrir /cafes y /menu en dark mode
# 4. Visual: buscar algo inexistente en menú y verificar empty state
# 5. Visual: activar filtro de tipo de café sin resultados
```
