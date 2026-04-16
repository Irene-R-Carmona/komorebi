# Sprint QoL Holístico — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Corregir 5 bugs críticos visibles, limpiar 3 áreas de deuda técnica DX, mejorar 1 KPI operativo del staff y añadir navegación a la página 404.

**Architecture:** El sprint se organiza en 4 olas independientes (bugs, DX, staff, visual). Cada ola puede ejecutarse en cualquier orden tras la Ola 0. Las correcciones son mayoritariamente cirugía de ficheros individuales sin impacto en contratos entre capas.

**Tech Stack:** PHP 8.4, Alpine.js, Bootstrap Icons CDN, PHPUnit, PHPStan level 5, Docker (`make dev`).

**Spec:** `docs/superpowers/specs/2026-04-14-qol-holistic-sprint-design.md`

---

## Mapa de ficheros

| Tarea | Fichero | Acción |
|-------|---------|--------|
| T0.1 | `resources/views/public/quiz/resultado.php` | Modificar — renombrar claves de `$cafeData` |
| T0.2 | `resources/views/components/admin/review-card.php` | Modificar — fix JS injection |
| T0.3 | `resources/views/backoffice/keeper/dashboard.php` | Modificar — fix KPI card labels/values |
| T0.4 | `resources/views/public/loyalty/card.php` | Modificar — eliminar dead code `$tierEmojis` |
| T0.5 | Vistas públicas cafés + keeper animals | Modificar — añadir `onerror` fallback |
| T0.5 | `public/images/ui/placeholder.svg` | Crear — imagen SVG placeholder |
| T1.1 | `app/Http/Controllers/Keeper/AnimalCareController.php` | Modificar — eliminar 8 métodos legacy |
| T1.2 | `app/Services/ContextService.php` + call sites | Modificar — eliminar métodos deprecated |
| T1.2 | `app/Services/NavigationService.php` + call sites | Modificar — eliminar métodos deprecated |
| T1.3 | Tests en `phpstan-baseline.neon` (40+ archivos) | Modificar — estandarizar mock/stub |
| T2.1 | `app/Http/Controllers/Kitchen/KitchenController.php` | Modificar — extraer magic numbers |
| T3.1 | Vista 404 (localizar con grep) | Modificar — añadir enlace Home |

---

## OLA 0 — Bugs Críticos

---

### Tarea 0.1: Quiz — fix claves de `$cafeData` en vista resultado

**Ficheros:**

- Modificar: `resources/views/public/quiz/resultado.php` (bloque `<?php if ($cafeData): ?>`)

La tabla `cafes` tiene columnas `name`, `image_url`, `description`, `location`, `rating_avg`. La vista usa `nombre`, `imagen`, `descripcion`, `ubicacion`, `rating`, que no existen → bloque se renderiza vacío.

- [ ] **Paso 1: Verificar el estado actual del bug**

Arrancar el stack y visitar `/quiz`, completar las 5 preguntas. El resultado debe mostrar el nombre del café en el hero (`$cafe['nombre']` del array PHP, no BD) pero el bloque "Visita tu refugio" debe estar invisible o vacío.

```bash
# En terminal wsHost:
make dev
# Abrir http://localhost/quiz y completar el quiz
```

- [ ] **Paso 2: Aplicar los cambios en la vista**

En `resources/views/public/quiz/resultado.php`, localizar el bloque `<?php if ($cafeData): ?>` (aprox. línea 70) y reemplazar todas las referencias incorrectas:

```php
// ANTES — claves que no existen en la tabla cafes
<?php if (!empty($cafeData['imagen'])): ?>
    <img src="<?= htmlspecialchars($cafeData['imagen']) ?>"
        alt="<?= htmlspecialchars($cafeData['nombre']) ?>"
        class="cafe-destino__imagen">
<?php endif; ?>

<div class="cafe-destino__info">
    <h3><?= htmlspecialchars($cafeData['nombre']) ?></h3>
    <p><?= htmlspecialchars($cafeData['descripcion']) ?></p>

    <div class="cafe-destino__meta">
        <span><i class="bi bi-geo-alt" aria-hidden="true"></i> <?= htmlspecialchars($cafeData['ubicacion'] ?? 'Tokyo, Japón') ?></span>
        <span><i class="bi bi-star-fill" aria-hidden="true" style="color:var(--color-acento)"></i> <span class="visually-hidden">Valoración:</span><?= number_format($cafeData['rating'] ?? 4.5, 1) ?></span>
    </div>
</div>
```

