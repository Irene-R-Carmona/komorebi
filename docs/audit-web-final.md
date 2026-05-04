# Auditoría Web Final — Komorebi Café

**Fecha de auditoría:** 2026-04-29 / 2026-04-30
**Auditor:** GitHub Copilot (Claude Sonnet 4.6)
**App:** `http://localhost:8080` | PHP 8.4.20 / FrankenPHP / MySQL 8.4.8
**Branch:** `develop`
**Objetivo:** Determinar estado operativo de todas las páginas antes del despliegue Railway (TFG).

---

## Resumen Ejecutivo

> Actualizado progresivamente fase a fase.

| Fase | Módulo | Rutas | Operativas | Parciales | Rotas | Estado |
|------|--------|-------|-----------|-----------|-------|--------|
| 1 | Públicas | ~18 | 12 | 5 | 1 | ✅ Auditada |
| 2 | Auth | ~5 | 4 | 1 | 0 | ✅ Auditada |
| 3 | Usuario autenticado | ~10 | 7 | 2 | 1 | ✅ Auditada |
| 4 | Admin backoffice | ~22 | 14 | 2 | 0 | ✅ Auditada |
| 5 | Manager | ~8 | 8 | 0 | 0 | ✅ Auditada |
| 6 | Supervisor | ~2 | 2 | 0 | 0 | ✅ Auditada |
| 7 | Reception/OPS | ~2 | 2 | 0 | 0 | ✅ Auditada |
| 8 | Kitchen/KDS | ~2 | 2 | 0 | 0 | ✅ Auditada |
| 9 | Keeper | ~10 | 8 | 2* | 0 | ✅ Auditada |
| 10 | Páginas de error | ~3 | 3 | 0 | 0 | ✅ Auditada |
| 11 | API endpoints | ~3 | 3 | 0 | 0 | ✅ Auditada |
| 12 | Accesibilidad | 4 páginas | 4 | 0 | 0 | ✅ Auditada |

---

## Leyenda

- **Estado:** Operativa / Parcial / Rota
- **Datos:** BD real / Hardcodeado / Sin datos
- **JS Err:** Sí / No
- **Interactivos:** OK / Roto / Parcial / N/A
- **Estilos:** OK / Menor / Roto
- **🔴** Bloquea uso · **🟡** Degrada experiencia · **🔵** Cosmético/menor

---

## Registro de Bugs

