# Bugfixes UI/UX: Recepción, KDS SOP, Notificaciones, Charts — Plan de Implementación

> **Para agentes:** Las 5 áreas son **independientes entre sí**. Se pueden ejecutar en paralelo
> con `dispatching-parallel-agents` o en cualquier orden con `executing-plans`.

**Goal:** Resolver 5 grupos de bugs/mejoras visuales y funcionales:

1. Doble escape HTML en vistas de recepción (`&#039;` visible como texto)
2. Filtros de categoría/alérgenos en modal de pedido de recepción y comanda del wizard
3. Rediseño del modal SOP del KDS (panel roto → modal centrado full-screen)
4. Notificaciones cocina → recepción cuando un ítem se marca como listo
5. Divisa ¥ → € en dashboard manager, chart de ingresos y carrito de usuario

**Regla:** NO crear migrations de fix/drop. Editar la migration definitiva directamente.
**FrankenPHP:** Tras cualquier cambio PHP, ejecutar `docker compose restart app`.

---

## Área 1 — Doble escape HTML en recepción

**Causa raíz:** `View::render()` aplica `htmlspecialchars()` a todos los strings vía `escapeData()`.
Las vistas que volvían a llamar `htmlspecialchars()` encima generan texto literal `&#039;` en pantalla.

**Archivos:** `resources/views/reception/index.php`

### Tareas

- [ ] **1.1** En `resources/views/reception/index.php`, eliminar **todas** las llamadas a `htmlspecialchars()`
  sobre variables que vienen de `View::render()`.
  Líneas afectadas: ~55, 57, 62, 65, 101, 143, 149, 157, 169, 175, 210, 281–282.
  Patrón a buscar y eliminar: `htmlspecialchars(` y el cierre `, ENT_QUOTES, 'UTF-8')` correspondiente.
  > Conservar `htmlspecialchars()` SOLO si la variable viene directamente de `$_POST`, `$_GET` o `$_SERVER`.

- [ ] **1.2** (Opcional) Buscar el mismo patrón en otras vistas del proyecto:

  ```bash
  grep -rn "htmlspecialchars(" resources/views/ | grep -v "ENT_QUOTES" | head -30
  ```

  Listar candidatos adicionales para una segunda iteración.

**Verificación:**

- En browser: modal de recepción "Añadir Pedido" → nombre de producto "Omurice" sin `&#039;`
- Nombres con apóstrofe (p.ej. "Café d'Or") se muestran correctamente con `'`

---

## Área 2 — Modal recepción: filtros por categoría y alérgenos

**Archivos:** `app/Repositories/ProductRepository.php`, `resources/views/reception/index.php`,
`resources/views/shared/reservas/index.php`

### Tareas

- [x] **2.1** En `app/Repositories/ProductRepository.php`, método `findOrderableItems(int $cafeId)`:
  - Añadir JOIN con tablas de alérgenos al final del FROM/WHERE existente:

    ```sql
    LEFT JOIN product_allergens pa ON pa.product_id = p.id
    LEFT JOIN allergens al ON al.id = pa.allergen_id
    ```

  - Añadir al SELECT:

    ```sql
    GROUP_CONCAT(
        DISTINCT CONCAT_WS('|', al.code, al.name, al.icon_color)
        SEPARATOR ';;'
    ) AS allergen_data
    ```

  - Añadir al final de la query: `GROUP BY p.id`

