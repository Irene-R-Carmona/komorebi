# Supervisor Dashboard v2 — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Corregir el bug crítico de los timers en cocina (cuentan desde creación, no desde INICIAR), añadir la columna `kitchen_started_at` en la migración definitiva, crear un layout supervisor sin sidebar, y reestructurar el dashboard en 2 columnas con colores semánticos correctos.

**Architecture:** Modificación in-place de `migrations/004_reservations.sql` (regla de oro: sin migraciones de fix separadas). Nuevo layout `supervisor.php` basado en `kds.php` (pantalla completa, solo top-bar). Dashboard en 2 columnas fijas (65 % órdenes / 35 % mesas+reservas). Timer en cocina usa `kitchen_started_ts`; en pending usa `created_ts`.

**Tech Stack:** PHP 8.4, Alpine.js 3.14.9, CSS custom tokens (`/public/css/design-tokens.css`), MySQL, PHPUnit, Docker (`docker compose exec app`)

---

## Decisiones de Diseño (rationale — no cambiar sin justificación)

| Decisión | Razón |
|---|---|
| Sin acordeones | NNGroup: los acordeones aumentan el coste de interacción en dashboards operativos donde el usuario necesita la mayoría del contenido visible |
| Sin sidebar | El supervisor opera en "floor mode" — la barra lateral desperdicia ~250 px y crea tentación de navegación que cerraría el SSE |
| Sin accesos rápidos a Recepción/KDS/Keeper | Separación física de terminales real; la navegación cruzada cierra SSE, crea confusión de rol y duplica operaciones |
| Mesas ocupadas en dorado (`--color-accent-500: #c9a959`) | Las mesas ocupadas son el estado **operativo normal**, no una señal de peligro. El rojo danger está reservado para urgencias reales |
| `kitchen_started_at` en `004_reservations.sql` | Regla de oro del proyecto: no crear migraciones de fix/drop. Modificar la definitiva |

---

## Mapa de Archivos

| Acción | Archivo | Responsabilidad |
|---|---|---|
| Modificar | `migrations/004_reservations.sql` | Añadir `kitchen_started_at` a `reservation_items` |
| Modificar | `app/Repositories/ReservationItemRepository.php` | `ITEM_SELECT` + `updateKitchenStarted()` |
| Modificar | `app/Repositories/Contracts/ReservationItemRepositoryInterface.php` | Declarar `updateKitchenStarted()` |
| Modificar | `app/Services/KitchenService.php` | `startPreparing()` llama a `updateKitchenStarted()` |
| Modificar | `app/Http/Controllers/Kitchen/KitchenController.php` | `ui_time` usa `kitchen_started_ts` para status=kitchen |
| Crear | `resources/views/layouts/supervisor.php` | Layout pantalla completa, top-bar, sin sidebar |
| Crear | `public/css/layouts/supervisor.css` | Estilos del top-bar y área de contenido |
| Modificar | `app/Http/Controllers/Supervisor/SupervisorController.php` | Cambiar `layout => 'backoffice'` → `'supervisor'` |
| Modificar | `resources/views/supervisor/dashboard.php` | 2-col layout, timers con field configurable |
| Modificar | `public/css/backoffice/supervisor-dashboard.css` | Colores semánticos, 2-col layout |
| Modificar | `public/js/backoffice/supervisor-dashboard.js` | `getOrderAge(order, field)` con parámetro configurable |
| Crear | `tests/Unit/Services/KitchenServiceStartPreparingTest.php` | Test de `startPreparing()` con timestamp |
| Crear | `tests/Unit/Repositories/ReservationItemRepositoryUpdateKitchenStartedTest.php` | Test de `updateKitchenStarted()` |

---

## Tarea 1: Añadir `kitchen_started_at` a la migración definitiva

**Files:**
- Modify: `migrations/004_reservations.sql` (tabla `reservation_items`, líneas ~171-183)

- [ ] **Paso 1.1: Localizar la posición exacta dentro de `CREATE TABLE reservation_items`**

```bash
grep -n "kitchen_started_at\|created_at TIMESTAMP\|idx_items_kds" migrations/004_reservations.sql
```
Esperado: línea con `created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP` y los índices.

- [ ] **Paso 1.2: Insertar la columna y el índice**

En `migrations/004_reservations.sql`, dentro de `CREATE TABLE reservation_items`, añadir **después** de `created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,`:

```sql
    kitchen_started_at TIMESTAMP NULL DEFAULT NULL COMMENT 'Cuándo se inició la preparación (status → kitchen)',
```

Y antes del cierre `)`, añadir el índice:
```sql
    INDEX idx_ri_kitchen_started (kitchen_started_at),
```

El bloque relevante queda así:
```sql
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    kitchen_started_at TIMESTAMP NULL DEFAULT NULL COMMENT 'Cuándo se inició la preparación (status → kitchen)',
    INDEX idx_items_res (reservation_id),
    INDEX idx_items_prod (product_id),
    INDEX idx_items_kds_active (status, created_at) COMMENT 'KDS: filtro activos pending+kitchen',
    INDEX idx_reservation_items_timeline (reservation_id, created_at DESC),
    INDEX idx_ri_kitchen_started (kitchen_started_at),
```

- [ ] **Paso 1.3: Verificar sintaxis SQL**

```bash
docker compose exec app php scripts/apply-db.php --dry-run 2>&1 | grep -i error
```
Si no existe `--dry-run`, revisar que el archivo sea sintácticamente válido con un diff visual.

- [ ] **Paso 1.4: Commit**

