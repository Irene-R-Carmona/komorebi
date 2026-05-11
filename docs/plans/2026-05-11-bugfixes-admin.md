# Plan: Corrección de 10 bugs en panel Admin

**Estado**: 🔵 Plan creado — pendiente inicio
**Fecha**: 2026-05-11
**Wave**: 6

## TL;DR

Diez bugs confirmados en el backoffice admin (7 originales detectados en sesión + 3 nuevos tras auditoría completa del módulo). Correcciones focalizadas en vistas y controladores existentes, sin cambios de arquitectura.

---

## Bugs confirmados y causa raíz

| # | Bug | Causa raíz | Archivo(s) afectado(s) |
|---|-----|-----------|----------------------|
| A | Animals filter → redirige a manager/dashboard | Form `action="/keeper/animals"` enviado por admin → middleware keeper rechaza → redirect | `resources/views/backoffice/keeper/animals/index.php` L80 |
| B | AnimalController no filtra por especie/estado | `getAllAnimals()` sin params de filtro en controller | `app/Http/Controllers/Admin/AnimalController.php` L51-70 |
| C | admin/menu usa ¥ (yen) en precios | `¥<?= number_format(...) ?>` hardcodeado en vista | `resources/views/admin/products/index.php` ~L208 |
| D | Toggle de producto no funciona | `@click="toggleProduct()"` pero JS solo tiene `toggleProductStatus()` | `resources/views/admin/products/index.php` ~L243 |
| E | No hay filtro por alérgenos en admin/menu | Falta `<select name="allergen">` en form GET y MenuController no lo procesa | `resources/views/admin/products/index.php` + `app/Http/Controllers/Admin/MenuController.php` |
| F | Data viewer sin paginación | `getDataViewerSamples()` usa LIMIT 10 hardcodeado, sin offset ni nav | `app/Repositories/StatisticsRepository.php` L432 + controller + vista |
| G | Sidebar: hueco visual entre bloque usuario y borde inferior | `body` sin `min-height: 100vh` | `public/css/backoffice-modern.css` |
| H | Dashboard: link "Crear reserva" → 404 | `href="/admin/reservas/crear"` — ruta no existe en el router | `resources/views/admin/home.php` L254 |
| I | `users/create.php`: JS TypeError al enviar formulario | `querySelector('[name=_csrf]')` devuelve null; el campo real es `name="csrf_token"` | `resources/views/admin/users/create.php` L78 |
| J | `users/edit.php`: JS TypeError al enviar formulario | Mismo selector `[name=_csrf]` incorrecto | `resources/views/admin/users/edit.php` ~L78 |

---

## Falsos positivos verificados (auditoría de 22 bugs)

Los siguientes items reportados en la auditoría **no son bugs**:

- `window.AppRoutes` undefined → **definido** en `resources/views/layouts/backoffice.php` L57-63
- Settings view missing → **existe** `resources/views/admin/settings/index.php`
- `ReservationController::show()` siempre redirige → **diseño intencional** documentado en docblock (`"Detalle no disponible como página independiente"`)
- Products form `action` apunta a URL incorrecta → **dead code**; `@submit.prevent="submitForm"` intercepta el submit y usa `/api/v1/admin/menu` directamente vía fetch
- `AnimalController` renderiza vistas keeper → **reuso intencional** de vistas compartidas (patrón documentado)
- Reports export con `fetch`+blob → **funciona**: ruta GET `/admin/reports/export` existe, CSRF via `meta[name=csrf-token]`, blob download es patrón válido para descarga de archivos

---

## Pasos de implementación

### Fase 1: Fix animals filter (Bugs A + B)

- [ ] **Paso 1** — `resources/views/backoffice/keeper/animals/index.php`:
  - Cambiar `action="/keeper/animals"` → `action="<?= $baseUrl ?? '/keeper/animals' ?>"`
  - Cambiar href de botón "Limpiar filtros" → `href="<?= $baseUrl ?? '/keeper/animals' ?>"`
  - Corregir links de paginación (si los hay) con el mismo `$baseUrl`

- [ ] **Paso 2** — `app/Http/Controllers/Admin/AnimalController.php`:
  - Leer `$search`, `$status`, `$species` del querystring en `index()`
  - Filtrar el array retornado por `getAllAnimals()` en el controller (no en el servicio, para no cambiar la interfaz)
  - Pasar `'baseUrl' => '/admin/animals'` y `'currentParams'` al `View::render`

- [ ] **Paso 3** — Verificar que `KeeperAnimalController::index()` pase `'baseUrl' => '/keeper/animals'`

### Fase 2: Fix admin/menu (Bugs C + D + E) — paralelo con Fase 1

- [ ] **Paso 4** — `resources/views/admin/products/index.php`:
  - Reemplazar `¥<?= number_format(...) ?>` → `<?= \App\Support\CurrencyFormatting::euro((int) ($product['price'] ?? 0)) ?>`
  - Cambiar `@click="toggleProduct(<?= $productId ?>, ...)"` → `@click="toggleProductStatus(<?= $productId ?>)"`
  - Añadir `<select name="allergen" @change="$el.form.requestSubmit()">` al filter-bar con opciones: todos, con_alergenos, sin_alergenos

