# Plan Fase 0 — Hotfixes críticos (runtime crashes)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Each checkbox is an atomic step. Steps marked ⚠️ require a decision before proceeding.

**Goal:** Eliminar todos los errores de runtime en el proyecto antes de cualquier otra mejora. Estos errores causan fatal errors o comportamiento incorrecto al navegar por la aplicación en producción.

**Architecture:** Los grupos A–C son independientes entre sí y pueden ejecutarse en paralelo por distintos agentes. El grupo D depende de la decisión tomada en C. El grupo E es independiente de todos.

**Tech Stack:** PHP 8.4, `View::render()`, `Raw::json()`, `Env::bool()`, PHPStan nivel 5, migraciones SQL plain.

---

## Mapa de archivos afectados

| Grupo | Archivo | Acción |
|---|---|---|
| A1 | `app/Http/Controllers/Admin/ReviewController.php` | Corregir ruta de vista + adaptar datos |
| A2 | `app/Http/Controllers/Admin/AnimalController.php` | Corregir 3 rutas de vistas |
| A3 | `app/Http/Controllers/Admin/ProductController.php` | Corregir 3 rutas de vistas + adaptar datos |
| B1 | `resources/views/shared/reservas/confirmation.php` | CREAR vista nueva |
| B2 | `resources/views/shared/reservas/lista.php` | CREAR vista nueva |
| C1 | `bootstrap/container.php` | Corregir `FEATURE_BACKOFFICE` |
| D1 | `app/Http/Controllers/Admin/CatalogController.php` | ELIMINAR (huérfano) |
| D2 | `app/Http/Controllers/Admin/ManagerController.php` | ELIMINAR (huérfano) |
| E  | `migrations/019_fix_supervisor_bigint.sql` | CREAR migración |

---

## Grupo A — Corregir rutas de vistas incorrectas

### A1: Admin\ReviewController — ruta y contrato de datos

**Archivo:** `app/Http/Controllers/Admin/ReviewController.php`

**Problema:** El método `index()` llama a la vista `'backoffice/admin/reviews/index'` que no existe.
**Vista real:** `resources/views/admin/reviews/pending.php`
**Incompatibilidad de datos:** La vista existente espera `$alpineConfig` como `Raw::json()` con la forma `{ reviews: [...], csrfToken: "..." }`. El controlador actualmente pasa `$reviews` y `$csrf_token` como variables separadas.

**Fix requerido:**

```php
// ANTES (L40 aproximadamente):
View::render('backoffice/admin/reviews/index', [
    'reviews'    => $reviews,
    'csrf_token' => Csrf::getToken(),
], ['reviews.css'], 'backoffice');

// DESPUÉS:
View::render('admin/reviews/pending', [
    'alpineConfig' => Raw::json([
        'reviews'   => $reviews,
        'csrfToken' => Csrf::getToken(),
    ]),
], ['reviews.css'], 'backoffice');
```

**Tareas:**

- [ ] Leer `app/Http/Controllers/Admin/ReviewController.php` completo — identificar el método `index()` y la línea exacta del `View::render()`
- [ ] Leer `resources/views/admin/reviews/pending.php` — confirmar qué variables usa el template (especialmente `$alpineConfig`)
- [ ] Corregir la ruta: `'backoffice/admin/reviews/index'` → `'admin/reviews/pending'`
- [ ] Adaptar el array de datos para pasar `'alpineConfig' => Raw::json([...])` en lugar de variables separadas
- [ ] Verificar que el namespace `use App\Core\Raw;` está presente en el archivo
- [ ] Ejecutar `make phpstan` — sin errores nuevos
- [ ] Navegar a `/admin/reviews` (en dev) — debe cargar sin error

---

### A2: Admin\AnimalController — 3 rutas incorrectas

**Archivo:** `app/Http/Controllers/Admin/AnimalController.php`

**Problema:** Tres métodos referencian `backoffice/admin/animals/` pero las vistas reales están en `backoffice/keeper/animals/`.

| Método | Vista incorrecta | Vista correcta |
|---|---|---|
| `index()` | `backoffice/admin/animals/index` | `backoffice/keeper/animals/index` |
| `create()` | `backoffice/admin/animals/create` | `backoffice/keeper/animals/create` |
| `edit()` | `backoffice/admin/animals/edit` | `backoffice/keeper/animals/edit` |

**Compatibilidad de datos:** Las vistas del keeper esperan: `id`, `name`, `image_url`, `species_type`, `cafe_name`, `current_status`, `logs_today`. Confirmar que el controlador proporciona estas claves antes de hacer el cambio de ruta.