- [x] **2.2** En `resources/views/reception/index.php`, sección del modal "Añadir Pedido":
  Reemplazar el `<select>` plano por una UI Alpine con tabs de categoría + filtro de alérgenos.

  Estructura mínima:

  ```html
  <div x-data="{
      activeCat: 'all',
      excludedAllergens: [],
      get categories() {
          const cats = [...new Set(orderableItems.map(i => i.category_name).filter(Boolean))];
          return ['all', ...cats];
      },
      get filteredItems() {
          return orderableItems.filter(item => {
              const catOk = this.activeCat === 'all' || item.category_name === this.activeCat;
              if (!catOk) return false;
              if (this.excludedAllergens.length === 0) return true;
              const itemAllergens = (item.allergen_data || '').split(';;')
                  .map(a => a.split('|')[0]).filter(Boolean);
              return !this.excludedAllergens.some(a => itemAllergens.includes(a));
          });
      },
      toggleAllergen(code) {
          const idx = this.excludedAllergens.indexOf(code);
          if (idx >= 0) this.excludedAllergens.splice(idx, 1);
          else this.excludedAllergens.push(code);
      }
  }">
      <!-- Tabs de categoría -->
      <div class="reception-modal__tabs">
          <template x-for="cat in categories" :key="cat">
              <button
                  type="button"
                  :class="{'active': activeCat === cat}"
                  @click="activeCat = cat"
                  x-text="cat === 'all' ? 'Todos' : cat">
              </button>
          </template>
      </div>

      <!-- Badges de alérgenos toggleables (extraídos de todos los items) -->
      <div class="reception-modal__allergens">
          <span class="label">Excluir alérgenos:</span>
          <template x-for="allergen in [...new Set(
              orderableItems.flatMap(i => (i.allergen_data||'').split(';;')
                  .filter(a=>a).map(a => ({code:a.split('|')[0],name:a.split('|')[1]})))
          )]" :key="allergen.code">
              <button
                  type="button"
                  :class="{'excluded': excludedAllergens.includes(allergen.code)}"
                  @click="toggleAllergen(allergen.code)"
                  x-text="allergen.name">
              </button>
          </template>
      </div>

      <!-- Lista filtrada de productos -->
      <ul class="reception-modal__product-list">
          <template x-for="item in filteredItems" :key="item.id">
              <li @click="addToOrder(item)">
                  <span x-text="item.name"></span>
                  <span x-text="item.price_formatted"></span>
              </li>
          </template>
      </ul>
  </div>
  ```

  > `orderableItems` ya existe en el scope Alpine de `receptionApp`. Verificar el nombre exacto.

- [x] **2.3** En `resources/views/shared/reservas/index.php`, sección de comanda del wizard (paso de pre-order): NO APLICA — el wizard no tiene paso de comanda.
  Verificar si ya tiene filtros de categoría/alérgenos. Si no los tiene, aplicar el mismo patrón
  de la tarea 2.2 adaptado al scope de `reservasApp`.

**Verificación:**

- Modal recepción: tabs "Bebidas", "Comidas", "Todos" → solo muestran items de esa categoría
- Badge "Gluten" activado → productos con gluten desaparecen de la lista
- Comanda wizard: mismo comportamiento si se aplicó el paso 2.3

---

## Área 3 — SOP KDS: rediseño como modal centrado

**Causa raíz triple mismatch:**

- HTML usa `.kds-modal` / `.kds-modal__overlay` / `.kds-modal__content` — sin CSS para estas clases.
- `kds.css` tiene `.sop-modal` / `.sop-card` — sistema viejo, diferente nombre.
- `kds-sop.css` tiene `.sop-modal-overlay` / `.sop-container` — sistema nuevo Blueprint, no conectado al HTML.
- Resultado: el `div` fluye sin posición → parece panel lateral derecho roto.

**Solución:** Reescribir el HTML para usar las clases de `kds-sop.css` (ya enlazado en `kds.php`).

**Archivos:** `resources/views/kitchen/partials/sop_modal.php`, `public/js/sections/kds.js`,
`public/css/workspaces/kds-sop.css`, `public/css/workspaces/kds.css`,
`app/Http/Controllers/Kitchen/KitchenController.php`

### Tareas