```bash
git add migrations/004_reservations.sql
git commit -m "feat(migration): add kitchen_started_at to reservation_items in 004"
```

---

## Tarea 2: Actualizar `ReservationItemRepository`

**Files:**
- Modify: `app/Repositories/ReservationItemRepository.php`
- Modify: `app/Repositories/Contracts/ReservationItemRepositoryInterface.php`

- [ ] **Paso 2.1: Añadir `kitchen_started_at` al `ITEM_SELECT`**

En `ReservationItemRepository.php`, el `ITEM_SELECT` actualmente termina con:
```php
    private const ITEM_SELECT = '
        ri.id, ri.quantity, ri.status, ri.created_at,
        UNIX_TIMESTAMP(ri.created_at) AS created_ts,
        ri.reservation_id,
```

Añadir justo después de `UNIX_TIMESTAMP(ri.created_at) AS created_ts,`:
```php
        ri.kitchen_started_at,
        UNIX_TIMESTAMP(ri.kitchen_started_at) AS kitchen_started_ts,
```

El bloque completo queda:
```php
    private const ITEM_SELECT = '
        ri.id, ri.quantity, ri.status, ri.created_at,
        UNIX_TIMESTAMP(ri.created_at) AS created_ts,
        ri.kitchen_started_at,
        UNIX_TIMESTAMP(ri.kitchen_started_at) AS kitchen_started_ts,
        ri.reservation_id,
        p.id AS product_id, p.name AS product_name, p.station,
        p.prep_time, p.recipe_steps, p.ingredients_list, p.critical_check,
        t.code AS tracker_code,
        r.guest_count AS guests,
        GROUP_CONCAT(DISTINCT CONCAT_WS(\'|\', al.code, al.name, al.icon_color, al.severity)
            ORDER BY al.name SEPARATOR \';;\'
        ) AS allergen_data
    ';
```

- [ ] **Paso 2.2: Añadir método `updateKitchenStarted()` al repositorio**

Al final de la clase `ReservationItemRepository`, antes del cierre `}`, añadir:

```php
    public function updateKitchenStarted(int $id): void
    {
        $stmt = $this->getDb()->prepare(
            'UPDATE reservation_items SET kitchen_started_at = NOW() WHERE id = ?'
        );
        $stmt->execute([$id]);
    }
```

- [ ] **Paso 2.3: Declarar `updateKitchenStarted()` en la interfaz**

En `app/Repositories/Contracts/ReservationItemRepositoryInterface.php`, añadir:
```php
    public function updateKitchenStarted(int $id): void;
```

- [ ] **Paso 2.4: Confirmar que PHPStan no da errores**

```bash
docker compose exec app php vendor/bin/phpstan analyse app/Repositories/ --level=5 2>&1
```
Esperado: `[OK] No errors`

- [ ] **Paso 2.5: Commit**

```bash
git add app/Repositories/ReservationItemRepository.php \
        app/Repositories/Contracts/ReservationItemRepositoryInterface.php
git commit -m "feat(repository): add kitchen_started_ts to ITEM_SELECT + updateKitchenStarted()"
```

---

## Tarea 3: Corregir `KitchenService::startPreparing()`

**Files:**
- Modify: `app/Services/KitchenService.php`

- [ ] **Paso 3.1: Actualizar `startPreparing()` para registrar el timestamp**

El método actual es:
```php
    #[Override]
    public function startPreparing(int $itemId): bool
    {
        return $this->itemRepo->updateStatus($itemId, ReservationItem::STATUS_KITCHEN);
    }
```

Reemplazar por:
```php
    #[Override]
    public function startPreparing(int $itemId): bool
    {
        $ok = $this->itemRepo->updateStatus($itemId, ReservationItem::STATUS_KITCHEN);
        if ($ok) {
            $this->itemRepo->updateKitchenStarted($itemId);
        }
        return $ok;
    }
```

- [ ] **Paso 3.2: Confirmar PHPStan**

```bash
docker compose exec app php vendor/bin/phpstan analyse app/Services/KitchenService.php --level=5 2>&1
```
Esperado: `[OK] No errors`

- [ ] **Paso 3.3: Commit**

```bash
git add app/Services/KitchenService.php
git commit -m "fix(kitchen): startPreparing() now sets kitchen_started_at timestamp"
```

---

## Tarea 4: Corregir `KitchenController::processOrdersForDisplay()`

**Files:**
- Modify: `app/Http/Controllers/Kitchen/KitchenController.php`

- [ ] **Paso 4.1: Localizar el bloque de cálculo de `ui_time`**

```bash
grep -n "ui_time\|created_ts\|kitchen_started" app/Http/Controllers/Kitchen/KitchenController.php
```

El bloque actual (dentro de `processOrdersForDisplay`):
```php
        foreach ($itemsRaw as $item) {
            // Calcular tiempo de espera (created_ts viene como UNIX_TIMESTAMP desde MySQL — sin desfase de timezone)
            $seconds = $now - (int) ($item['created_ts'] ?? \strtotime($item['created_at']));

            // Formatear tiempo para UI
            $item['ui_time'] = \gmdate(($seconds > 3600 ? 'H:i:s' : 'i:s'), $seconds);
```

- [ ] **Paso 4.2: Usar `kitchen_started_ts` cuando `status === 'kitchen'`**