**Tareas:**

- [ ] Leer `app/Http/Controllers/Admin/AnimalController.php` completo
- [ ] Leer `resources/views/backoffice/keeper/animals/index.php` — confirmar variables esperadas
- [ ] Leer `resources/views/backoffice/keeper/animals/create.php` y `edit.php` — confirmar variables
- [ ] Comparar los arrays de datos del controlador con lo que espera cada vista
- [ ] Si hay variables faltantes en el controlador, añadirlas (usando servicios ya inyectados)
- [ ] Corregir las 3 rutas: sustituir `backoffice/admin/animals/` por `backoffice/keeper/animals/`
- [ ] Ejecutar `make phpstan`
- [ ] Navegar a `/admin/animals` en dev — debe cargar sin error

---

### A3: Admin\ProductController — 3 rutas + adaptación Alpine.js

**Archivo:** `app/Http/Controllers/Admin/ProductController.php`

**Problema:** Tres métodos referencian `management/products/` pero las vistas reales están en `admin/products/`.

| Método | Línea aprox. | Vista incorrecta | Vista correcta |
|---|---|---|---|
| `index()` | L60 | `management/products/index` | `admin/products/index` |
| `create()` | L85 | `management/products/create` | `admin/products/create` |
| `edit()` | L219 | `management/products/edit` | `admin/products/edit` |

**Incompatibilidad de datos:** Las vistas usan `x-data='productManagement(<?= $alpineConfig ?>)'` (Alpine.js). Requieren que `$alpineConfig` sea un `Raw::json()`. El controlador actualmente pasa arrays separados y usa `$title` en lugar de `$titulo`.

**Fix requerido para `index()`:**

```php
// ANTES:
View::render('management/products/index', [
    'title'    => 'Gestión de Productos',
    'products' => $products,
    'cafes'    => $cafes,
], ['products.css'], 'backoffice');

// DESPUÉS:
View::render('admin/products/index', [
    'titulo'      => 'Gestión de Productos',
    'alpineConfig' => Raw::json([
        'products' => $products,
        'cafes'    => $cafes,
    ]),
], ['products.css'], 'backoffice');
```

Aplicar el mismo patrón en `create()` y `edit()`, adaptando las claves del JSON al contrato específico de cada vista.

**Tareas:**

- [ ] Leer `app/Http/Controllers/Admin/ProductController.php` completo
- [ ] Leer `resources/views/admin/products/index.php` — confirmar exactamente qué llaves del JSON Alpine necesita
- [ ] Leer `resources/views/admin/products/create.php` y `edit.php` — confirmar sus variables
- [ ] Verificar que `use App\Core\Raw;` está en el controlador (añadir si no)
- [ ] Corregir `index()`: ruta + construir `$alpineConfig` + renombrar `$title` → `$titulo`
- [ ] Corregir `create()`: misma adaptación
- [ ] Corregir `edit()`: misma adaptación
- [ ] Ejecutar `make phpstan`
- [ ] Navegar a `/admin/products` en dev — debe cargar sin error

---

## Grupo B — Crear vistas faltantes de reservas

**Controlador:** `app/Http/Controllers/Shared/ReservationController.php`

Dos métodos referencian vistas que no existen. Ambas usan el layout `main` (interfaz pública para usuarios autenticados).

### B1: Crear `shared/reservas/confirmation.php`

**Método en el controlador:** `confirmation()` — se llama tras crear una reserva exitosamente.

**Datos que recibe la vista:**

```php
[
    'titulo'      => 'Confirmación de Reserva',
    'reservation' => [
        'id'                     => int,
        'cafe_name'              => string,
        'pass_name'              => string,
        'pass_duration_minutes'  => int,
        'reservation_date'       => 'YYYY-MM-DD',
        'reservation_time'       => 'HH:MM:SS',
        'guest_count'            => int,
        'status'                 => string,  // 'confirmed', 'pending', etc.
        'comments'               => string|null,
    ],
]
```

**Requisitos de la vista:**

- Layout: `main`
- Mostrar todos los datos de la reserva en un formato legible
- Mostrar el número/código de reserva prominentemente (para que el usuario lo anote)
- Botones de acción: "Ver mis reservas" (`/mis-reservas`) y "Volver al inicio" (`/`)
- Formatear fecha: `date('d/m/Y', strtotime($reservation['reservation_date']))`
- Formatear hora: `substr($reservation['reservation_time'], 0, 5)` (quitar segundos)
- Usar `e()` en todas las variables de usuario
- Usar las clases CSS del proyecto (ver `resources/views/shared/reservas/index.php` como referencia de estilo)