```php
// DESPUÉS — columnas reales de la tabla cafes
<?php if (!empty($cafeData['image_url'])): ?>
    <img src="<?= htmlspecialchars($cafeData['image_url']) ?>"
        alt="<?= htmlspecialchars($cafeData['name']) ?>"
        class="cafe-destino__imagen"
        onerror="this.src='/images/ui/placeholder.svg'; this.onerror=null;">
<?php endif; ?>

<div class="cafe-destino__info">
    <h3><?= htmlspecialchars($cafeData['name']) ?></h3>
    <p><?= htmlspecialchars($cafeData['description']) ?></p>

    <div class="cafe-destino__meta">
        <span><i class="bi bi-geo-alt" aria-hidden="true"></i> <?= htmlspecialchars($cafeData['location'] ?? 'Tokyo, Japón') ?></span>
        <span><i class="bi bi-star-fill" aria-hidden="true" style="color:var(--color-acento)"></i> <span class="visually-hidden">Valoración:</span><?= number_format((float) ($cafeData['rating_avg'] ?? 4.5), 1) ?></span>
    </div>
</div>
```

- [ ] **Paso 3: Verificar en browser**

Completar el quiz de nuevo. El bloque "Visita tu refugio" debe mostrar el nombre real del café (`Neko no Niwa`, `Usagi Yume`, etc.) y su descripción en español. Sin errores en consola.

- [ ] **Paso 4: Commit**

```bash
git add resources/views/public/quiz/resultado.php
git commit -m "fix(quiz): alinear claves cafeData con columnas reales de la tabla cafes"
```

---

### Tarea 0.2: Admin reviews modal — fix JS injection con `json_encode`

**Ficheros:**

- Modificar: `resources/views/components/admin/review-card.php` (línea ~71)

`e($author)` convierte `'` a `&#039;` (HTML). Alpine decodifica HTML entities al evaluar el atributo como expresión JS. Si el autor tiene apostrofe en su nombre (`O'Brien`), el literal JS `'O'Brien'` es sintácticamente incorrecto. La evaluación falla y Alpine asigna el nodo DOM como fallback → `[object HTMLTextAreaElement]`.

- [ ] **Paso 1: Localizar la línea exacta del bug**

```bash
grep -n "openRejectModal" resources/views/components/admin/review-card.php
```

Resultado esperado:

```
71:                @click="openRejectModal({id: <?= (int) $id ?>, author: '<?= e($author) ?>'})"
```

- [ ] **Paso 2: Aplicar el fix**

Reemplazar la línea problemática: sustituir la concatenación con comillas simples y `e()` por `json_encode()` con flags de escape HTML para embebido seguro en atributo HTML.

```php
// ANTES — vulnerable a JS injection si el nombre tiene apostrofe
@click="openRejectModal({id: <?= (int) $id ?>, author: '<?= e($author) ?>'})"
```

```php
// DESPUÉS — json_encode produce string JS con comillas dobles, flags evitan XSS en atributo HTML
@click="openRejectModal({id: <?= (int) $id ?>, author: <?= e(json_encode($author, JSON_HEX_APOS | JSON_HEX_TAG | JSON_HEX_AMP)) ?>})"
```

Los flags usados:

- `JSON_HEX_APOS` → `'` se codifica como `\u0027`
- `JSON_HEX_TAG` → `<>` se codifican
- `JSON_HEX_AMP` → `&` se codifica

`e()` envuelve el resultado para que sea safe en el contexto del atributo HTML.

- [ ] **Paso 3: Verificar en browser**

