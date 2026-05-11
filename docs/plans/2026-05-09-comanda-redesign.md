# Plan: Rediseño Comanda Wizard (Step 5) + Integración KDS pre_order

**Wave:** 5 — Feature
**Fecha:** 2026-05-09
**Estado:** 🟡 En implementación

---

## Objetivo

Transformar el Step 5 (Comanda opcional) del wizard de reservas de placeholder a UI completa:

- Filtro de alérgenos por chips
- Menú filtrado por categoría de café (`cafe.category`)
- Panel de cuota de incluidos por pase
- Integración KDS vía `status='pre_order'` → activación en recepción al check-in
- Cancelación limpia (borra pre_orders en cascada)
- Backoffice: sección pre-order read-only

---

## Archivos afectados

| Archivo | Cambio |
|---|---|
| `migrations/004_reservations.sql` | Integrado — `pre_order` en ENUM de `reservation_items.status` |
| `app/Repositories/ReservationRepository.php` | + `insertPreOrderItems()`, `deletePreOrderItems()`, `getPreOrderItems()` |
| `app/Repositories/Contracts/ReservationRepositoryInterface.php` | + 3 métodos |
| `app/Services/ReservationService.php` | `create()` inserta pre_order; `cancel()` borra |
| `app/Http/Controllers/Api/V1/Ops/ReceptionApiController.php` | + `activatePreorder()` |
| `app/Services/ReceptionService.php` | + `activatePreOrder()` |
| `app/Services/Contracts/ReceptionServiceInterface.php` | + método |
| `app/routes.php` | + ruta activate-preorder |
| `public/js/sections/reservas.js` | Rediseño completo Feature D → v16 |
| `resources/views/shared/reservas/index.php` | Step 5 HTML completo; bump v=16 |
| `resources/views/reception/index.php` | Sección pre-order en modal check-in |
| `resources/views/admin/reservations/partials/_modal.php` | Panel pre-order read-only |

---

## Datos clave de referencia

- `pass_inclusions`: `{category_id, quantity_per_pax, max_unit_price (cents|NULL), category_name}`
- `passActivo.inclusions` ya disponible en Alpine vía `/api/v1/passes` → `PassController`
- `reservation_items.status` ENUM actual: `('pre_order','pending','kitchen','ready','served')`
- `product.price` = INT cents como float en API (toViewArray no divide por 100)
- `product.allergens_list` = `[{id, name, icon, icon_color, severity}]`
- `product.target_cafe_types` = `[]` → global; `['lounge']` → sólo lounge, etc.
- KDS filtra `status IN ('pending','kitchen','ready')` → no afectado por `pre_order`

---

## Tareas

### [x] T1 — Migración: añadir `pre_order` al ENUM

**Integrado en:** `migrations/004_reservations.sql` — `pre_order` ya está en el ENUM de `reservation_items.status`.

**Verificación:** `docker compose exec app php scripts/apply-db.php`

---

### [ ] T2 — Repository: métodos pre_order

**Archivo:** `app/Repositories/ReservationRepository.php`

Añadir tres métodos públicos:

- `insertPreOrderItems(int $reservationId, array $items): void`
  items shape: `[{product_id: int, qty: int}]`
  INSERT INTO `reservation_items (reservation_id, product_id, quantity, status)` con `status='pre_order'`
- `deletePreOrderItems(int $reservationId): int` → DELETE + rowCount()
- `getPreOrderItems(int $reservationId): array`
  SELECT `ri.product_id, ri.quantity, p.name, p.price, mc.name AS category_name, mc.id AS category_id`
  JOIN products + menu_categories WHERE `reservation_id=:id AND status='pre_order'`

**Interfaz:** Actualizar `ReservationRepositoryInterface` con los 3 métodos.

**Tests:** `tests/Unit/Repositories/ReservationRepositoryPreOrderTest.php`

---

### [ ] T3 — Service: create() + cancel() pre_order

**Archivo:** `app/Services/ReservationService.php`

**`create()`** — tras `$reservationId = $this->reservationRepo->create(...)`:

```php
if (!empty($data['pre_order']) && \is_array($data['pre_order'])) {
    $this->reservationRepo->insertPreOrderItems($reservationId, $data['pre_order']);
}
```