Reemplazar el bloque de cálculo de `$seconds` por:
```php
        foreach ($itemsRaw as $item) {
            // Usar kitchen_started_at como base cuando el cocinero ha pulsado INICIAR,
            // ya que el timer debe medir el tiempo de preparación real, no el de creación.
            $baseTs = ($item['status'] === 'kitchen' && !empty($item['kitchen_started_ts']))
                ? (int) $item['kitchen_started_ts']
                : (int) ($item['created_ts'] ?? \strtotime($item['created_at']));

            $seconds = $now - $baseTs;

            // Formatear tiempo para UI
            $item['ui_time'] = \gmdate(($seconds > 3600 ? 'H:i:s' : 'i:s'), $seconds);
```

- [ ] **Paso 4.3: PHPStan**

```bash
docker compose exec app php vendor/bin/phpstan analyse app/Http/Controllers/Kitchen/KitchenController.php --level=5 2>&1
```
Esperado: `[OK] No errors`

- [ ] **Paso 4.4: Commit**

```bash
git add app/Http/Controllers/Kitchen/KitchenController.php
git commit -m "fix(kds): timer uses kitchen_started_ts for items in 'kitchen' status"
```

---

## Tarea 5: Crear layout `supervisor.php`

**Files:**
- Create: `resources/views/layouts/supervisor.php`
- Create: `public/css/layouts/supervisor.css`

- [ ] **Paso 5.1: Crear `public/css/layouts/supervisor.css`**

```css
/* =====================================================
   Supervisor Layout — Pantalla completa, top-bar only
   ===================================================== */

.supervisor-mode {
    min-height: 100vh;
    background-color: var(--color-surface-0, #faf8f5);
    display: flex;
    flex-direction: column;
    font-family: var(--font-sans, 'Inter', system-ui, sans-serif);
}

/* ── Top Bar ─────────────────────────────────────── */
.supervisor-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    height: 56px;
    padding: 0 1.5rem;
    background-color: var(--color-primary-700, #3b2519);
    border-bottom: 2px solid var(--color-accent-500, #c9a959);
    position: sticky;
    top: 0;
    z-index: 100;
    gap: 1rem;
}

.supervisor-header__brand {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-shrink: 0;
}

.supervisor-header__logo {
    height: 32px;
    width: auto;
}

.supervisor-header__cafe {
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--color-accent-300, #e8d5a3);
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.supervisor-header__role {
    font-size: 0.7rem;
    color: var(--color-primary-300, #a8836c);
    text-transform: uppercase;
    letter-spacing: 0.08em;
}

.supervisor-header__clock {
    font-size: 1.25rem;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
    color: var(--color-text-primary, #1a1a1a);
    background: var(--color-surface-1, #fff);
    padding: 0.25rem 0.75rem;
    border-radius: 6px;
    border: 1px solid var(--color-border, #e5e0d8);
    letter-spacing: 0.06em;
}

.supervisor-header__actions {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-shrink: 0;
}

.btn-supervisor-logout {
    display: flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.375rem 0.75rem;
    font-size: 0.8rem;
    font-weight: 500;
    color: var(--color-primary-200, #d4b09a);
    background: transparent;
    border: 1px solid var(--color-primary-500, #7a4f3a);
    border-radius: 6px;
    cursor: pointer;
    transition: background 0.15s, color 0.15s;
    text-decoration: none;
}

.btn-supervisor-logout:hover {
    background: var(--color-primary-600, #5c3d2e);
    color: var(--color-accent-300, #e8d5a3);
}

/* ── Content Area ────────────────────────────────── */
.supervisor-content {
    flex: 1;
    overflow-y: auto;
    padding: 1.5rem;
}

/* ── Responsive ──────────────────────────────────── */
@media (max-width: 768px) {
    .supervisor-header {
        padding: 0 1rem;
        height: 48px;
    }

    .supervisor-header__cafe {
        display: none;
    }

    .supervisor-content {
        padding: 1rem;
    }
}
```

- [ ] **Paso 5.2: Crear `resources/views/layouts/supervisor.php`**

Basado en `kds.php` pero con identidad de supervisor (claro, no oscuro).