- [ ] **3.1** Reescribir **completo** `resources/views/kitchen/partials/sop_modal.php`.
  Reemplazar toda la estructura `.kds-modal__*` por la de `kds-sop.css`:

  ```php
  <?php
  /**
   * Partial: SOP Modal
   * Modal centrado que muestra Standard Operating Procedures para preparar productos.
   * Usa el scope de kdsApp (x-data en el layout) — NO tiene x-data propio.
   * Conectado a kds-sop.css (Blueprint split-view design).
   */
  ?>
  <div class="sop-modal-overlay"
      @show-sop.window="openSop($event.detail)"
      @keydown.escape.window="closeSop()"
      x-show="sopOpen"
      x-cloak
      style="display:none;"
      @click.self="closeSop()"
      x-transition:enter="transition ease-out duration-200"
      x-transition:enter-start="opacity-0"
      x-transition:enter-end="opacity-100"
      x-transition:leave="transition ease-in duration-150"
      x-transition:leave-start="opacity-100"
      x-transition:leave-end="opacity-0">

      <div class="sop-container"
          x-show="sopOpen"
          x-transition:enter="transition ease-out duration-200"
          x-transition:enter-start="opacity-0 scale-95"
          x-transition:enter-end="opacity-100 scale-100"
          x-transition:leave="transition ease-in duration-150"
          x-transition:leave-start="opacity-100 scale-100"
          x-transition:leave-end="opacity-0 scale-95">

          <!-- Header -->
          <div class="sop-header">
              <div class="sop-title-group">
                  <div class="sop-icon-box">
                      <span class="material-symbols-outlined">menu_book</span>
                  </div>
                  <div>
                      <h2 class="sop-title" x-text="sopData.title || 'Procedimiento'"></h2>
                      <p class="sop-subtitle" x-text="sopData.station || 'General'"></p>
                  </div>
              </div>
              <button class="btn-close-sop" @click="closeSop()" type="button" aria-label="Cerrar">
                  <span class="material-symbols-outlined">close</span>
              </button>
          </div>

          <!-- Body: split-view -->
          <div class="sop-body">

              <!-- Mise en Place (izquierda) -->
              <div class="sop-mise">
                  <div class="sop-section-header">
                      <span class="material-symbols-outlined">inventory_2</span>
                      <span>Mise en Place</span>
                  </div>
                  <div x-show="sopData.ingred && sopData.ingred.length > 0">
                      <ul class="mise-list">
                          <template x-for="(ingr, idx) in sopData.ingred" :key="idx">
                              <li class="mise-item" :class="{'checked': ingr.checked}">
                                  <button type="button" class="mise-check" @click="toggleMise(idx)"
                                      :aria-label="'Marcar ' + ingr.name">
                                      <span class="material-symbols-outlined"
                                          x-text="ingr.checked ? 'check_circle' : 'radio_button_unchecked'">
                                      </span>
                                  </button>
                                  <span class="mise-name" x-text="ingr.name"></span>
                              </li>
                          </template>
                      </ul>
                  </div>
                  <div x-show="!sopData.ingred || sopData.ingred.length === 0" class="sop-empty-col">
                      <span class="material-symbols-outlined">info</span>
                      <p>Sin ingredientes documentados.</p>
                  </div>
              </div>

              <!-- Pasos de preparación (derecha) -->
              <div class="sop-execution">
                  <div class="sop-section-header">
                      <span class="material-symbols-outlined">task_alt</span>
                      <span>Preparación</span>
                  </div>
                  <div x-show="sopData.steps && sopData.steps.length > 0">
                      <ol class="exec-list">
                          <template x-for="(step, idx) in sopData.steps" :key="idx">
                              <li class="step-card" :class="{'active': step.active}"
                                  @click="activateStep(idx)">
                                  <span class="step-num" x-text="idx + 1"></span>
                                  <div>
                                      <p class="step-title" x-text="step.text"></p>
                                  </div>
                              </li>
                          </template>
                      </ol>
                  </div>
                  <div x-show="!sopData.steps || sopData.steps.length === 0" class="sop-empty-col">
                      <span class="material-symbols-outlined">info</span>
                      <p>Sin pasos documentados.</p>
                  </div>
              </div>
          </div>

          <!-- Footer: HACCP + alérgenos -->
          <div class="sop-footer">
              <div class="haccp-stripe" aria-hidden="true"></div>
              <div class="haccp-content">
                  <div x-show="sopData.allergens && sopData.allergens.length > 0"
                      class="sop-allergens-row">
                      <span class="material-symbols-outlined">emergency</span>
                      <template x-for="a in sopData.allergens" :key="a.code">
                          <span class="sop-allergen-badge"
                              :style="'background-color:' + (a.color || '#ccc')"
                              x-text="a.name">
                          </span>
                      </template>
                  </div>
                  <div x-show="sopData.check" class="sop-haccp-alert">
                      <span class="material-symbols-outlined">warning</span>
                      <span x-text="sopData.check"></span>
                  </div>
                  <div x-show="!sopData.check && (!sopData.allergens || sopData.allergens.length === 0)"
                      class="sop-haccp-empty">
                      Sin puntos críticos HACCP documentados.
                  </div>
              </div>
              <button class="kds-btn kds-btn--secondary" @click="closeSop()" type="button">
                  Entendido
              </button>
          </div>
      </div>
  </div>
  ```