Items shape validada antes: `[{product_id: int, qty: int}]`. Si la forma es incorrecta → ignorar (no lanzar excepción — el usuario simplemente no tendrá items).

**`cancel()`** — antes de `$this->reservationRepo->cancel(...)`:

```php
$this->reservationRepo->deletePreOrderItems($reservationId);
```

**Interfaz:** `ReservationServiceInterface::create()` — no cambia firma (ya acepta `array $data`).

**Tests:** `tests/Unit/Services/ReservationServicePreOrderTest.php`

---

### [ ] T4 — Reception API: endpoint activate-preorder

**Archivo:** `app/Http/Controllers/Api/V1/Ops/ReceptionApiController.php`

Nuevo método `activatePreorder(ServerRequestInterface $request, int $id): ResponseInterface`:

```
POST /api/v1/ops/reception/reservations/{id}/activate-preorder
Auth: apiAuth + apiRole(admin|manager|supervisor|reception) + CSRF
```

Flujo:

1. `$result = $this->receptionService->activatePreOrder($id, $cafeId)`
2. Si `!$result->ok` → `$this->unprocessable($result->getMessage())`
3. Retorna `{ok: true, activated: N, unavailable: [{product_id, name}]}`

**`ReceptionService::activatePreOrder(int $reservationId, int $cafeId): Result`:**

1. Obtener items con `getPreOrderItems($reservationId)`
2. Si vacío → `Result::fail('Sin items pre-order', 'no_preorder')`
3. Para cada item: verificar `stock_quantity` — si 0, añadir a `unavailable`
4. UPDATE en una sola query: `status = 'pending' WHERE reservation_id=:id AND status='pre_order' AND product_id NOT IN (...unavailable_ids)`
5. `Result::ok(['activated' => N, 'unavailable' => [...]])`

**Interfaz:** Añadir `activatePreOrder(int $reservationId, int $cafeId): Result` a `ReceptionServiceInterface`.

**Ruta** en `app/routes.php` dentro del grupo `$opsReceptionApiMiddleware`:

```php
$r->post('/reservations/{id}/activate-preorder', 'Api\V1\Ops\ReceptionApiController@activatePreorder', [$mw->csrf()]);
```

**Tests:** `tests/Unit/Services/ReceptionServiceActivatePreOrderTest.php`

---

### [ ] T5 — Frontend JS: reservas.js v16

**Archivo:** `public/js/sections/reservas.js`

#### Estado nuevo (junto a `comandaLocal`)

```js
alergenos: [],
loadingAlergenos: false,
alergenosExcluidos: new Set(),   // ids de alérgenos que quiero EXCLUIR del menú
```

#### Métodos nuevos / modificados

**`loadAlergenos()`** — llamado una vez al abrir Step 5:

```js
async loadAlergenos() {
  if (this.alergenos.length > 0) return;
  this.loadingAlergenos = true;
  try {
    const res = await fetch('/api/v1/menu/alergenos');
    const json = await res.json();
    if (json.ok) this.alergenos = json.data ?? [];
  } catch (e) { console.error('loadAlergenos', e); }
  finally { this.loadingAlergenos = false; }
},

toggleAlergeno(id) {
  if (this.alergenosExcluidos.has(id)) {
    this.alergenosExcluidos.delete(id);
  } else {
    this.alergenosExcluidos.add(id);
  }
  this.alergenosExcluidos = new Set(this.alergenosExcluidos); // reactivity
},

sinAlergias() {
  this.alergenosExcluidos.clear();
  this.alergenosExcluidos = new Set();
},
```

**`loadProductos()`** — sin cambios (ya agrupa por category_id).

**Getters nuevos / modificados:**