```php
<!DOCTYPE html>
<html lang="es">

<head>
    <?php

    use App\Core\Csrf;

    $cspNonce = $GLOBALS['cspNonce'] ?? '';
    ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= Csrf::token() ?>">
    <title>Supervisor | <?= e($titulo ?? 'Panel') ?></title>
    <link rel="icon" type="image/svg+xml" href="/images/logos/komorebi-logo-icon.svg">
    <link rel="alternate icon" href="/favicon.ico">

    <!-- Fuentes -->
    <link rel="dns-prefetch" href="//fonts.googleapis.com">
    <link rel="dns-prefetch" href="//fonts.gstatic.com">
    <link href="https://fonts.googleapis.com" rel="preconnect">
    <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
    <link rel="preload"
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Roboto+Mono:wght@400;700&display=swap"
        as="style" data-preload-style crossorigin>
    <noscript>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Roboto+Mono:wght@400;700&display=swap"
            rel="stylesheet">
    </noscript>

    <script nonce="<?= $cspNonce ?>">
        (function () {
            document.querySelectorAll('link[data-preload-style]').forEach(function (link) {
                try {
                    var ss = document.createElement('link');
                    ss.rel = 'stylesheet';
                    ss.href = link.href;
                    if (link.crossOrigin) ss.crossOrigin = link.crossOrigin;
                    document.head.appendChild(ss);
                } catch (e) { /* noop */ }
            });
        })();
    </script>

    <!-- Iconos Material Symbols -->
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1"
        rel="stylesheet">

    <!-- Design tokens globales -->
    <link href="/css/design-tokens.css" rel="stylesheet">

    <!-- Layout supervisor -->
    <link href="/css/layouts/supervisor.css" rel="stylesheet">

    <!-- CSS específico de la página (inyectado por View::render) -->
    <?php foreach ($styles ?? [] as $style): ?>
        <link href="/css/<?= e($style) ?>" rel="stylesheet">
    <?php endforeach; ?>

    <!-- Alpine.js -->
    <script src="/js/components/fallbacks.js"></script>
    <script defer src="/js/init/event-delegation.js"></script>
    <script nonce="<?= $cspNonce ?>" src="/js/init/alpine-components.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.9/dist/cdn.min.js"></script>
</head>

<body class="supervisor-mode">

    <!-- TOP BAR -->
    <header class="supervisor-header" role="banner">
        <div class="supervisor-header__brand">
            <img src="/images/logos/komorebi-logo-icon.svg"
                 alt="Komorebi Café"
                 class="supervisor-header__logo"
                 width="32" height="32">
            <div>
                <div class="supervisor-header__cafe">Komorebi Café</div>
                <div class="supervisor-header__role">Supervisor</div>
            </div>
        </div>

        <div class="supervisor-header__clock" id="supervisorClock" aria-live="polite" aria-label="Hora actual">
            --:--
        </div>

        <div class="supervisor-header__actions">
            <form method="POST" action="/logout" style="margin:0;">
                <?= \App\Core\Csrf::field() ?>
                <button type="submit" class="btn-supervisor-logout" title="Cerrar sesión">
                    <span class="material-symbols-outlined" aria-hidden="true"
                        style="font-size:1rem;">power_settings_new</span>
                    Salir
                </button>
            </form>
        </div>
    </header>

    <!-- CONTENT -->
    <main class="supervisor-content" id="main-content">
        <?= $content ?>
    </main>

    <script nonce="<?= $cspNonce ?>">
        // Reloj en tiempo real para el header del supervisor
        (function () {
            function tick() {
                var el = document.getElementById('supervisorClock');
                if (el) {
                    var now = new Date();
                    el.textContent = now.toLocaleTimeString('es-ES', {
                        hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false
                    });
                }
            }
            tick();
            setInterval(tick, 1000);
        })();
    </script>

</body>

</html>
```

- [ ] **Paso 5.3: Verificar que el layout se puede renderizar sin errores**

```bash
docker compose exec app php -r "require 'bootstrap/container.php'; echo 'OK';"
```
Esperado: `OK`

- [ ] **Paso 5.4: Commit**

```bash
git add resources/views/layouts/supervisor.php public/css/layouts/supervisor.css
git commit -m "feat(layout): add supervisor full-screen layout (no sidebar)"
```

---

## Tarea 6: Cambiar layout en `SupervisorController`

**Files:**
- Modify: `app/Http/Controllers/Supervisor/SupervisorController.php`

- [ ] **Paso 6.1: Cambiar el cuarto parámetro de `View::render()`**

El método `index()` actualmente llama:
```php
        View::render('supervisor/dashboard', [
            'titulo'        => 'Supervisor — Panel',
            // ...
        ], [], 'backoffice');
```

Cambiar `'backoffice'` por `'supervisor'`:
```php
        View::render('supervisor/dashboard', [
            'titulo'        => 'Supervisor — Panel',
            'cafe_id'       => $cafeId,
            'reservations'  => $data['reservations'],
            'activeTables'  => $data['activeTables'],
            'pendingOrders' => $data['pendingOrders'],
            'kitchenOrders' => $data['kitchenOrders'],
            'readyOrders'   => $data['readyOrders'],
        ], [], 'supervisor');
```

- [ ] **Paso 6.2: Eliminar la carga condicional de assets en `backoffice.php`** (si aplica)

Buscar en `resources/views/layouts/backoffice.php` si hay un bloque como:
```bash
grep -n "supervisor-dashboard" resources/views/layouts/backoffice.php
```
Si existe, ese bloque ya no es necesario (el layout `supervisor.php` carga los assets directamente). Documentarlo pero no eliminar aún — esperar a la Tarea 7 donde el CSS/JS migran al layout supervisor.

- [ ] **Paso 6.3: PHPStan**

```bash
docker compose exec app php vendor/bin/phpstan analyse app/Http/Controllers/Supervisor/ --level=5 2>&1
```
Esperado: `[OK] No errors`

- [ ] **Paso 6.4: Commit**

```bash
git add app/Http/Controllers/Supervisor/SupervisorController.php
git commit -m "feat(supervisor): switch layout from backoffice to supervisor"
```

---

## Tarea 7: Reestructurar `dashboard.php` en 2 columnas

**Files:**
- Modify: `resources/views/supervisor/dashboard.php`

El dashboard pasa de un bento-grid a una estructura de **2 columnas fijas**:
- Columna izquierda (65 %): todas las órdenes agrupadas en 3 paneles (Pending / En Cocina / Listos)
- Columna derecha (35 %): Mesas activas + Reservas del día

- [ ] **Paso 7.1: Reemplazar el bloque de layout bento por el 2-col**

Dentro del `<div class="container-fluid" x-data='supervisorDashboard(...)'>`, reemplazar todo el contenido de layout (manteniendo el KPI strip y el header que ya están) por la siguiente estructura:

```php
    <!-- KPI Strip — mantener el existente sin cambios -->

    <!-- 2-Col Layout -->
    <div class="supervisor-2col">

        <!-- ════════════════════════════════════════════════
             Columna Izquierda — Órdenes (65%)
             ════════════════════════════════════════════════ -->
        <section class="supervisor-col supervisor-col--orders" aria-labelledby="orders-heading">
            <h2 id="orders-heading" class="supervisor-col__title">
                <span class="material-symbols-outlined" aria-hidden="true">receipt_long</span>
                Comandas
            </h2>

            <!-- Panel: Pendientes -->
            <div class="order-panel order-panel--pending">
                <div class="order-panel__header">
                    <span class="order-panel__label">
                        <span class="status-dot status-dot--pending" aria-hidden="true"></span>
                        Pendientes
                    </span>
                    <span class="order-panel__count" x-text="pendingOrders.length"></span>
                </div>
                <div class="order-panel__body">
                    <template x-if="pendingOrders.length === 0">
                        <p class="order-panel__empty">Sin comandas pendientes</p>
                    </template>
                    <template x-for="order in pendingOrders" :key="order.id">
                        <div class="order-card order-card--pending">
                            <div class="order-card__meta">
                                <span class="order-card__product" x-text="order.product_name"></span>
                                <span class="order-card__tracker" x-text="order.tracker_code || '—'"></span>
                            </div>
                            <div class="order-card__footer">
                                <span class="order-card__station" x-text="order.station"></span>
                                <span class="order-timer"
                                    :class="getTimerClass(getOrderAge(order, 'created_ts'))"
                                    x-show="_tick >= 0"
                                    x-text="getOrderAge(order, 'created_ts') + 'min'"
                                    aria-label="Tiempo de espera"></span>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Panel: En cocina -->
            <div class="order-panel order-panel--kitchen">
                <div class="order-panel__header">
                    <span class="order-panel__label">
                        <span class="status-dot status-dot--kitchen" aria-hidden="true"></span>
                        En Cocina
                    </span>
                    <span class="order-panel__count" x-text="kitchenOrders.length"></span>
                </div>
                <div class="order-panel__body">
                    <template x-if="kitchenOrders.length === 0">
                        <p class="order-panel__empty">Cocina sin órdenes activas</p>
                    </template>
                    <template x-for="order in kitchenOrders" :key="order.id">
                        <div class="order-card order-card--kitchen">
                            <div class="order-card__meta">
                                <span class="order-card__product" x-text="order.product_name"></span>
                                <span class="order-card__tracker" x-text="order.tracker_code || '—'"></span>
                            </div>
                            <div class="order-card__footer">
                                <span class="order-card__station" x-text="order.station"></span>
                                <span class="order-timer"
                                    :class="getTimerClass(getOrderAge(order, 'kitchen_started_ts'))"
                                    x-show="_tick >= 0"
                                    x-text="getOrderAge(order, 'kitchen_started_ts') + 'min'"
                                    aria-label="Tiempo en preparación"></span>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Panel: Listos -->
            <div class="order-panel order-panel--ready">
                <div class="order-panel__header">
                    <span class="order-panel__label">
                        <span class="status-dot status-dot--ready" aria-hidden="true"></span>
                        Listos para servir
                    </span>
                    <span class="order-panel__count" x-text="readyOrders.length"></span>
                </div>
                <div class="order-panel__body">
                    <template x-if="readyOrders.length === 0">
                        <p class="order-panel__empty">Nada listo aún</p>
                    </template>
                    <template x-for="order in readyOrders" :key="order.id">
                        <div class="order-card order-card--ready">
                            <div class="order-card__meta">
                                <span class="order-card__product" x-text="order.product_name"></span>
                                <span class="order-card__tracker" x-text="order.tracker_code || '—'"></span>
                            </div>
                            <div class="order-card__footer">
                                <span class="order-card__station" x-text="order.station"></span>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </section>

        <!-- ════════════════════════════════════════════════
             Columna Derecha — Mesas + Reservas (35%)
             ════════════════════════════════════════════════ -->
        <aside class="supervisor-col supervisor-col--context" aria-labelledby="context-heading">

            <!-- Panel: Mesas activas -->
            <div class="context-panel">
                <h3 class="context-panel__title" id="context-heading">
                    <span class="material-symbols-outlined" aria-hidden="true">table_restaurant</span>
                    Mesas en uso
                    <span class="context-panel__count" x-text="activeTables.length"></span>
                </h3>
                <div class="tables-grid">
                    <template x-if="activeTables.length === 0">
                        <p class="context-panel__empty">Sin mesas activas</p>
                    </template>
                    <template x-for="table in activeTables" :key="table.id">
                        <div class="table-cell table-cell--occupied">
                            <span class="table-cell__code" x-text="table.table_code || table.tracker_code || '?'"></span>
                            <span class="table-cell__name" x-text="table.name || table.guest_name || '—'"></span>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Panel: Reservas del día -->
            <div class="context-panel">
                <h3 class="context-panel__title">
                    <span class="material-symbols-outlined" aria-hidden="true">event_note</span>
                    Reservas de hoy
                    <span class="context-panel__count" x-text="reservations.length"></span>
                </h3>

                <!-- Filtro de estado -->
                <div class="reservation-filters" role="group" aria-label="Filtrar reservas">
                    <button class="filter-btn" :class="{ 'filter-btn--active': reservationFilter === '' }"
                        @click="reservationFilter = ''" type="button">Todas</button>
                    <button class="filter-btn" :class="{ 'filter-btn--active': reservationFilter === 'active' }"
                        @click="reservationFilter = 'active'" type="button">Activas</button>
                    <button class="filter-btn" :class="{ 'filter-btn--active': reservationFilter === 'confirmed' }"
                        @click="reservationFilter = 'confirmed'" type="button">Confirmadas</button>
                </div>

                <ul class="reservation-list" role="list">
                    <template x-if="filteredReservations.length === 0">
                        <li class="context-panel__empty">Sin reservas para este filtro</li>
                    </template>
                    <template x-for="res in filteredReservations" :key="res.id">
                        <li class="reservation-item" :class="'reservation-item--' + (res.status || 'pending')">
                            <span class="reservation-item__time" x-text="res.time || res.scheduled_at || '—'"></span>
                            <span class="reservation-item__name" x-text="res.name || res.guest_name || 'Sin nombre'"></span>
                            <span class="reservation-item__guests">
                                <span class="material-symbols-outlined" aria-hidden="true"
                                    style="font-size:0.875rem;">group</span>
                                <span x-text="res.guest_count || '?'"></span>
                            </span>
                        </li>
                    </template>
                </ul>
            </div>
        </aside>

    </div><!-- /.supervisor-2col -->
```