- [ ] **3.2** Añadir regla de badge al final de `public/css/workspaces/kds-sop.css`:

  ```css
  /* Badges de alérgenos en footer — borde y contraste */
  .sop-allergen-badge {
      display: inline-flex;
      align-items: center;
      border: 1px solid rgba(255, 255, 255, 0.3);
      color: #fff;
      text-shadow: 0 1px 2px rgba(0, 0, 0, 0.8);
      padding: 0.25rem 0.75rem;
      border-radius: 4px;
      font-size: 0.85rem;
      font-weight: 600;
  }
  ```

- [ ] **3.3** Actualizar `public/js/sections/kds.js` — método `openSop()` y añadir `toggleMise()` / `activateStep()`:

  ```js
  openSop(data) {
      const ingred = this.parseList(data.ingred);
      const steps  = this.parseSteps(data.steps);

      this.sopData = {
          title:     data.title,
          station:   data.station || 'General',
          ingred:    ingred.map(name => ({ name, checked: false })),
          steps:     steps.map((text, i) => ({ text, active: i === 0 })),
          check:     data.check,
          allergens: Array.isArray(data.allergens) ? data.allergens : [],
      };

      this.sopOpen = true;
  },

  toggleMise(idx) {
      if (this.sopData.ingred[idx] !== undefined) {
          this.sopData.ingred[idx].checked = !this.sopData.ingred[idx].checked;
      }
  },

  activateStep(idx) {
      this.sopData.steps = this.sopData.steps.map((s, i) => ({ ...s, active: i === idx }));
  },
  ```

- [ ] **3.4** En `public/css/workspaces/kds.css`, eliminar las reglas CSS huérfanas del sistema viejo
  (líneas ~441–495). Buscar y eliminar los bloques:

  ```css
  /* Eliminar todo el bloque que contenga estas clases (ya no se usan): */
  .sop-modal { ... }
  .sop-backdrop { ... }
  .sop-card { ... }
  ```

  Verificar primero con `grep -n "\.sop-modal\|\.sop-backdrop\|\.sop-card" public/css/workspaces/kds.css`

- [ ] **3.5** En `app/Http/Controllers/Kitchen/KitchenController.php`, método `processOrdersForDisplay()`,
  añadir flags de seguridad al `json_encode()`:

  ```php
  // ANTES:
  json_encode([...], JSON_UNESCAPED_UNICODE)
  // DESPUÉS:
  json_encode([...], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS)
  ```

**Verificación:**

- KDS en browser: clic en botón SOP de cualquier producto → modal aparece centrado (no panel derecho)
- Ingredientes muestran checkboxes — al hacer clic, se marcan con `check_circle`
- Paso activo resaltado con borde azul (`border-left: 4px solid var(--sop-primary)`)
- Footer naranja con badges de alérgenos (p.ej. "Lactosa") visibles con borde y sombra de texto

---

## Área 4 — Notificaciones cocina → recepción (ítem READY)

**Arquitectura:**

- Mercure Hub configurado en ambos layouts (`kds.php` y `reception.php` via `window.__MERCURE__`)
- `reception.js` ya suscribe a 2 topics Mercure
- `KitchenApiController::completeOrder()` marca ítem pero NO publica Mercure
- `MercurePublisherService::publish()` existe y está funcional
- Nuevo topic: `reception/{cafeId}/kitchen-ready`

**Archivos:** `app/Services/KitchenService.php`, `app/Http/Controllers/Api/V1/Ops/KitchenApiController.php`,
`public/js/sections/reception.js`, `resources/views/layouts/reception.php`

### Tareas

- [ ] **4.1** En `app/Services/KitchenService.php`, añadir método `getOrderItem()`:

  ```php
  public function getOrderItem(int $id): array
  {
      $stmt = $this->itemRepo->getDb()->prepare(
          'SELECT oi.id, oi.product_name, r.cafe_id, r.id AS reservation_id
           FROM order_items oi
           JOIN reservations r ON r.id = oi.reservation_id
           WHERE oi.id = ?'
      );
      $stmt->execute([$id]);
      return $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
  }
  ```

  > Si `ReservationItemRepository` no expone `getDb()`, acceder vía `Database::getConnection()`.
  > Verificar la API del repositorio antes de implementar.

  También añadir la firma al contrato `KitchenServiceInterface`:

  ```php
  public function getOrderItem(int $id): array;
  ```