```js
// Productos filtrados por café y sin alérgenos seleccionados
get productosParaCafe() {
  const cafeType = this.cafeActivo?.category ?? null;
  const allProds = Object.values(this.productos).flat();
  return allProds.filter(p => {
    // Filtrar por café type
    if (cafeType && Array.isArray(p.target_cafe_types) && p.target_cafe_types.length > 0) {
      if (!p.target_cafe_types.includes(cafeType)) return false;
    }
    return true;
  });
},

// Categorías que tienen al menos 1 producto para este café
get categoriasDisponibles() {
  const cats = {};
  for (const p of this.productosParaCafe) {
    if (!cats[p.category_id]) {
      cats[p.category_id] = { id: p.category_id, name: p.category_name };
    }
  }
  return Object.values(cats);
},

// Productos de la categoría activa, visibilidad por alérgenos
get productosPorCategoria() {
  const src = this.comandaCatActiva === 0
    ? this.productosParaCafe
    : this.productosParaCafe.filter(p => p.category_id === this.comandaCatActiva);
  return src;
},

// Cuotas por categoría (basadas en passActivo.inclusions y personas)
get quotasPorCategoria() {
  const inclusions = this.passActivo?.inclusions ?? [];
  const result = {};
  for (const inc of inclusions) {
    const total = (inc.quantity_per_pax ?? 0) * this.personas;
    const used = this.comandaLocal
      .filter(i => i.category_id === inc.category_id
        && (inc.max_unit_price === null || i.price_cents <= inc.max_unit_price))
      .reduce((s, i) => s + i.qty, 0);
    result[inc.category_id] = {
      category_name: inc.category_name,
      total,
      used: Math.min(used, total),
      max_unit_price: inc.max_unit_price,
    };
  }
  return result;
},
```

**`comandaBreakdown` revisado:**

```js
get comandaBreakdown() {
  const quotas = { ...this.quotasPorCategoria }; // copia para consumir cuotas
  return this.comandaLocal.map(item => {
    const quota = quotas[item.category_id];
    let incluidasUnits = 0;
    if (quota && quota.total > quota.used + incluidasUnits) {
      const canInclude = (quota.max_unit_price === null || item.price_cents <= quota.max_unit_price)
        ? Math.min(item.qty, quota.total - quota.used)
        : 0;
      incluidasUnits = canInclude;
      if (quota) quota.used += incluidasUnits;
    }
    const extraUnits = item.qty - incluidasUnits;
    return {
      ...item,
      incluidas: incluidasUnits,
      extras: extraUnits,
      total_cents: extraUnits * item.price_cents,
    };
  });
},

get comandaTotal() {
  return this.comandaBreakdown.reduce((s, i) => s + i.total_cents, 0);
},

get comandaHasItems() {
  return this.comandaLocal.length > 0;
},
```

**`productoContieneAlergenoExcluido(p)`:**

```js
productoContieneAlergenoExcluido(p) {
  if (this.alergenosExcluidos.size === 0) return false;
  return (p.allergens_list ?? []).some(a => this.alergenosExcluidos.has(a.id));
},
```

**`addToComanda(p)`** — guardar también `price_cents`:

```js
addToComanda(product) {
  if ((product.stock_quantity ?? null) === 0) return; // agotado
  const existing = this.comandaLocal.find(i => i.product_id === product.id);
  if (existing) {
    existing.qty++;
  } else {
    this.comandaLocal.push({
      product_id: product.id,
      name: product.name,
      price_cents: product.price, // ya en cents
      category_id: product.category_id,
      qty: 1,
    });
  }
},
```

**`cantidadEnComanda(productId)`** — simplificar (ya no merge con carrito):

```js
cantidadEnComanda(productId) {
  return this.comandaLocal.find(i => i.product_id === productId)?.qty ?? 0;
},
```

**`submitReservation()`** — añadir `pre_order` al payload:

```js
body: JSON.stringify({
  cafe_id: this.selectedCafeId,
  pass_product_id: this.selectedPassId,
  date: this.fecha,
  time: this.hora,
  guests: this.personas,
  special_requests: this.comentarios,
  pre_order: this.comandaLocal.map(i => ({ product_id: i.product_id, qty: i.qty })),
}),
```

**Apertura Step 5** — añadir `loadAlergenos()`:

```js
// En el @click del header Step 5:
if(fecha && hora) { toggleStep(5); if(openStep===5){ loadProductos(); loadAlergenos(); } }
// En el botón Siguiente del Step 4:
if(fecha && hora) { openStep = 5; loadProductos(); loadAlergenos(); }
```