- [ ] **Paso 7.2: Verificar que no quedan restos del layout bento anterior**

```bash
grep -n "bento\|bento-grid\|grid-cols-3" resources/views/supervisor/dashboard.php
```
Esperado: sin resultados.

- [ ] **Paso 7.3: Commit**

```bash
git add resources/views/supervisor/dashboard.php
git commit -m "feat(supervisor): 2-col layout — orders (65%) + context (35%)"
```

---

## Tarea 8: Actualizar CSS del dashboard supervisor

**Files:**
- Modify: `public/css/backoffice/supervisor-dashboard.css`

- [ ] **Paso 8.1: Añadir estilos del 2-col layout y corregir colores semánticos**

Añadir al final de `public/css/backoffice/supervisor-dashboard.css`:

```css
/* =====================================================
   Supervisor Dashboard v2 — 2-col layout + semantic colors
   ===================================================== */

/* ── 2-Col Layout ──────────────────────────────────── */
.supervisor-2col {
    display: grid;
    grid-template-columns: 65fr 35fr;
    gap: 1.25rem;
    align-items: start;
}

@media (max-width: 900px) {
    .supervisor-2col {
        grid-template-columns: 1fr;
    }
}

.supervisor-col {
    display: flex;
    flex-direction: column;
    gap: 0.875rem;
}

.supervisor-col__title {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.875rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--color-text-secondary, #6b6560);
    margin: 0 0 0.25rem;
}

/* ── Order Panels ─────────────────────────────────── */
.order-panel {
    background: var(--color-surface-1, #fff);
    border: 1px solid var(--color-border, #e5e0d8);
    border-radius: 10px;
    overflow: hidden;
}

.order-panel__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.625rem 1rem;
    font-size: 0.8rem;
    font-weight: 600;
    border-bottom: 1px solid var(--color-border, #e5e0d8);
}

.order-panel--pending .order-panel__header {
    background: var(--color-warning-50, #fef9ec);
    color: var(--color-warning-700, #92400e);
    border-bottom-color: var(--color-warning-200, #fde68a);
}

.order-panel--kitchen .order-panel__header {
    background: var(--color-info-50, #eff6ff);
    color: var(--color-info-700, #1d4ed8);
    border-bottom-color: var(--color-info-200, #bfdbfe);
}

.order-panel--ready .order-panel__header {
    background: var(--color-success-50, #f0fdf4);
    color: var(--color-success-700, #15803d);
    border-bottom-color: var(--color-success-200, #bbf7d0);
}

.order-panel__label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.order-panel__count {
    font-size: 1rem;
    font-weight: 700;
    min-width: 1.5rem;
    text-align: right;
}

.order-panel__body {
    padding: 0.5rem;
    display: flex;
    flex-direction: column;
    gap: 0.375rem;
    max-height: 280px;
    overflow-y: auto;
}

.order-panel__empty {
    padding: 1rem;
    text-align: center;
    font-size: 0.8rem;
    color: var(--color-text-tertiary, #a09b95);
    font-style: italic;
    margin: 0;
}

/* ── Order Cards ──────────────────────────────────── */
.order-card {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    padding: 0.5rem 0.75rem;
    border-radius: 6px;
    background: var(--color-surface-0, #faf8f5);
    border: 1px solid var(--color-border-light, #f0ebe3);
    transition: background 0.1s;
}

.order-card__meta {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    gap: 0.5rem;
}

.order-card__product {
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--color-text-primary, #1a1a1a);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.order-card__tracker {
    font-size: 0.75rem;
    font-weight: 700;
    font-family: var(--font-mono, 'Roboto Mono', monospace);
    color: var(--color-text-secondary, #6b6560);
    flex-shrink: 0;
}

.order-card__footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.order-card__station {
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--color-text-tertiary, #a09b95);
}

/* ── Status dots ──────────────────────────────────── */
.status-dot {
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
}
.status-dot--pending  { background: var(--color-warning, #C9A959); }
.status-dot--kitchen  { background: var(--color-info, #3b82f6); }
.status-dot--ready    { background: var(--color-success, #5E6F64); }

/* ── Context Panel (right col) ────────────────────── */
.context-panel {
    background: var(--color-surface-1, #fff);
    border: 1px solid var(--color-border, #e5e0d8);
    border-radius: 10px;
    padding: 0.875rem 1rem;
}

.context-panel__title {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--color-text-secondary, #6b6560);
    margin: 0 0 0.75rem;
}

.context-panel__count {
    margin-left: auto;
    font-size: 1rem;
    font-weight: 700;
    color: var(--color-text-primary, #1a1a1a);
}

.context-panel__empty {
    font-size: 0.8rem;
    color: var(--color-text-tertiary, #a09b95);
    font-style: italic;
    padding: 0.5rem 0;
    margin: 0;
}

/* ── Tables Grid (right col) ─────────────────────── */
.tables-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(60px, 1fr));
    gap: 0.5rem;
    margin-bottom: 0.5rem;
}

/* Corregir color semántico: mesas ocupadas = estado NORMAL (dorado, no peligro) */
.table-cell--occupied {
    background: var(--color-accent-500, #c9a959);
    color: #1a1a1a;
    border-color: var(--color-accent-600, #b8943e);
}

.table-cell--occupied .table-cell__code {
    color: #1a1a1a;
    font-weight: 700;
}

.table-cell--occupied .table-cell__name {
    color: rgba(26, 26, 26, 0.75);
}

/* ── Reservation List (right col) ────────────────── */
.reservation-filters {
    display: flex;
    gap: 0.375rem;
    margin-bottom: 0.625rem;
    flex-wrap: wrap;
}

.filter-btn {
    padding: 0.25rem 0.625rem;
    font-size: 0.75rem;
    font-weight: 500;
    border: 1px solid var(--color-border, #e5e0d8);
    border-radius: 20px;
    background: var(--color-surface-0, #faf8f5);
    color: var(--color-text-secondary, #6b6560);
    cursor: pointer;
    transition: background 0.15s, color 0.15s, border-color 0.15s;
    white-space: nowrap;
}

.filter-btn--active,
.filter-btn:hover {
    background: var(--color-primary-600, #5c3d2e);
    color: #fff;
    border-color: var(--color-primary-600, #5c3d2e);
}

.reservation-list {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 0.375rem;
    max-height: 320px;
    overflow-y: auto;
}

.reservation-item {
    display: grid;
    grid-template-columns: 50px 1fr auto;
    align-items: center;
    gap: 0.5rem;
    padding: 0.4375rem 0.5rem;
    border-radius: 6px;
    font-size: 0.8rem;
    background: var(--color-surface-0, #faf8f5);
    border-left: 3px solid transparent;
}

.reservation-item--active {
    border-left-color: var(--color-success, #5E6F64);
    background: var(--color-success-50, #f0fdf4);
}

.reservation-item--confirmed {
    border-left-color: var(--color-info, #3b82f6);
}

.reservation-item--pending {
    border-left-color: var(--color-warning, #C9A959);
}

.reservation-item__time {
    font-family: var(--font-mono, 'Roboto Mono', monospace);
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--color-text-secondary, #6b6560);
}

.reservation-item__name {
    font-weight: 500;
    color: var(--color-text-primary, #1a1a1a);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.reservation-item__guests {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    color: var(--color-text-secondary, #6b6560);
    font-size: 0.75rem;
    flex-shrink: 0;
}
```