En `/admin/reviews`, pulsar "Rechazar" en cualquier reseña. El modal debe mostrar el nombre del autor correctamente (no `[object HTMLTextAreaElement]`). Verificar también con una reseña cuyo autor tenga apostrofe en el nombre (crear una de prueba si es necesario).

- [ ] **Paso 4: Commit**

```bash
git add resources/views/components/admin/review-card.php
git commit -m "fix(admin): usar json_encode para pasar author a Alpine, evita JS injection con apostrofe"
```

---

### Tarea 0.3: Keeper dashboard — fix KPIs incorrectos

**Ficheros:**

- Modificar: `resources/views/backoffice/keeper/dashboard.php` (bloque stats-grid, líneas ~65-90)

Problemas:

1. "Animales Activos" muestra `$stats['total_animals']` (todos los no borrados) en lugar de `$stats['healthy']` (solo status=`active`)
2. "Promedio Interacciones" muestra `$stats['avg_interactions']` que `getHealthStatistics()` nunca devuelve → siempre 0
3. Si la tabla `animals` está vacía, todos los KPIs son 0 — esto se debe a falta de seed inicial

- [ ] **Paso 1: Verificar que el seeder de animales ha corrido**

```bash
# Con el stack levantado (make dev):
docker compose exec app php -r "
    require 'vendor/autoload.php';
    \$db = App\Core\Database::getConnection();
    \$stmt = \$db->query('SELECT COUNT(*) as c FROM animals WHERE deleted_at IS NULL');
    echo 'Animales: ' . \$stmt->fetchColumn() . PHP_EOL;
"
```

Si el resultado es `Animales: 0`, ejecutar el seeder:

```bash
make db-seed
```

- [ ] **Paso 2: Corregir los KPI cards en la vista**

```php
// ANTES — card 1 usa total_animals (no filtra por status), card 4 usa avg_interactions (no existe en stats)
<?= View::componentToString('components/admin/stat-card', [
    'icon'    => 'heart-fill',
    'variant' => 'primary',
    'label'   => 'Animales Activos',
    'value'   => $stats['total_animals'] ?? 0,
]) ?>
// ... cards 2 y 3 sin cambio ...
<?= View::componentToString('components/admin/stat-card', [
    'icon'    => 'graph-up',
    'variant' => 'info',
    'label'   => 'Promedio Interacciones',
    'value'   => ($stats['avg_interactions'] ?? 0) . '/día',
]) ?>
```

```php
// DESPUÉS — card 1 usa healthy (status=active), card 4 usa monitoring (status=resting, dato real)
<?= View::componentToString('components/admin/stat-card', [
    'icon'    => 'heart-fill',
    'variant' => 'primary',
    'label'   => 'Animales Activos',
    'value'   => $stats['healthy'] ?? 0,
]) ?>
// ... cards 2 y 3 sin cambio ...
<?= View::componentToString('components/admin/stat-card', [
    'icon'    => 'moon-fill',
    'variant' => 'info',
    'label'   => 'En Descanso',
    'value'   => $stats['monitoring'] ?? 0,
]) ?>
```

- [ ] **Paso 3: Verificar en browser**

Navegar a `/keeper/dashboard`. Los KPIs deben mostrar valores coherentes con la BD (non-zero si el seeder corrió). "Animales Activos" refleja solo animales con `current_status = 'active'`. "En Descanso" refleja `current_status = 'resting'`.

- [ ] **Paso 4: Commit**

```bash
git add resources/views/backoffice/keeper/dashboard.php
git commit -m "fix(keeper): KPI Animales Activos usa stats[healthy], reemplaza avg_interactions por stats[monitoring]"
```

---

### Tarea 0.4: Loyalty card — eliminar dead code `$tierEmojis`

**Ficheros:**

- Modificar: `resources/views/public/loyalty/card.php` (sección de definición de arrays, inicio del fichero)

`$tierEmojis` está definido con clases Bootstrap Icons pero nunca se usa en el template → dead code que puede confundir. Los iconos de tier sí usan `$tierIcons` correctamente.