- [ ] **4.2** En `KitchenApiController.php`, método `completeOrder()`, tras la línea `$ok = $this->service->markReady($id)` con éxito, añadir:

  ```php
  // Publicar notificación Mercure a recepción
  $itemData = $this->service->getOrderItem($id);
  if (!empty($itemData['cafe_id'])) {
      MercurePublisherService::publish(
          'reception/' . (int) $itemData['cafe_id'] . '/kitchen-ready',
          [
              'type'            => 'kitchen-ready',
              'order_id'        => $id,
              'product_name'    => $itemData['product_name'] ?? '',
              'reservation_ref' => '#' . ($itemData['reservation_id'] ?? ''),
          ]
      );
  }
  ```

  Añadir import: `use App\Services\MercurePublisherService;`

- [ ] **4.3** En `public/js/sections/reception.js`, función `initReceptionMercure()`:
  - Añadir un tercer topic a la URL de EventSource:

    ```js
    'topic=' + encodeURIComponent('reception/' + cfg.cafeId + '/kitchen-ready'),
    ```

  - Cambiar el handler de mensajes para distinguir por tipo:

    ```js
    es.onmessage = function(e) {
        let data = {};
        try { data = JSON.parse(e.data); } catch (_) {}

        if (data.type === 'kitchen-ready') {
            window.notificationManager?.show(
                `Listo para servir: ${data.product_name} ${data.reservation_ref}`,
                'success',
                6000
            );
        } else {
            document.dispatchEvent(new CustomEvent('reception:refresh'));
        }
    };
    ```

  > `window.notificationManager` se carga en el paso 4.4.

- [ ] **4.4** En `resources/views/layouts/reception.php`, añadir `notification-manager.js` antes de `reception.js`:

  ```html
  <script src="/js/components/notification-manager.js"></script>
  ```

  > Verificar que `public/js/components/notification-manager.js` expone `window.notificationManager`.

**Verificación:**

- Con KDS y Recepción abiertas en dos pestañas del mismo navegador
- En KDS: marcar ítem como READY → en Recepción aparece toast "Listo para servir: Caramel Macchiato #42" sin recargar la página
- El toast desaparece tras ~6 segundos

---

## Área 5 — Divisa ¥ → € y datos reales en chart

**Causa raíz:**

- `¥` hardcodeado en 3 archivos: `dashboard.php`, `manager-dashboard.js`, `user/cart.php`
- `final_amount` se almacena en **céntimos** pero se muestra sin dividir entre 100
- Query del chart filtra solo `status='completed' AND payment_status='paid'` → casi sin datos en dev

**Archivos:** `resources/views/manager/dashboard.php`, `public/js/backoffice/manager-dashboard.js`,
`app/Services/Manager/DashboardService.php`, `app/Http/Controllers/Manager/DashboardController.php`,
`resources/views/user/cart.php`

### Tareas

- [ ] **5.1** En `resources/views/manager/dashboard.php`, stat card de ingresos (~línea 108):
  - Añadir import al inicio del archivo (si no existe):

    ```php
    use App\Support\CurrencyFormatting;
    ```

  - Cambiar la línea que muestra el total de ingresos:

    ```php
    // ANTES (patrón similar):
    '¥' . number_format(array_sum(array_column($stats['weekly_revenue'] ?? [], 'revenue')), 0)
    // DESPUÉS:
    CurrencyFormatting::euro((int) array_sum(array_column($stats['weekly_revenue'] ?? [], 'revenue')))
    ```

  > `CurrencyFormatting::euro(int $cents)` devuelve string con formato `"38,30 €"`.
  > El valor viene en céntimos → pasarlo como entero directamente.

- [ ] **5.2** En `public/js/backoffice/manager-dashboard.js`, en la configuración del dataset del chart:

  ```js
  // ANTES:
  label: 'Ingresos (¥)',
  // DESPUÉS:
  label: 'Ingresos (€)',
  ```

  Si existe un callback de tooltip que formatea como `¥X`, cambiarlo a `X €`.