- [ ] **Paso 8.2: Verificar que no hay conflictos con los tokens existentes**

```bash
grep -n "color-accent-500\|color-danger\|#ef4444\|#9B2335" public/css/backoffice/supervisor-dashboard.css
```
Esperado: solo aparecen en la sección nueva `.table-cell--occupied` usando `--color-accent-500`.

- [ ] **Paso 8.3: Commit**

```bash
git add public/css/backoffice/supervisor-dashboard.css
git commit -m "feat(supervisor-css): 2-col layout + semantic gold for occupied tables"
```

---

## Tarea 9: Actualizar `supervisor-dashboard.js` — `getOrderAge` configurable

**Files:**
- Modify: `public/js/backoffice/supervisor-dashboard.js`

- [ ] **Paso 9.1: Añadir parámetro `field` a `getOrderAge()`**

El método actual:
```javascript
      getOrderAge(order) {
        if (!order || !order.created_ts) return 0;
        return Math.floor((Date.now() / 1000 - order.created_ts) / 60);
      },
```

Reemplazar por:
```javascript
      getOrderAge(order, field) {
        var f = field || 'created_ts';
        var ts = order && order[f] ? order[f] : (order && order.created_ts ? order.created_ts : null);
        if (!ts) return 0;
        return Math.floor((Date.now() / 1000 - ts) / 60);
      },
```

- [ ] **Paso 9.2: Verificar que no hay errores de sintaxis JS**

```bash
docker compose exec app node --check public/js/backoffice/supervisor-dashboard.js 2>&1 || echo "Node not available in container"
```
Si Node no está disponible en el container, verificar visualmente que el archivo es sintácticamente correcto.

- [ ] **Paso 9.3: Commit**

```bash
git add public/js/backoffice/supervisor-dashboard.js
git commit -m "feat(supervisor-js): getOrderAge(order, field) configurable for kitchen_started_ts"
```

---

## Tarea 10: Escribir tests

**Files:**
- Create: `tests/Unit/Services/KitchenServiceStartPreparingTest.php`
- Create: `tests/Unit/Repositories/ReservationItemRepositoryUpdateKitchenStartedTest.php`

- [ ] **Paso 10.1: Crear test de `KitchenService::startPreparing()` con timestamp**

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Repositories\Contracts\ReservationItemRepositoryInterface;
use App\Services\KitchenService;
use PHPUnit\Framework\TestCase;

/**
 * ¿Qué pruebas aquí?
 * El comportamiento de KitchenService::startPreparing() tras el fix del timer bug.
 *
 * ¿Qué me quieres demostrar?
 * Que al llamar startPreparing(), se invoca updateStatus() Y updateKitchenStarted()
 * en ese orden, y solo cuando updateStatus() devuelve true.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si startPreparing() deja de llamar a updateKitchenStarted(), o si lo llama
 * cuando updateStatus() falla, el test falla.
 */