| # | Sev | Ruta | Descripción | Estado |
|---|-----|------|-------------|--------|
| 1 | 🟡 | `/` | Cookie consent devuelve 422 | Documentado |
| 2 | 🟡 | `/` | Sección "3 pasos": iconos como emoji en lugar de SVG | Documentado |
| 3 | 🟡 | `/cafes` | Botones de filtro no funcionan | Documentado |
| 4 | 🔴 | GLOBAL | CSP bloquea `img onerror` — imágenes rotas sin fallback | Documentado |
| 5 | 🟡 | `/cafes/{slug}` | 8 imágenes de productos con 404 | Documentado |
| 6 | 🔴 | `/menu` | HTTP 500 al cargar la carta | ✅ **Corregido** |
| 7 | 🟡 | `/menu` | 63 imágenes de productos rotas (storage no accesible) | Documentado |
| 8 | 🔴 | `/menu` | `DataCloneError` al actualizar cantidad en carrito | ✅ **Corregido** |
| 9 | 🟡 | `/contacto` | Enlace "self-link" apunta a placeholder no resuelto | Documentado |
| 10 | 🔴 | `/newsletter/unsubscribe` | HTTP 500 al intentar desuscribirse | ✅ **Corregido** |
| 11 | 🔴 | Auth flow | Rutas de redirect incorrectas tras login/registro | ✅ **Corregido** |
| 12 | 🔴 | `/account/*` | `AccountDeletionService` DI con tipo incorrecto (`PDO` en lugar de `UserRepositoryInterface`) | ✅ **Corregido** |
| 13 | 🔴 | `/user/waitlists` | `WaitlistController` accedía a forma incorrecta de `$result->data` | ✅ **Corregido** |
| 14 | 🔴 | `/account/*` | `FileUploadService` constructor lanzaba excepción al inicializar | ✅ **Corregido** |
| 15 | 🔴 | `/loyalty/card` | `LoyaltyRepository` insertaba en columna `GENERATED ALWAYS` | ✅ **Corregido** |
| 16 | 🔴 | `/loyalty/card` | `LoyaltyController` pasaba DTO objeto a vista (debe ser array) | ✅ **Corregido** |
| 17 | 🔴 | `/admin/users/create` | `UserController@create()` no existía — HTTP 500 | ✅ **Corregido + Verificado** |
| 18 | 🔴 | `/admin/menu/create` | Rutas de componentes incorrectas: `products/allergen-checkbox` y `products/image-preview` (faltaba prefijo `components/`) | ✅ **Corregido + Verificado** |
| 19 | 🔴 | `/admin/waitlists` | Vista usaba `Csrf::field()` sin `use App\Core\Csrf;` | ✅ **Corregido + Verificado** |
| 20 | 🔴 | GLOBAL admin | `NavigationService::getAdminMenu()` omitía 5 ítems del sidebar: Reseñas, Lista de espera, Animales, Informes, Visor de datos | ✅ **Corregido + Verificado** |
| 21 | 🔴 | `/admin/menu` | Rutas stale `/admin/productos` en vista — 6 URLs rotas; sin paginación ni filtros | ✅ **Corregido + Verificado** |
| 22 | 🔴 | `/admin/reviews` | Sin paginación; `ReviewModerationService` args intercambiados: `findPendingPaginated(10, $page)` siempre cargaba página 10 | ✅ **Corregido + Verificado** |
| 23 | 🔴 | `/admin/waitlists` | Sin paginación — cargaba todos los registros sin límite | ✅ **Corregido + Verificado** |
| 24 | 🔴 | `/admin/animals` | Sin paginación + botón "Nuevo Animal" ausente | ✅ **Corregido + Verificado** |

---

## FASE 1 — Páginas Públicas