- [x] **Paso 1: Verificar que Bootstrap Icons cargan correctamente**

Con stack levantado, abrir `/loyalty/card` e inspeccionar los iconos de tier (`bi-award`, `bi-shield-fill`, etc.). Si se ven correctamente, continuar.

Si *no* se ven, verificar en `resources/views/layouts/main.php` que exista la línea:

```html
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
```

Si no existe, añadirla en el `<head>`.

- [x] **Paso 2: Localizar y eliminar `$tierEmojis`**

```bash
grep -n "tierEmojis" resources/views/public/loyalty/card.php
```

Localizar el bloque de PHP donde se define `$tierEmojis` (array con clases BI como valores). Eliminar completamente ese bloque ya que no se usa en ningún lugar del template.

- [x] **Paso 3: Verificar que no hay referencias rotas** ✅

```bash
grep -n "tierEmojis" resources/views/public/loyalty/card.php
# Debe retornar: sin resultados
```

- [x] **Paso 4: Commit** ✅

```bash
git add resources/views/public/loyalty/card.php
git commit -m "fix(loyalty): eliminar array tierEmojis sin uso (dead code)"
```

---

### Tarea 0.5: Imágenes rotas — añadir fallback `onerror` y crear placeholder SVG

**Ficheros:**

- Crear: `public/images/ui/placeholder.svg`
- Modificar: vistas con `<img>` de cafés y animales (ver grep abajo)

Los archivos de imagen no existen en el repo. El fallback SVG evita broken images en cualquier entorno sin assets físicos.

- [x] **Paso 1: Crear el fichero placeholder SVG** ✅

Crear `public/images/ui/placeholder.svg` con el siguiente contenido (placeholder neutro de 400×300 con silueta):

```xml
<svg xmlns="http://www.w3.org/2000/svg" width="400" height="300" viewBox="0 0 400 300">
  <rect width="400" height="300" fill="#f5ede3"/>
  <rect x="80" y="60" width="240" height="180" rx="12" fill="#e8d5c4"/>
  <circle cx="200" cy="130" r="40" fill="#c9a882"/>
  <path d="M140 220 Q200 170 260 220" stroke="#c9a882" stroke-width="3" fill="none"/>
  <text x="200" y="256" text-anchor="middle" font-family="sans-serif" font-size="13" fill="#a07850">Komorebi Café</text>
</svg>
```

- [x] **Paso 2: Localizar todos los `<img>` de cafés sin `onerror`** ✅

```bash
grep -rn "<img" resources/views/public/cafes/ resources/views/public/quiz/ resources/views/backoffice/keeper/ \
  | grep "image_url\|cafe_image\|\$cafe\[" \
  | grep -v "onerror"
```

- [x] **Paso 3: Añadir `onerror` en cada `<img>` identificado** ✅

Para cada `<img src="...">` que muestre imágenes de cafés o animales, añadir el atributo:

```php
// Ejemplo — antes
<img src="<?= e($cafe['image_url'] ?? '') ?>" alt="<?= e($cafe['name']) ?>" class="cafe-card__img">

// Ejemplo — después
<img src="<?= e($cafe['image_url'] ?? '/images/ui/placeholder.svg') ?>"
     alt="<?= e($cafe['name']) ?>"
     class="cafe-card__img"
     onerror="this.src='/images/ui/placeholder.svg'; this.onerror=null;">
```

El mismo patrón se aplica a imágenes de animales:

```php
<img src="<?= e($animal['image_url'] ?? '/images/ui/placeholder.svg') ?>"
     alt="<?= e($animal['name']) ?>"
     onerror="this.src='/images/ui/placeholder.svg'; this.onerror=null;">
```

- [x] **Paso 4: Verificar visualmente con stack levantado** ✅

Abrir `/cafes`. Las cards deben mostrar el placeholder SVG (fondo crema con silueta) en lugar de iconos de imagen rota.

- [x] **Paso 5: Commit** ✅

