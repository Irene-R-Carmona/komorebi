# Plan: Cierre de Gaps Arquitectónicos — FASE 0

**Fecha:** 2026-04-13
**Estado:** ✅ Completado
**Rama:** `feature/cierre-gaps-arquitectonicos`

---

## Contexto

Tras la revisión exhaustiva de FASE 0 (Principios Arquitectónicos), se identificaron 4 gaps reales
que quedan fuera de los Streams ya completados:

| Gap | Categoría           | Impacto                                               |
|-----|---------------------|-------------------------------------------------------|
| 1   | Documentación API   | OpenAPI sin spec para 18 endpoints `/api/v1/`        |
| 2   | Contrato de servicio| `ReviewService::deleteReview()` devuelve `bool\|Result` |
| 3   | Inyección concreta  | `ClimaContextoService` recibe `WeatherService` directo |
| 4   | Admin JS/rutas      | 9 fetch calls con URLs incorrectas (`/api/admin/`)   |

---

## GAP 3 — WeatherServiceInterface en container.php

**Archivo:** `bootstrap/container.php`

**Problema:** `ClimaContextoService` recibe el concreto `WeatherService::class` en lugar de
la interfaz `WeatherServiceInterface::class`. Viola el principio de inversión de dependencias
y hace imposible mockear la dependencia en tests.

**Cambios:**

1. Añadir `use App\Services\Contracts\WeatherServiceInterface;` en imports
2. Cambiar `Container::make(WeatherService::class)` → `Container::make(WeatherServiceInterface::class)`
   en la definición de `ClimaContextoService`

**Estado:** ✅ Completado

---

## GAP 2 — ReviewService siempre devuelve Result

**Archivos:**

- `app/Services/ReviewService.php`
- `app/Services/Contracts/ReviewServiceInterface.php`
- `tests/Unit/Services/ReviewServiceTest.php`

**Problema:** `deleteReview()` tiene comportamiento dual:

- Con `$userId === null` → devuelve `bool` (rama legacy para tests)
- Con `$userId !== null` → devuelve `Result`

Viola el contrato de servicio ("todos los métodos devuelven `Result` o tipo puro").

**Cambios:**

1. `ReviewService`: Cambiar la rama `$userId === null` para devolver `Result::ok(null)` / `Result::fail()`
2. `ReviewServiceInterface`: Cambiar firma de `bool|Result` → `Result`
3. `ReviewServiceTest`: Renombrar `testDeleteReviewReturnsBoolean` → `testDeleteReviewReturnsResult`,
   cambiar `assertTrue($result)` → `assertInstanceOf(Result::class, $result)` + `assertTrue($result->ok)`

**Estado:** ✅ Completado

---

## GAP 4 — URLs incorrectas en admin JS

**Archivos:**

- `public/js/sections/admin/admin-logs.js`
- `public/js/sections/admin/admin-reports.js`
- `app/routes.php`
- `app/Http/Controllers/Admin/AuthLogController.php`

**Problema:** Los Alpine components llaman a `/api/admin/...` pero todas las rutas admin
están en `/admin/...` (sin prefijo `/api/`). Además faltan rutas para sub-endpoints cuyos
métodos de controlador ya existen.

**Fetch calls en admin-logs.js (6 total):**

| URL original                           | URL corregida                       | Ruta existe |
|----------------------------------------|-------------------------------------|-------------|
| `GET /api/admin/logs/audit?...`        | `GET /admin/logs/audit?...`         | ✅ (añadir header `X-Requested-With`) |
| `GET /api/admin/logs/audit/export?...` | `GET /admin/logs/audit/export?...`  | ⬜ → añadir |
| `GET /api/admin/logs/auth?...`         | `GET /admin/logs/auth?...`          | ✅ (añadir header `X-Requested-With`) |
| `GET /api/admin/logs/auth/suspicious-count` | `GET /admin/logs/auth/suspicious-count` | ⬜ → añadir |
| `GET /api/admin/logs/auth/export?...`  | `GET /admin/logs/auth/export?...`   | ⬜ → añadir |
| `POST /api/admin/security/block-ip`    | `POST /admin/security/block-ip`     | ⬜ → stub 501 |

**Fetch calls en admin-reports.js (3 total):**

| URL original                          | URL corregida                           |
|---------------------------------------|-----------------------------------------|
| `POST /api/admin/reports/export/pdf`  | `GET /admin/reports/export?format=pdf`  |
| `POST /api/admin/reports/export/excel`| `GET /admin/reports/export?format=excel`|
| `POST /api/admin/reports/export/csv`  | `GET /admin/reports/export?format=csv`  |

**Cambios:**

1. `admin-logs.js`: Corregir 6 URLs + añadir `X-Requested-With: XMLHttpRequest`
   en las llamadas de lista + corregir extracción de datos del JSON (`data.data?.logs`)
2. `admin-reports.js`: Cambiar 3 llamadas POST con body JSON → GET con query params
3. `routes.php`: Añadir rutas para audit/export, auth/export, auth/suspicious-count, security/block-ip
4. `AuthLogController`: Añadir `suspiciousCount()` método que devuelve `{ok: true, count: int}`

**Estado:** ✅ Completado

---

## GAP 1 — OpenAPI spec para 18 endpoints /api/v1/

**Archivo:** `docs/openapi.yaml`

**Problema:** Los controladores en `app/Http/Controllers/Api/V1/` están implementados
pero ninguno de sus endpoints está documentado en el OpenAPI spec.

**Endpoints a documentar (18):**

```
/api/v1/menu/items           GET  — lista items de menú
/api/v1/menu/items/{id}      GET  — detalle item
/api/v1/menu/alergenos       GET  — lista alérgenos
/api/v1/cookies/preferences  GET  — obtener preferencias
/api/v1/cookies/preferences  POST — guardar preferencias
/api/v1/cookies/consent      POST — registrar consentimiento
/api/v1/cookies/withdraw     POST — retirar consentimiento
/api/v1/cart                 GET  — obtener carrito
/api/v1/cart/items           POST — añadir al carrito
/api/v1/cart/items/{id}      PUT  — actualizar cantidad
/api/v1/cart/items/{id}      DELETE — eliminar del carrito
/api/v1/cart/clear           DELETE — vaciar carrito
/api/v1/newsletter/subscribe POST — suscribirse newsletter
/api/v1/favorites            GET  — lista favoritos
/api/v1/favorites/{id}       POST — añadir favorito
/api/v1/reservations         GET  — historial reservas
/api/v1/reservations/{id}    GET  — detalle reserva
/api/v1/loyalty/points       GET  — puntos lealtad
/api/v1/loyalty/history      GET  — historial puntos
/api/v1/loyalty/rewards      GET  — recompensas disponibles
```

**Estado:** ✅ Completado (ya estaba documentado — 38/38 endpoints en `docs/openapi.yaml`)

---

## Orden de implementación

1. ✅ GAP 3 (trivial — 2 líneas)
2. ✅ GAP 2 (medium — 3 archivos)
3. ✅ GAP 4 (medium — 4 archivos + controller method)
4. ✅ GAP 1 (ya existía — 38/38 endpoints documentados)

---

## Verificación

```bash
make phpstan     # sin nuevos errores tras GAP 2 y 3
make test-unit   # testDeleteReviewReturnsResult debe pasar
```

### Resultados de verificación

| Herramienta | Resultado |
|-------------|----------|
| PHPUnit (9 tests) | ✅ OK (9 tests, 22 assertions) |
| PHPStan level 5 (archivos modificados) | ✅ No errors |