**Eliminar:** referencias a `loadCarrito()` y `carrito` en la lógica de comanda (queda sólo `comandaLocal`).

**Bump:** comentario o constante `v16` en el cabecera del archivo.

---

### [ ] T6 — Frontend view: Step 5 HTML completo

**Archivo:** `resources/views/shared/reservas/index.php`

Reemplazar el contenido del `<div class="rsv3-step__body">` del Paso 5 con:

```html
<div class="rsv3-step__body">

  <!-- Spinner inicial -->
  <div x-show="loadingProductos || loadingAlergenos" class="rsv3-comanda-loading">
    <i class="bi bi-hourglass-split" aria-hidden="true"></i>
    Cargando carta&hellip;
  </div>

  <template x-if="!loadingProductos && !loadingAlergenos">
    <div>

      <!-- 1. FILTRO DE ALÉRGENOS -->
      <div class="rsv3-alergenos">
        <p class="rsv3-alergenos__label">
          <i class="bi bi-funnel" aria-hidden="true"></i>
          ¿Tienes alguna alergia?
        </p>
        <div class="rsv3-alergenos__chips">
          <template x-for="al in alergenos" :key="al.id">
            <button type="button"
              class="rsv3-alergen-chip"
              :class="{ 'rsv3-alergen-chip--active': alergenosExcluidos.has(al.id) }"
              @click="toggleAlergeno(al.id)"
              :aria-pressed="alergenosExcluidos.has(al.id)"
              x-text="al.name">
            </button>
          </template>
        </div>
        <button type="button" class="rsv3-alergenos__skip"
          @click="sinAlergias()"
          x-show="alergenosExcluidos.size > 0">
          <i class="bi bi-x-circle" aria-hidden="true"></i>
          Quitar filtros
        </button>
      </div>

      <!-- 2. PANEL DE CUOTA DE INCLUIDOS (si hay pase con inclusions) -->
      <template x-if="passActivo?.inclusions?.length && Object.keys(quotasPorCategoria).length">
        <div class="rsv3-quota-panel">
          <p class="rsv3-quota-panel__title">
            <i class="bi bi-gift" aria-hidden="true"></i>
            Incluido en tu pase
          </p>
          <template x-for="(q, catId) in quotasPorCategoria" :key="catId">
            <div class="rsv3-quota-row">
              <span class="rsv3-quota-row__name" x-text="q.category_name"></span>
              <div class="rsv3-quota-bar">
                <div class="rsv3-quota-bar__fill"
                  :style="'width:' + (q.total > 0 ? Math.round((q.used/q.total)*100) : 0) + '%'">
                </div>
              </div>
              <span class="rsv3-quota-row__count"
                x-text="q.used + ' / ' + q.total"></span>
            </div>
          </template>
        </div>
      </template>

      <!-- 3. TABS DE CATEGORÍAS -->
      <div class="rsv3-comanda-cats" x-show="categoriasDisponibles.length > 0">
        <button type="button" class="rsv3-comanda-cat"
          :class="{'rsv3-comanda-cat--active': comandaCatActiva === 0}"
          @click="comandaCatActiva = 0">Todos</button>
        <template x-for="cat in categoriasDisponibles" :key="cat.id">
          <button type="button" class="rsv3-comanda-cat"
            :class="{'rsv3-comanda-cat--active': comandaCatActiva === cat.id}"
            @click="comandaCatActiva = cat.id"
            x-text="cat.name">
          </button>
        </template>
      </div>

      <!-- 4. GRID DE PRODUCTOS -->
      <div class="rsv3-comanda-grid" x-show="productosPorCategoria.length > 0">
        <template x-for="p in productosPorCategoria" :key="p.id">
          <div class="rsv3-comanda-item"
            :class="{
              'rsv3-comanda-item--allergen': productoContieneAlergenoExcluido(p),
              'rsv3-comanda-item--agotado': (p.stock_quantity ?? null) === 0
            }">

            <!-- Imagen opcional -->
            <template x-if="p.image_url">
              <img :src="p.image_url" :alt="p.name"
                class="rsv3-comanda-item__img" loading="lazy">
            </template>

            <div class="rsv3-comanda-item__info">
              <span class="rsv3-comanda-item__name" x-text="p.name"></span>
              <span class="rsv3-comanda-item__jp" x-show="p.japanese_name"
                x-text="p.japanese_name"></span>
              <span class="rsv3-comanda-item__price" x-text="formatEuro(p.price)"></span>

              <!-- Badges de alérgenos -->
              <div class="rsv3-comanda-item__allergens" x-show="p.allergens_list?.length">
                <template x-for="al in p.allergens_list" :key="al.id">
                  <span class="rsv3-alg-badge"
                    :class="{ 'rsv3-alg-badge--warn': alergenosExcluidos.has(al.id) }"
                    :title="al.name"
                    x-text="al.name">
                  </span>
                </template>
              </div>

              <!-- Aviso alérgeno -->
              <template x-if="productoContieneAlergenoExcluido(p)">
                <p class="rsv3-alg-warning">
                  <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
                  Contiene alérgenos seleccionados
                </p>
              </template>

              <!-- Badge agotado -->
              <template x-if="(p.stock_quantity ?? null) === 0">
                <span class="rsv3-badge rsv3-badge--agotado">Agotado</span>
              </template>
            </div>

            <!-- Controles de cantidad -->
            <div class="rsv3-comanda-item__controls"
              x-show="(p.stock_quantity ?? null) !== 0">
              <button type="button" class="rsv3-qty-btn"
                x-show="cantidadEnComanda(p.id) > 0"
                @click="removeFromComanda(p.id)"
                aria-label="Quitar uno">&minus;</button>
              <span class="rsv3-qty-val"
                x-show="cantidadEnComanda(p.id) > 0"
                x-text="cantidadEnComanda(p.id)"></span>
              <button type="button" class="rsv3-qty-btn rsv3-qty-btn--add"
                :disabled="productoContieneAlergenoExcluido(p)"
                @click="addToComanda(p)"
                aria-label="Añadir uno">+</button>
            </div>
          </div>
        </template>
      </div>

      <!-- Sin productos disponibles -->
      <p class="rsv3-comanda-empty"
        x-show="productosPorCategoria.length === 0 && !loadingProductos">
        No hay productos disponibles para este café.
      </p>

      <!-- 5. DESGLOSE COMANDA -->
      <template x-if="comandaHasItems">
        <div class="rsv3-comanda-breakdown">
          <p class="rsv3-comanda-breakdown__title">Tu comanda</p>
          <template x-for="item in comandaBreakdown" :key="item.product_id">
            <div class="rsv3-comanda-breakdown__row">
              <span x-text="item.qty + '× ' + item.name"></span>
              <span>
                <template x-if="item.incluidas > 0 && item.extras === 0">
                  <span class="rsv3-comanda-inc-label">Incluido en pase</span>
                </template>
                <template x-if="item.incluidas > 0 && item.extras > 0">
                  <span>
                    <span class="rsv3-comanda-inc-label" x-text="item.incluidas + '× incluido'"></span>
                    + <span x-text="formatEuro(item.total_cents)"></span>
                  </span>
                </template>
                <template x-if="item.incluidas === 0">
                  <span x-text="formatEuro(item.total_cents)"></span>
                </template>
              </span>
            </div>
          </template>
          <div class="rsv3-comanda-breakdown__total">
            <span>Total adicional</span>
            <span x-text="formatEuro(comandaTotal)"></span>
          </div>
          <p class="rsv3-comanda-breakdown__note">IVA incluido · Pago en el local</p>
        </div>
      </template>

      <!-- 6. ACCIONES -->
      <div class="rsv3-comanda-actions">
        <button type="button" class="btn btn--secundario btn--sm"
          @click="openStep = 6">
          Omitir este paso
        </button>
        <button type="button" class="btn btn--primario btn--sm"
          @click="openStep = 6">
          Añadir a mi reserva
          <i class="bi bi-arrow-right" aria-hidden="true"></i>
        </button>
      </div>

    </div>
  </template>
</div>
```