```bash
git add public/images/ui/placeholder.svg resources/views/
git commit -m "fix(assets): añadir placeholder SVG y onerror fallback en imágenes de cafés y animales"
```

---

## OLA 1 — Salud Técnica (DX)

---

### Tarea 1.1: Eliminar métodos legacy en `AnimalCareController`

**Ficheros:**

- Modificar: `app/Http/Controllers/Keeper/AnimalCareController.php`

Ocho métodos sin rutas activas, marcados `TODO(plan7-cleanup)`:
`logCare`, `updateHealth`, `toggleActive`, `uploadPhoto`, `createIncident`, `resolveIncident`, `update`, `toggle`.

- [ ] **Paso 1: Verificar que ningún método tiene ruta activa**

```bash
grep -n "logCare\|updateHealth\|toggleActive\|uploadPhoto\|createIncident\|resolveIncident\|AnimalCareController@update\|AnimalCareController@toggle" app/routes.php
```

Resultado esperado: sin resultados. Si aparece alguno, DON'T delete ese método.

- [ ] **Paso 2: Verificar que ningún otro fichero referencia estos métodos**

```bash
grep -rn "AnimalCareController" app/ resources/ --include="*.php" | grep -v "AnimalCareController.php"
```

Los únicos resultados deben ser registros en `bootstrap/container.php` o `routes.php` referenciando solo los métodos con rutas vivas. Confirmar antes de continuar.

- [ ] **Paso 3: Eliminar los 8 métodos del controlador**

En `app/Http/Controllers/Keeper/AnimalCareController.php`, eliminar completamente los métodos:

1. `logCare()` (con su bloque docblock y comentario `TODO`)
2. `updateHealth()`
3. `toggleActive()`
4. `uploadPhoto()`
5. `createIncident()`
6. `resolveIncident()`
7. `update()` (alias de `logCare`)
8. `toggle()` (alias de `toggleActive`)

También eliminar los `use` imports que solo sean necesarios para los métodos eliminados (revisar cuáles quedan huérfanos).

- [ ] **Paso 4: Ejecutar tests unitarios**

```bash
make test-unit
```

Resultado esperado: verde. Si hay test que referencia alguno de los métodos eliminados, el test también debe eliminarse (era para código legacy).

- [ ] **Paso 5: Ejecutar PHPStan**

```bash
make phpstan
```

No deben aparecer errores nuevos relacionados con el controlador.

- [ ] **Paso 6: Commit**

```bash
git add app/Http/Controllers/Keeper/AnimalCareController.php
git commit -m "refactor(keeper): eliminar 8 métodos legacy sin ruta activa (plan7-cleanup)"
```

---

### Tarea 1.2: Eliminar métodos `@deprecated` en ContextService y NavigationService

**Ficheros:**

- Modificar: `app/Services/ContextService.php`
- Modificar: `app/Services/NavigationService.php`
- Modificar: todos los call sites encontrados por grep

- [ ] **Paso 1: Localizar todos los métodos deprecated**

```bash
grep -n "@deprecated" app/Services/ContextService.php app/Services/NavigationService.php
```

Anotar los nombres de método exactos.

- [ ] **Paso 2: Encontrar todos los call sites**

```bash
# Sustituir METHOD_NAME por cada nombre encontrado en el paso anterior
grep -rn "ContextService::\|NavigationService::" app/ resources/ --include="*.php" \
  | grep -v "_test\|Test\.php"
```

- [ ] **Paso 3: Para cada call site, migrar a la API de instancia**

La nueva API usa instancias inyectadas (no métodos estáticos). El patrón de migración es:

```php
// ANTES — método estático deprecated
ContextService::getNavigationContext($request);

// DESPUÉS — instancia inyectada (obtenerla del Container o constructor)
$contextService = Container::make(ContextServiceInterface::class);
$contextService->getNavigationContext($request);
```

Si el call site es en un constructor donde se puede inyectar, registrar la dependencia en `bootstrap/container.php` si no existe ya.