**Tareas:**

- [ ] Leer `resources/views/shared/reservas/index.php` — tomar el layout y estructura de navegación como referencia
- [ ] Leer `resources/views/layouts/main.php` — confirmar variables requeridas del layout (p.ej. `$titulo`)
- [ ] Crear `resources/views/shared/reservas/confirmation.php` con contenido completo
- [ ] Verificar que el archivo usa `e()` en todas las variables de usuario
- [ ] ⚠️ Si el layout requiere variables adicionales (como `$user`), confirmar que el controlador las pasa

---

### B2: Crear `shared/reservas/lista.php`

**Método en el controlador:** `userReservations()` — muestra todas las reservas del usuario autenticado.

**Datos que recibe la vista:**

```php
[
    'titulo'      => 'Mis Reservas',
    'reservations' => [
        // Array de reservas, cada una con la misma estructura que en B1
        // Puede ser array vacío []
    ],
]
```

**Requisitos de la vista:**

- Layout: `main`
- Si `$reservations` está vacío, mostrar mensaje "No tienes reservas aún" con enlace a `/reservas`
- Si hay reservas, mostrar una tabla o lista de tarjetas con: café, tipo de pase, fecha, hora, estado, número de personas
- El campo `status` debe tener un badge de color: `confirmed` → verde, `pending` → amarillo, `cancelled` → rojo
- Enlace a cada reserva o botón "Cancelar" para las que están `pending` o `confirmed` (POST a `/mis-reservas/{id}/cancel`)
- Usar `e()` en todas las variables
- Referencia de estilo: `resources/views/shared/reservas/index.php`

**Tareas:**

- [ ] Crear `resources/views/shared/reservas/lista.php` con contenido completo
- [ ] Incluir estado vacío (sin reservas)
- [ ] Incluir badges de estado con colores semánticos
- [ ] Añadir botón "Cancelar" con formulario POST + `<?= Csrf::field() ?>`
- [ ] Verificar que el archivo usa `e()` en todas las variables de usuario

---

## Grupo C — Corregir FEATURE_BACKOFFICE

**Archivo:** `bootstrap/container.php`

**Problema:** `FEATURE_BACKOFFICE` usa comparación de string `=== '1'` en lugar de `Env::bool()`. Si el env var se configura como `true` (booleano), la comparación falla silenciosamente y el módulo backoffice no se registra — incluso si el operador quiso activarlo.

```php
// bootstrap/container.php — fragmento actual (L30 aproximadamente)
// ❌ INCORRECTO:
if (Env::get('FEATURE_BACKOFFICE', '1') === '1') {

// ✅ CORRECTO (igual que FEATURE_KEEPER y FEATURE_OPS):
if (Env::bool('FEATURE_BACKOFFICE', true)) {
```

**Referencia:** En el mismo archivo, líneas ~L35 y ~L39 usan `Env::bool()` correctamente para los otros dos feature flags.

**Tareas:**

- [ ] Leer `bootstrap/container.php` L20–L50 — confirmar la línea exacta del bug
- [ ] Cambiar `Env::get('FEATURE_BACKOFFICE', '1') === '1'` por `Env::bool('FEATURE_BACKOFFICE', true)`
- [ ] Verificar que `Env::bool()` está disponible en el namespace (igual que las otras líneas del mismo archivo)
- [ ] Ejecutar `make test-unit` — debe pasar en verde
- [ ] Commit: `fix: FEATURE_BACKOFFICE usar Env::bool() consistente con otros flags`

---

## Grupo D — Eliminar controladores huérfanos

Estos controladores no tienen ninguna ruta en `app/routes.php`, referencian vistas que no existen y su funcionalidad está cubierta por otros controladores.

### D1: Eliminar Admin\CatalogController

**Archivo:** `app/Http/Controllers/Admin/CatalogController.php`

**Justificación:** Su único método llama a `'backoffice/manager/productos'` (no existe). La funcionalidad de listar/togglear elementos del catálogo está cubierta por `Admin\MenuController`. No tiene ruta.

**Tareas:**

- [ ] Ejecutar `grep -rn "CatalogController"` en todo el proyecto para confirmar que no hay referencias
- [ ] Si no hay referencias: eliminar `app/Http/Controllers/Admin/CatalogController.php`
- [ ] Ejecutar `make phpstan` — sin errores

---

### D2: Eliminar Admin\ManagerController