**Bump:** cambiar `?v=15` → `?v=16` en el `<script>`.

---

### [ ] T7 — Reception view: sección pre-order en modal check-in

**Archivo:** `resources/views/reception/index.php`

En el modal de check-in (`<!-- MODAL WELCOME -->`), antes de `<form @submit.prevent="submitCheckin()">`, añadir:

```html
<!-- Pre-order items (si existen) -->
<div x-show="checkinPreOrder.length > 0" class="preorder-panel">
  <p class="preorder-panel__title">
    <i class="bi bi-list-check" aria-hidden="true"></i>
    Pre-pedido del cliente
  </p>
  <ul class="preorder-list">
    <template x-for="item in checkinPreOrder" :key="item.product_id">
      <li class="preorder-list__item">
        <span x-text="item.qty + '× ' + item.name"></span>
        <span x-text="formatPriceReception(item.price)"></span>
      </li>
    </template>
  </ul>
  <button type="button" class="btn-confirm"
    :disabled="activatingPreOrder || loading"
    @click="activatePreOrder()">
    <span x-show="!activatingPreOrder">
      <i class="bi bi-fire" aria-hidden="true"></i>
      Enviar todo a cocina
    </span>
    <span x-show="activatingPreOrder">Enviando&hellip;</span>
  </button>
  <p x-show="preOrderResult" class="preorder-result"
    x-text="preOrderResult"></p>
</div>
```