- [ ] **Paso 4: Eliminar los métodos `@deprecated` de ambos servicios**

Tras confirmar cero call sites, borrar los métodos marcados con `@deprecated`.

- [ ] **Paso 5: Ejecutar tests y PHPStan**

```bash
make test-unit ; make phpstan
```

Resultado esperado: verde sin nuevos errores.

- [ ] **Paso 6: Commit**

```bash
git add app/Services/ContextService.php app/Services/NavigationService.php
git commit -m "refactor: eliminar métodos @deprecated en ContextService y NavigationService, migrar call sites a instancia"
```

---

### Tarea 1.3: Reducir ruido PHPStan — estandarizar mock/stub en tests

**Ficheros:**

- Modificar: ~40 archivos de test en `tests/Unit/` (ver grep abajo)
- Modificar: `phpstan-baseline.neon` (regenerar tras los fixes)

Regla: `createStub()` se usa para doubles sin expectations. `createMock()` se usa cuando el test llama `.expects()` o `.method()` para verificar llamadas. La mezcla genera 240+ errores PHPStan suprimidos.

- [ ] **Paso 1: Identificar los tests problemáticos**

```bash
grep -rn "createStub\(\|->expects\|->method(" tests/Unit/ --include="*.php" -l
```

Esto lista los archivos que tienen ambas (createStub + expects/method). Esos son los candidatos al fix.

- [ ] **Paso 2: Para cada fichero, aplicar la regla**

Patrón a buscar + reemplazar:

```php
// ANTES — stub usado como mock (incorrecto)
$repo = $this->createStub(ReservationRepositoryInterface::class);
$repo->expects($this->once())->method('findById')->willReturn($reservation);

// DESPUÉS — mock usado con expectations (correcto)
$repo = $this->createMock(ReservationRepositoryInterface::class);
$repo->expects($this->once())->method('findById')->willReturn($reservation);
```

```php
// ANTES — mock donde no hay expectations (innecesario)
$service = $this->createMock(SomeService::class);
// ... (sin calls a expects())

// DESPUÉS — stub es suficiente
$service = $this->createStub(SomeService::class);
```

Aplicar el cambio archivo por archivo. Ejecutar `make test-unit` tras cada bloque de archivos para detectar regresiones.

- [ ] **Paso 3: Regenerar el baseline**

Tras corregir todos los archivos:

```bash
make phpstan 2>&1 | tee /tmp/phpstan-output.txt
# Si hay errores que NO son de mock/stub y son legítimos, actualizar baseline:
docker compose exec app vendor/bin/phpstan analyse --generate-baseline phpstan-baseline.neon
```

- [ ] **Paso 4: Verificar reducción**

```bash
grep -c "message:" phpstan-baseline.neon
```

El número debe ser significativamente menor que el original (~240+). Apuntar el número antes y después en el mensaje de commit.

- [ ] **Paso 5: Commit**

```bash
git add tests/Unit/ phpstan-baseline.neon
git commit -m "fix(tests): estandarizar createMock/createStub, reducir baseline PHPStan de ~240 a N errores"
```

---

## OLA 2 — QoL Operativo Staff

---

### Tarea 2.1: KDS — extraer magic numbers a constantes nombradas

**Ficheros:**

- Modificar: `app/Http/Controllers/Kitchen/KitchenController.php`

Los valores `300`, `900`, `3600` (segundos) controlan el color de alerta de las tarjetas KDS. Están hardcodeados, son ilegibles y difíciles de ajustar por café.

- [ ] **Paso 1: Localizar los magic numbers**

```bash
grep -n "300\|900\|3600" app/Http/Controllers/Kitchen/KitchenController.php
```

- [ ] **Paso 2: Extraer a constantes de clase con nombre semántico**

Añadir al inicio de la clase, justo antes del constructor:

```php
/** Segundos hasta mostrar aviso amarillo en tarjeta KDS */
private const KDS_WARN_SECONDS = 300;  // 5 minutos

/** Segundos hasta mostrar aviso naranja en tarjeta KDS */
private const KDS_LATE_SECONDS = 900;  // 15 minutos

/** Segundos hasta mostrar aviso rojo (crítico) en tarjeta KDS */
private const KDS_CRITICAL_SECONDS = 3600;  // 60 minutos
```