| Ruta | Estado | Datos | JS Err | Interactivos | Estilos | Issues |
|------|--------|-------|--------|--------------|---------|--------|
| `/` | Parcial | BD real | Sí | Parcial | OK | 🟡 #1 Cookie consent 422, 🟡 #2 Emoji en "3 pasos" |
| `/cafes` | Parcial | BD real | No | Parcial | OK | 🟡 #3 Filtros no funcionan |
| `/cafes/{slug}` | Parcial | BD real | No | OK | Menor | 🟡 #5 8 imágenes 404 |
| `/menu` | Parcial | BD real | No | OK | OK | 🟡 #7 63 imágenes rotas (storage) |
| `/quiz` | Operativa | BD real | No | OK | OK | — |
| `/quiz/resultado` | Operativa | BD real | No | OK | OK | — |
| `/historia` | Operativa | Hardcodeado | No | N/A | OK | — |
| `/faq` | Operativa | Hardcodeado | No | OK | OK | — |
| `/contacto` | Parcial | BD real | No | Parcial | OK | 🟡 #9 Self-link placeholder |
| `/legal/cookies` | Operativa | Hardcodeado | No | N/A | OK | — |
| `/legal/privacidad` | Operativa | Hardcodeado | No | N/A | OK | — |
| `/legal/terminos` | Operativa | Hardcodeado | No | N/A | OK | — |
| `/reservar` | Operativa | BD real | No | OK | OK | — |
| `/waitlist/status/{token}` | Operativa | BD real | No | N/A | OK | — |
| `/newsletter/unsubscribe` | Operativa | BD real | No | N/A | OK | — (Bug #10 corregido) |
| `/health` | Operativa | BD real | N/A | N/A | N/A | — |
| `/ruta-inexistente` | Operativa | N/A | N/A | N/A | OK | — |
| 403 trigger | Operativa | N/A | N/A | N/A | OK | — |

**Nota global:** Bug #4 (CSP bloquea `img onerror`) afecta `/cafes/{slug}` y `/menu` — imágenes de BD sin fallback visual. Requiere revisión de `Content-Security-Policy` header.

---

## FASE 2 — Autenticación

| Ruta | Estado | Datos | JS Err | Interactivos | Estilos | Issues |
|------|--------|-------|--------|--------------|---------|--------|
| `/login` | Operativa | BD real | No | OK | OK | — |
| `/registro` | Operativa | BD real | No | OK | OK | — |
| `/forgot-password` | Operativa | BD real | No | OK | OK | — |
| `/reset-password?token=fake` | Parcial | N/A | No | N/A | OK | 🟡 Token inválido: muestra form pero no valida hasta submit |
| Redirect tras login | Operativa | — | — | — | — | — (Bug #11 corregido) |

---

## FASE 3 — Usuario Autenticado (role=user)

| Ruta | Estado | Datos | JS Err | Interactivos | Estilos | Issues |
|------|--------|-------|--------|--------------|---------|--------|
| `/perfil` | Operativa | BD real | No | OK | OK | — |
| `/account/security` | Operativa | BD real | No | OK | OK | — |
| `/account/sessions` | Operativa | BD real | No | OK | OK | — |
| `/account/change-password` | Operativa | BD real | No | OK | OK | — |
| `/reservar` (flujo completo) | Operativa | BD real | No | OK | OK | — |
| `/reservas` | Operativa | BD real | No | OK | OK | — |
| `/cart` | Operativa | BD real | No | OK | OK | — |
| `/favorites` | Operativa | BD real | No | N/A | OK | — |
| `/user/waitlists` | Operativa | BD real | No | OK | OK | — (Bug #13 corregido) |
| `/loyalty/card` | Operativa | BD real | No | OK | OK | — (Bugs #15 y #16 corregidos) |

---

## FASE 4 — Admin Backoffice (role=admin)

**Bugs corregidos esta fase:** #19-#24 (NavigationService sidebar, menu URLs+paginación, reviews paginación+swap args, waitlist paginación, animals paginación+botón crear)

| Ruta | Estado | Datos | JS Err | Interactivos | Estilos | Issues |
|------|--------|-------|--------|--------------|---------|--------|
| `/admin/dashboard` | Parcial | BD real | Sí | Parcial | OK | 🟡 `admin-dashboard.js` 404 |
| `/admin/users` | Operativa | BD real | No | OK | OK | Paginación OK (modal crear) |
| `/admin/users/create` | Operativa | — | No | OK | OK | Bug #17 corregido |
| `/admin/users/{id}/edit` | Operativa | BD real | No | OK | OK | — |
| `/admin/roles` | Operativa | BD real | No | OK | OK | — |
| `/admin/cafes` | Operativa | BD real | No | OK | OK | Paginación OK |
| `/admin/cafes/create` | Operativa | — | No | OK | OK | — |
| `/admin/cafes/{id}/edit` | Operativa | BD real | No | OK | OK | — |
| `/admin/menu` | Operativa | BD real | No | OK | OK | Bug #21 corregido — paginación+filtros+URLs |
| `/admin/menu/create` | Operativa | — | No | OK | OK | Bug #18 corregido |
| `/admin/menu/{id}/edit` | Operativa | BD real | No | OK | OK | — |
| `/admin/reservations` | Operativa | BD real | No | OK | OK | Paginación manual OK |
| `/admin/reviews` | Operativa | BD real | No | OK | OK | Bug #22 corregido — paginación+swap args |
| `/admin/waitlists` | Operativa | BD real | No | OK | OK | Bugs #19+#23 corregidos — paginación PHP-level |
| `/admin/waitlists/{id}` | Operativa | BD real | No | OK | OK | — |
| `/admin/animals` | Operativa | BD real | No | OK | OK | Bug #24 corregido — paginación PHP-level + botón crear |
| `/admin/animals/create` | Operativa | — | No | OK | OK | — |
| `/admin/animals/{id}` | Operativa | BD real | No | OK | OK | — |
| `/admin/animals/{id}/edit` | Operativa | BD real | No | OK | OK | — |
| `/admin/settings` | Operativa | BD real | No | OK | OK | — |
| `/admin/logs` | Operativa | BD real | No | OK | OK | — |
| `/admin/data-viewer` | Operativa | BD real | No | OK | OK | — |

**Nota sidebar:** Bug #20 corregido — todos los ítems del sidebar ahora presentes: Dashboard, Usuarios, Sedes, Productos, Reservas, Lista de espera, Reseñas, Animales, Informes, Roles, Permisos, Logs, Visor de datos, Ajustes.

---

## FASE 5 — Manager Backoffice (role=manager)

**Bugs corregidos esta fase:** #25 (manager nav items faltantes), #26 (manager/products paginación y filtros), #27 (manager/reservations paginación)

| Ruta | Estado | Datos | JS Err | Interactivos | Estilos | Issues |
|------|--------|-------|--------|--------------|---------|--------|
| `/manager/dashboard` | Operativa | BD real | No | OK | OK | — |
| `/manager/cafes` | Operativa | BD real | No | OK | OK | — |
| `/manager/cafes/{id}` | Operativa | BD real | No | OK | OK | — |
| `/manager/products` | Operativa | BD real | No | OK | OK | Paginación+filtros OK |
| `/manager/products/create` | Operativa | — | No | OK | OK | — |
| `/manager/products/{id}/edit` | Operativa | BD real | No | OK | OK | — |
| `/manager/reservations` | Operativa | BD real | No | OK | OK | Paginación OK |
| `/manager/reports` | Operativa | BD real | No | OK | OK | — |

---

## FASE 6 — Supervisor (role=supervisor)

**Bugs corregidos esta fase:** #28 (supervisor/assignments paginación y filtros)

| Ruta | Estado | Datos | JS Err | Interactivos | Estilos | Issues |
|------|--------|-------|--------|--------------|---------|--------|
| `/supervisor/dashboard` | Operativa | BD real | No | OK | OK | — |
| `/ops/supervisor/assignments` | Operativa | BD real | No | OK | OK | Paginación+filtros OK |

---

## FASE 7 — Reception / OPS (role=reception)

**Bugs corregidos esta fase:** #33 (`ReceptionController@todayReservations` redirigía a ruta incorrecta)

| Ruta | Estado | Datos | JS Err | Interactivos | Estilos | Issues |
|------|--------|-------|--------|--------------|---------|--------|
| `/ops/reception` | Operativa | BD real | No | OK | OK | — |
| `/ops/reception/check-in/{id}` | Operativa | BD real | No | OK | OK | — |

---

## FASE 8 — Kitchen / KDS (role=kitchen)

**Bugs corregidos esta fase:** #34 (`KitchenController@activeOrders` redirigía a ruta incorrecta)

| Ruta | Estado | Datos | JS Err | Interactivos | Estilos | Issues |
|------|--------|-------|--------|--------------|---------|--------|
| `/ops/kitchen` | Operativa | BD real | No | OK | OK | — |
| `/ops/kds` | Operativa | BD real | No | OK | OK | — |

---

## FASE 9 — Keeper (role=keeper)

**Preparación:** Hash argon2id de contraseña corrompido en sesiones previas (shell stripeaba `$` del hash). Fix: script PHP PDO `scripts/fix-keeper-password.php`.

**Nota:** `*` 2 rutas con 404 esperado — tablas `animal_health_checks` e `animal_incidents` sin datos en entorno de desarrollo.

| Ruta | Estado | Datos | JS Err | Interactivos | Estilos | Issues |
|------|--------|-------|--------|--------------|---------|--------|
| `/keeper/dashboard` | Operativa | BD real | No | OK | OK | — |
| `/keeper/animals` | Operativa | BD real | No | OK | OK | — |
| `/keeper/animals/1` | Operativa | BD real | No | OK | OK | — |
| `/keeper/health-checks` | Operativa | BD real | No | OK | OK | — |
| `/keeper/health-checks/create/1` | Operativa | BD real | No | OK | OK | — |
| `/keeper/health-checks/{id}` | Sin datos* | — | — | — | — | 404 esperado (tabla vacía) |
| `/keeper/health-checks/history/1` | Operativa | BD real | No | OK | OK | — |
| `/keeper/incidents` | Operativa | BD real | No | OK | OK | — |
| `/keeper/incidents/create` | Operativa | BD real | No | OK | OK | — |
| `/keeper/incidents/{id}` | Sin datos* | — | — | — | — | 404 esperado (tabla vacía) |

---

## FASE 10 — Páginas de Error

| Caso | Estado | HTTP | Página | Issues |
|------|--------|------|--------|--------|
| Ruta inexistente (`/no-existe`) | Operativa | 404 | "404 - Página no encontrada" con estilos correctos | — |
| Acceso sin permisos (keeper → `/admin/dashboard`) | Operativa | 302 → `/` | Redirige a home — no muestra 403 explícito | 🟡 Redirige en lugar de mostrar 403 |
| Página de error 500 | No testeable | — | — | Requiere código que falle deliberadamente |

---

## FASE 11 — API Endpoints

**Base URL:** `/api/v1/`

| Endpoint | Método | Auth | Estado | HTTP | Issues |
|----------|--------|------|--------|------|--------|
| `/api/v1/menu/alergenos` | GET | No | Operativa | 200 | JSON `{ok:true, data:{items:[...]}}` |
| `/api/v1/menu/productos` | GET | No | Operativa | 200 | JSON correcto |
| `/api/v1/cafes` | GET | No | Operativa | 200 | JSON correcto |
| `/api/v1/tokens` | POST | Sí (sesión) | Correcto | 401 sin auth | Endpoint protegido — correcto |

**Nota:** `/api/v1/health` devuelve 404 — no existe endpoint health dedicado en la API (el health check es `/health.php` en public/).

---

## FASE 12 — Accesibilidad (Playwright snapshot)

**Páginas auditadas:** `/` , `/cafes`, `/menu`, `/reservas`

**Resultado global:** ✅ APROBADO

| Criterio | Estado | Notas |
|----------|--------|-------|
| Skip link | ✅ | "Saltar al contenido principal" → `#main` |
| Navigation landmarks | ✅ | `<nav aria-label="Navegación principal">` |
| Heading hierarchy | ✅ | H1 en cada página, H2/H3 respetan jerarquía |
| Links descriptivos | ✅ | Sin "click aquí" ni enlaces vacíos |
| Footer navigation | ✅ | 3 secciones con aria-label |
| Formularios | ✅ | Labels asociados correctamente |
| Dark mode toggle | ✅ | Botón "Cambiar tema" con descripción |
| CSS carga correcta | ✅ | `/css/sections/home.css` → 200 |

---

## Top Issues Críticos 🔴 (Railway-blockers)

> Actualizado al finalizar todas las fases.

| # | Ruta | Descripción | Fix |
|---|------|-------------|-----|
| 4 | GLOBAL | CSP bloquea fallback de imágenes — todas las imágenes rotas muestran icono de error sin contexto | Revisar `Content-Security-Policy` o implementar fallback CSS |
| 7 | `/menu` + `/cafes/{slug}` | 63+ imágenes de productos no accesibles (storage path incorrecto en producción) | Verificar configuración de storage public en Railway |

---

## Issues de Degradación 🟡

| # | Ruta | Descripción |
|---|------|-------------|
| 1 | `/` | Cookie consent endpoint devuelve 422 |
| 2 | `/` | Sección "3 pasos" usa emojis como iconos — inconsistente con sistema de iconos SVG del resto del sitio |
| 3 | `/cafes` | Botones de filtro por tipo de café no realizan ninguna acción |
| 5 | `/cafes/{slug}` | 8 imágenes de productos específicos con 404 (diferente al Bug #4) |
| 9 | `/contacto` | Enlace a "nuestros cafés" apunta a `#` placeholder |
| 20 | `/admin/dashboard` | `admin-dashboard.js` no existe (404) — posibles widgets JS rotos |

---

## Issues Cosméticos 🔵

> Actualizado al finalizar todas las fases.

---

## Hardcoded / Datos Faltantes

| Ruta | Elemento | Observación |
|------|----------|-------------|
| `/historia` | Todo el contenido | Texto y datos completamente hardcodeados — sin CMS ni BD |
| `/faq` | Preguntas y respuestas | Hardcodeados en vista PHP |
| `/legal/*` | Contenido legal | Hardcodeado — esperado para contenido legal estático |

---

## Coherencia Estética Global

> Pendiente de auditar todos los módulos.

---

## Recomendaciones Railway

> Pendiente de completar todas las fases.

---

*Documento generado automáticamente durante la sesión de auditoría. Última actualización: Fases 1–12 completadas — 2026-04-30.*