final class KitchenServiceStartPreparingTest extends TestCase
{
    public function test_startPreparing_calls_updateKitchenStarted_when_status_update_succeeds(): void
    {
        $repo = $this->createMock(ReservationItemRepositoryInterface::class);
        $repo->expects($this->once())
             ->method('updateStatus')
             ->with(42, 'kitchen')
             ->willReturn(true);
        $repo->expects($this->once())
             ->method('updateKitchenStarted')
             ->with(42);

        $service = new KitchenService($repo);
        $result  = $service->startPreparing(42);

        $this->assertTrue($result);
    }

    public function test_startPreparing_does_not_call_updateKitchenStarted_when_status_update_fails(): void
    {
        $repo = $this->createMock(ReservationItemRepositoryInterface::class);
        $repo->expects($this->once())
             ->method('updateStatus')
             ->with(99, 'kitchen')
             ->willReturn(false);
        $repo->expects($this->never())
             ->method('updateKitchenStarted');

        $service = new KitchenService($repo);
        $result  = $service->startPreparing(99);

        $this->assertFalse($result);
    }
}
```

- [ ] **Paso 10.2: Ejecutar el test (debe FALLAR antes de implementar)**

```bash
docker compose exec app php vendor/bin/phpunit tests/Unit/Services/KitchenServiceStartPreparingTest.php --testdox 2>&1
```
Esperado antes de Tarea 3: FAIL — `startPreparing()` no llama a `updateKitchenStarted()`.
Esperado después de Tarea 3: PASS.

- [ ] **Paso 10.3: Crear test de `updateKitchenStarted()`**

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Repositories\ReservationItemRepository;
use PHPUnit\Framework\TestCase;
use PDO;
use PDOStatement;

/**
 * ¿Qué pruebas aquí?
 * El método updateKitchenStarted() de ReservationItemRepository.
 *
 * ¿Qué me quieres demostrar?
 * Que el método ejecuta exactamente una sentencia UPDATE con NOW()
 * y el id correcto, sin lanzar excepciones.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si la query SQL cambia o el parámetro de binding cambia.
 */
final class ReservationItemRepositoryUpdateKitchenStartedTest extends TestCase
{
    public function test_updateKitchenStarted_executes_correct_sql(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())
             ->method('execute')
             ->with([5]);

        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('kitchen_started_at = NOW()'))
            ->willReturn($stmt);

        $repo = new ReservationItemRepository($pdo);
        $repo->updateKitchenStarted(5);
    }
}
```

- [ ] **Paso 10.4: Ejecutar ambos tests**

```bash
docker compose exec app php vendor/bin/phpunit tests/Unit/Services/KitchenServiceStartPreparingTest.php tests/Unit/Repositories/ReservationItemRepositoryUpdateKitchenStartedTest.php --testdox 2>&1
```
Esperado: ambos en PASS.

- [ ] **Paso 10.5: Ejecutar suite completa de unit tests**

```bash
docker compose exec app php vendor/bin/phpunit tests/Unit/ --testdox 2>&1 | tail -20
```
Esperado: sin regresiones.

- [ ] **Paso 10.6: Commit**

```bash
git add tests/Unit/Services/KitchenServiceStartPreparingTest.php \
        tests/Unit/Repositories/ReservationItemRepositoryUpdateKitchenStartedTest.php
git commit -m "test(kitchen): add tests for startPreparing() timestamp + updateKitchenStarted()"
```

---

## Tarea 11: Verificación final

- [ ] **Paso 11.1: PHPStan completo**

```bash
docker compose exec app php vendor/bin/phpstan analyse --level=5 2>&1 | tail -10
```
Esperado: `[OK] No errors`

- [ ] **Paso 11.2: Suite de tests completa**

```bash
docker compose exec app php vendor/bin/phpunit tests/Unit/ --testdox 2>&1 | tail -15
```
Esperado: todos en PASS, sin regresiones.

- [ ] **Paso 11.3: Verificación visual en browser**

1. Navegar a `http://localhost:8080/supervisor/dashboard`
2. Confirmar: layout 2-col visible (órdenes izquierda, mesas+reservas derecha)
3. Confirmar: header sin sidebar — solo top-bar con reloj y botón Salir
4. Confirmar: mesas ocupadas en dorado (#c9a959), no en rojo
5. Confirmar: timers de "En Cocina" muestran `0min` para ítems recién iniciados (no 30+ min)
6. Confirmar: timers de "Pendientes" siguen contando desde `created_ts`

- [ ] **Paso 11.4: Commit final de verificación**

```bash
git add -A
git commit -m "chore: supervisor dashboard v2 — all tasks complete, verified"
git push origin develop
```

---

## Resumen de commits esperados

```
feat(migration): add kitchen_started_at to reservation_items in 004
feat(repository): add kitchen_started_ts to ITEM_SELECT + updateKitchenStarted()
fix(kitchen): startPreparing() now sets kitchen_started_at timestamp
fix(kds): timer uses kitchen_started_ts for items in 'kitchen' status
feat(layout): add supervisor full-screen layout (no sidebar)
feat(supervisor): switch layout from backoffice to supervisor
feat(supervisor): 2-col layout — orders (65%) + context (35%)
feat(supervisor-css): 2-col layout + semantic gold for occupied tables
feat(supervisor-js): getOrderAge(order, field) configurable for kitchen_started_ts
test(kitchen): add tests for startPreparing() timestamp + updateKitchenStarted()
chore: supervisor dashboard v2 — all tasks complete, verified
```