- [ ] **5.3** En `app/Services/Manager/DashboardService.php`, método `getWeeklyRevenue()`:
  Reemplazar la query actual por:

  ```php
  $stmt = $this->db->prepare(
      "SELECT
          reservation_date AS date,
          COALESCE(SUM(
              CASE
                  WHEN final_amount IS NOT NULL THEN final_amount
                  ELSE pass_unit_price * guest_count
              END
          ), 0) AS revenue
       FROM reservations
       WHERE cafe_id = :cafe_id
         AND reservation_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
         AND status NOT IN ('cancelled', 'no_show')
         AND deleted_at IS NULL
       GROUP BY reservation_date
       ORDER BY reservation_date"
  );
  ```

  > Incluye reservas `confirmed` y `active`. Usa `pass_unit_price * guest_count` como fallback
  > cuando `final_amount` es NULL (reservas no pagadas aún). Ambos campos están en céntimos.

- [ ] **5.4** En `app/Http/Controllers/Manager/DashboardController.php`, método `formatWeeklyRevenueForChart()`:

  ```php
  // ANTES:
  $data[] = (float) $day['revenue'];
  // DESPUÉS:
  $data[] = round((float) $day['revenue'] / 100, 2);
  ```

  > `final_amount` y `pass_unit_price * guest_count` están en céntimos. `/100` convierte a euros.

- [ ] **5.5** En `resources/views/user/cart.php`, reemplazar todas las ocurrencias de `¥` por `€`.
  Verificar también si hay llamadas a `number_format` que deban ajustarse
  (si muestra céntimos como enteros en vez de euros como decimales).

**Verificación:**

- Dashboard manager: stat card muestra "38,30 €" (no "¥3,830")
- Chart: barras aparecen para todos los días con reservas confirmed/active; eje Y en euros
- Label del dataset: "Ingresos (€)"
- Carrito de usuario: precios muestran "€" en vez de "¥"

---

## Archivos modificados (resumen)

| Archivo | Área(s) |
|---|---|
| `resources/views/reception/index.php` | 1, 2 |
| `app/Repositories/ProductRepository.php` | 2 |
| `resources/views/shared/reservas/index.php` | 2 |
| `resources/views/kitchen/partials/sop_modal.php` | 3 |
| `public/css/workspaces/kds-sop.css` | 3 |
| `public/js/sections/kds.js` | 3 |
| `public/css/workspaces/kds.css` | 3 |
| `app/Http/Controllers/Kitchen/KitchenController.php` | 3 |
| `app/Services/KitchenService.php` | 4 |
| `app/Services/Contracts/KitchenServiceInterface.php` | 4 |
| `app/Http/Controllers/Api/V1/Ops/KitchenApiController.php` | 4 |
| `public/js/sections/reception.js` | 4 |
| `resources/views/layouts/reception.php` | 4 |
| `resources/views/manager/dashboard.php` | 5 |
| `public/js/backoffice/manager-dashboard.js` | 5 |
| `app/Services/Manager/DashboardService.php` | 5 |
| `app/Http/Controllers/Manager/DashboardController.php` | 5 |
| `resources/views/user/cart.php` | 5 |

---

## Decisiones registradas

- **Área 1 (doble escape):** Eliminar `htmlspecialchars()` en vistas, no en helpers/servicios.
- **Área 2 (allergens):** `allergen_data` como `GROUP_CONCAT` — misma estrategia que `KitchenService`.
- **Área 3 (SOP):** Reutilizar `kds-sop.css` Blueprint existente — no reescribir CSS. Solo ajuste de badge.
- **Área 4 (notificaciones):** Usar `notificationManager` (vanilla JS) no `toastManager` (Alpine) — más simple e independiente del orden de init de Alpine.
- **Área 5 (divisa):** No cambiar columnas de BD — el `/100` se hace en el controller al formatear para el chart.
- **Área 5 (query):** Incluir `confirmed` + `active` con `pass_unit_price * guest_count` como fallback. No tocar `getRevenueToday()` (scope diferente, otro widget).

## Comandos de verificación global

```bash
docker compose exec app php -l app/Services/KitchenService.php
docker compose exec app php -l app/Http/Controllers/Api/V1/Ops/KitchenApiController.php
docker compose exec app php -l app/Services/Manager/DashboardService.php
make phpstan
make test-unit
```