- [ ] **Paso 5** — `app/Http/Controllers/Admin/MenuController.php`:
  - Leer `$allergen = \trim((string) ($q['allergen'] ?? ''))`
  - Filtrar `$products` si `$allergen === 'with'` (solo con alérgenos) o `'without'` (solo sin)
  - Incluir `'allergen' => $allergen` en `$currentParams`

### Fase 3: Data viewer paginación (Bug F)

- [ ] **Paso 6** — `app/Repositories/StatisticsRepository.php`:
  - Añadir parámetros `int $page = 1, int $limit = 10` a `getDataViewerSamples()`
  - Calcular `$offset = ($page - 1) * $limit`
  - Cambiar `LIMIT 10` por `LIMIT :limit OFFSET :offset` en todas las queries del método

- [ ] **Paso 7** — `app/Repositories/Contracts/StatisticsRepositoryInterface.php`:
  - Actualizar firma: `getDataViewerSamples(int $page = 1, int $limit = 10): array`

- [ ] **Paso 8** — `app/Http/Controllers/Admin/DataViewerController.php`:
  - Leer `$page = \max(1, (int) ($request->getQueryParams()['page'] ?? 1))`
  - Pasar `$page` a `getDataViewerSamples($page)`
  - Pasar `'page' => $page` a la vista

- [ ] **Paso 9** — `resources/views/admin/data-viewer.php`:
  - Añadir nav de paginación (Anterior/Siguiente) al pie de la página
  - Links `href="?page=N"` condicionados: Anterior si `$page > 1`, Siguiente siempre visible

### Fase 4: Sidebar gap + bugs puntuales (Bugs G + H + I + J) — paralelo con Fase 3

- [ ] **Paso 10** — `public/css/backoffice-modern.css`:
  - Añadir `min-height: 100vh` al selector `body` o al wrapper `.d-flex` del layout backoffice

- [ ] **Paso 11** — `resources/views/admin/home.php` L254:
  - Cambiar `href="/admin/reservas/crear"` → `href="/admin/reservations"` (no existe ruta de creación de reservas desde admin)

- [ ] **Paso 12** — `resources/views/admin/users/create.php`:
  - Cambiar `document.querySelector('[name=_csrf]')` → `document.querySelector('[name=csrf_token]')`

- [ ] **Paso 13** — `resources/views/admin/users/edit.php`:
  - Cambiar `document.querySelector('[name=_csrf]')` → `document.querySelector('[name=csrf_token]')`

---

## Archivos a modificar

- `resources/views/backoffice/keeper/animals/index.php`
- `app/Http/Controllers/Admin/AnimalController.php`
- `resources/views/admin/products/index.php`
- `app/Http/Controllers/Admin/MenuController.php`
- `app/Repositories/StatisticsRepository.php`
- `app/Repositories/Contracts/StatisticsRepositoryInterface.php`
- `app/Http/Controllers/Admin/DataViewerController.php`
- `resources/views/admin/data-viewer.php`
- `public/css/backoffice-modern.css`
- `resources/views/admin/home.php`
- `resources/views/admin/users/create.php`
- `resources/views/admin/users/edit.php`

---

## Verificación

1. `/admin/animals?species=gato` → muestra solo gatos, no redirige a manager/dashboard
2. `/admin/animals?status=sick` → filtra correctamente por estado
3. `/admin/animals` (keeper) → sigue funcionando con su propio baseUrl
4. En `/admin/menu`, click toggle → estado cambia sin error de consola JS, sin recarga completa
5. En `/admin/menu`, filtro "Con alérgenos" → muestra solo productos con alérgenos
6. Precios en `/admin/menu` muestran `€` no `¥`
7. `/admin/data-viewer?page=2` → segunda página de datos visible con nav funcional
8. Sidebar: sin hueco visible entre el bloque de usuario y el borde inferior del viewport
9. Dashboard: link en la sección reservas → navega a `/admin/reservations` sin 404
10. `/admin/users/create` → submit funciona sin TypeError en consola
11. `/admin/users/{id}/edit` → submit funciona sin TypeError en consola

---

## Decisiones técnicas

- **Bug D**: Cambiar la llamada en la VISTA para que coincida con el nombre del método JS existente (no cambiar el JS, para evitar romper otros posibles usos)
- **Bug E**: Filtrado server-side en GET form (consistente con el patrón existente de `search`/`category`/`status`); filtrar el array ya cargado sin cambios en el repositorio
- **Bug F**: Paginación global afectando todas las tablas del data viewer simultáneamente; LIMIT 10 fijo por página
- **Bug B**: Filtrar en el controller el resultado de `getAllAnimals()` para no cambiar la interfaz del servicio ni el repositorio