**Archivo:** `app/Http/Controllers/Admin/ManagerController.php`

**Justificación:**

- `getMonthlyRevenue()` siempre retorna `0.0` (hardcoded, placeholder no implementado)
- `getMonthlyStats()` siempre retorna `[]` (placeholder)
- Las tres vistas que referencia (`backoffice/manager/dashboard`, `personal`, `reportes`) no existen
- No tiene ruta en `app/routes.php`
- La funcionalidad la cubre `Manager\DashboardController` (que sí tiene rutas y vistas reales)

**Tareas:**

- [ ] Ejecutar `grep -rn "Admin\\\\ManagerController"` para confirmar ausencia de referencias
- [ ] Si no hay referencias: eliminar `app/Http/Controllers/Admin/ManagerController.php`
- [ ] Ejecutar `make phpstan` — sin errores
- [ ] Commit conjunto D1+D2: `refactor: eliminar controladores Admin huérfanos CatalogController y ManagerController`

---

## Grupo E — Migración supervisor_assignments INT → BIGINT

**Problema:** Todas las tablas del proyecto usan `BIGINT UNSIGNED` para PKs y FKs. La tabla `supervisor_assignments` usa `INT UNSIGNED` — riesgo de overflow y errores silenciosos en JOINs con tablas que usan BIGINT.

**Archivo a crear:** `migrations/019_fix_supervisor_assignments_bigint.sql`

**Contenido:**

```sql
-- Fix: supervisor_assignments columnas INT → BIGINT para consistencia
-- Todas las demás tablas usan BIGINT UNSIGNED. Ver migration 016_supervisor_assignments.sql

ALTER TABLE supervisor_assignments
  MODIFY COLUMN id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  MODIFY COLUMN supervisor_id  BIGINT UNSIGNED NOT NULL,
  MODIFY COLUMN reservation_id BIGINT UNSIGNED NOT NULL,
  MODIFY COLUMN cafe_id        BIGINT UNSIGNED NOT NULL;
```

**Tareas:**

- [ ] Leer `migrations/016_supervisor_assignments.sql` — confirmar tipos actuales de las columnas
- [ ] Crear `migrations/019_fix_supervisor_assignments_bigint.sql` con el contenido anterior
- [ ] Ejecutar `make db-migrate` en entorno de dev
- [ ] Ejecutar `make db-verify` — debe pasar sin errores
- [ ] Commit: `fix: supervisor_assignments columnas INT → BIGINT para consistencia de esquema`

---

## Verificación final de Fase 0

Ejecutar en este orden tras completar todos los grupos:

```bash
make phpstan          # debe terminar sin errores nuevos (baseline limpia)
make test-unit        # debe pasar en verde
make db-verify        # debe confirmar esquema coherente
```

Verificación manual en dev:

1. Navegar a `/admin/reviews` — debe cargar la vista de revisiones pendientes
2. Navegar a `/admin/animals` — debe cargar el listado de animales del keeper
3. Navegar a `/admin/products` — debe cargar la gestión de productos con Alpine.js
4. Crear una reserva de prueba → debe redirigir a `/reservas/confirmacion/{id}` y mostrar los datos
5. Navegar a `/mis-reservas` → debe mostrar la lista de reservas
6. Poner `FEATURE_BACKOFFICE=true` (booleano) en `.env` → debe seguir activando el módulo backoffice

---

## Commits esperados

```
fix: corregir ruta vista en Admin\ReviewController y adaptar datos para Alpine.js
fix: corregir 3 rutas de vistas en Admin\AnimalController (admin→keeper)
fix: corregir 3 rutas de vistas en Admin\ProductController y adaptar $alpineConfig
feat: crear vistas shared/reservas/confirmation y shared/reservas/lista
fix: FEATURE_BACKOFFICE usar Env::bool() consistente con otros flags
refactor: eliminar controladores Admin huérfanos CatalogController y ManagerController
fix: supervisor_assignments columnas INT → BIGINT para consistencia de esquema
```

## Siguiente fase

Una vez completada la Fase 0 y pasadas todas las verificaciones, continuar con:

- **Plan 1** (`2026-04-10-plan1-security.md`) — Security & Hardening
- **Plan 4** (`2026-04-10-plan4-reservation-repos.md`) — ReservationService → Repos
- **Plan 5** (`2026-04-10-plan5-tests.md`) — Cobertura de tests

Los tres planes de Fase 1 son independientes entre sí y pueden ejecutarse en paralelo.
Ver `2026-04-10-plan-maestro.md` para el árbol completo de dependencias.