- [ ] **Paso 3: Reemplazar los literales numéricos por las constantes**

Sustituir cada ocurrencia de `300`, `900`, `3600` en la lógica del controlador:

```php
// ANTES
if ($elapsed > 3600) { $cssClass = 'kds-card--late'; }
elseif ($elapsed > 900) { $cssClass = 'kds-card--warn'; }

// DESPUÉS
if ($elapsed > self::KDS_CRITICAL_SECONDS) { $cssClass = 'kds-card--late'; }
elseif ($elapsed > self::KDS_LATE_SECONDS) { $cssClass = 'kds-card--warn'; }
```

- [ ] **Paso 4: Ejecutar tests**

```bash
make test-unit
```

- [ ] **Paso 5: Commit**

```bash
git add app/Http/Controllers/Kitchen/KitchenController.php
git commit -m "refactor(kds): extraer thresholds de tiempo a constantes nombradas (KDS_WARN/LATE/CRITICAL_SECONDS)"
```

---

## OLA 3 — Coherencia Visual

---

### Tarea 3.1: Página 404 — añadir navegación de regreso

**Ficheros:**

- Modificar: vista 404 (localizar con grep abajo)

El usuario que llega a una URL inexistente no tiene forma de volver al site.

- [x] **Paso 1: Localizar la vista 404** ✅

```bash
grep -rn "404\|not.found\|NotFound" resources/views/ --include="*.php" -l
grep -rn "404" app/routes.php | head -5
```

Identificar el fichero exacto.

- [x] **Paso 2: Añadir navegación mínima** ✅

En la vista 404, añadir dentro del contenido visible (después del mensaje de error):

```php
<div class="error-nav" style="text-align:center; margin-top: 2rem;">
    <a href="/" style="
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.75rem 1.5rem;
        background: var(--color-primario, #8B6914);
        color: #fff;
        border-radius: 0.5rem;
        text-decoration: none;
        font-family: var(--font-body, sans-serif);
        font-size: 1rem;
    ">
        <i class="bi bi-house" aria-hidden="true"></i>
        Volver al inicio
    </a>
</div>
```

Si la vista usa el layout principal (`layouts/main.php`), el navbar ya está presente y este enlace es un fallback adicional bajo el mensaje de error para claridad.

- [x] **Paso 3: Verificar en browser** ✅

Navegar a `/url-que-no-existe`. La página 404 debe mostrar el botón "Volver al inicio" que lleva a `/`.

- [x] **Paso 4: Verificar accesibilidad básica** ✅

```bash
make e2e-a11y
```

Sin regresiones en contraste o estructura semántica.

- [x] **Paso 5: Commit** ✅

```bash
git add resources/views/
git commit -m "fix(ux): añadir enlace de regreso al inicio en página 404"
```

---

## Verificación final del sprint

- [ ] **Ola 0 completa:**

  ```bash
  # Recorrer manualmente: /quiz → resultado, /admin/reviews → modal rechazar,
  # /keeper/dashboard → KPIs, /loyalty/card → iconos, /cafes → imágenes con placeholder
  ```

- [ ] **Ola 1 completa:**

  ```bash
  make test-unit
  make phpstan
  # Verificar: grep -c "message:" phpstan-baseline.neon (debe ser < X anterior)
  ```

- [ ] **Ola 2 completa:**

  ```bash
  make test-unit
  grep "KDS_WARN_SECONDS\|KDS_LATE_SECONDS\|KDS_CRITICAL_SECONDS" app/Http/Controllers/Kitchen/KitchenController.php
  ```

- [ ] **Ola 3 completa:**

  ```bash
  make e2e-a11y
  # Verificar: curl -s -o /dev/null -w "%{http_code}" http://localhost/ruta-inventada (→ 404 con enlace visible)
  ```