En el `Alpine.data('receptionApp', ...)` de `public/js/sections/reception.js` (o el script inline), añadir:

- Estado: `checkinPreOrder: [], activatingPreOrder: false, preOrderResult: null`
- En `openCheckin(id)`: fetch `/api/v1/ops/reception/reservations/{id}/pre-order` → poblar `checkinPreOrder`
- Método `activatePreOrder()`: POST `/api/v1/ops/reception/reservations/{id}/activate-preorder` → mostrar resultado

**Nuevo endpoint GET (opcional):** `GET /api/v1/ops/reception/reservations/{id}/pre-order` para obtener los items.
O alternativamente: pasar `pre_order_items` en la respuesta de `todayReservations` (añadir JOIN).

> **Decisión de implementación:** añadir `pre_order_items` como array anidado en cada reserva de `todayReservations` para evitar N+1 al abrir el modal.

---

### [ ] T8 — Backoffice: panel pre-order read-only

**Archivo:** `resources/views/admin/reservations/partials/_modal.php`

Añadir sección que se cargue al hacer click en una fila de la lista:

```html
<template x-if="selectedReservation?.pre_order_items?.length">
  <div class="admin-preorder-panel">
    <h4>Pre-pedido registrado</h4>
    <table class="admin-table admin-table--sm">
      <thead>
        <tr><th>Producto</th><th>Qty</th><th>Categoría</th><th>Precio</th></tr>
      </thead>
      <tbody>
        <template x-for="item in selectedReservation.pre_order_items" :key="item.product_id">
          <tr>
            <td x-text="item.name"></td>
            <td x-text="item.quantity"></td>
            <td x-text="item.category_name"></td>
            <td x-text="formatEuroAdmin(item.price)"></td>
          </tr>
        </template>
      </tbody>
    </table>
  </div>
</template>
```

El admin controller ya pasa las reservas al listado. Se necesita que `pre_order_items` esté incluido en cada reserva — añadir LEFT JOIN o subquery en `ReservationRepository::findAll()` (o en el método que usa el admin controller).

---

## Verificación

```bash
# Tras T1:
docker compose exec app php scripts/apply-db.php

# Tras T2-T4:
docker compose exec app ./bin/phpunit tests/Unit/Repositories/ReservationRepositoryPreOrderTest.php
docker compose exec app ./bin/phpunit tests/Unit/Services/ReservationServicePreOrderTest.php
docker compose exec app ./bin/phpunit tests/Unit/Services/ReceptionServiceActivatePreOrderTest.php

# Full suite:
make test-unit

# PHPStan:
make phpstan

# JS syntax:
node --check public/js/sections/reservas.js

# Browser manual:
# 1. Crear reserva con comanda → verificar reservation_items con status='pre_order'
# 2. Reception check-in → botón "Enviar a cocina" → items pasan a 'pending'
# 3. KDS debe mostrar los items
# 4. Cancelar reserva → verification items borrados
```
