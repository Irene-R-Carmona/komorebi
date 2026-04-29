# Plan: Auditoría web completa pre-entrega TFG

**Estado:** 🔵 Plan creado — pendiente inicio
**Fecha:** 2026-04-29
**Objetivo:** Revisar todas las páginas web (públicas y privadas) para determinar cuáles están operativas y funcionales, cuáles no, cuáles a medias, cuáles usan datos reales de BD o tienen datos hardcodeados, y cuáles necesitan revisión de estilos/accesibilidad. Es el último plan antes de la defensa del TFG y el despliegue a Railway.
**Output:** `docs/audit-web-final.md` — tabla completa por página + issues priorizados

---

## Contexto

- **App:** `http://localhost:8080` (FrankenPHP, Docker, `make dev`)
- **Vistas:** 153 archivos PHP en `resources/views/`
- **Rutas:** ~130 rutas agrupadas en `app/routes/` (public, auth, admin, ops)
- **Roles:** admin, manager, supervisor, reception, kitchen, keeper, user (9 roles)
- **Features activas:** `FEATURE_BACKOFFICE=1`, `FEATURE_OPS=1`, `FEATURE_KEEPER=1`
- **Stack con datos:** seeded para todos los roles

---

## Herramientas por fase

| Herramienta | Uso |
|-------------|-----|
| `pla_browser_navigate` | Abrir página |
| `pla_browser_screenshot` (1280px, escala 0.5) | Captura visual reducida — adjuntable al razonamiento |
| `pla_browser_click` / `pla_browser_hover` | Abrir dropdowns, modals, accordions, menús |
| `pla_browser_snapshot` | Árbol accesibilidad/DOM tras interacciones |
| `chr_list_console_messages` | Errores JS en consola |
| `chr_list_network_requests` | Peticiones de red / errores API |
| `chr_lighthouse_audit` | Solo en 4 páginas públicas clave |

---

## Protocolo de screenshot

- Resolución: **1280px ancho, escala 0.5** (~640px) para adjuntar en razonamiento
- Scroll al top antes de capturar (above-the-fold)
- Screenshot adicional tras cada interacción relevante (modal abierto, dropdown desplegado, accordion expandido)

---

## Protocolo de interactividad por página

Para cada página, probar todos los elementos interactivos detectados:

1. Desplegables / selects / dropdowns → click → screenshot abierto
2. Modales (CRUD, confirmación, detalle) → trigger → screenshot con modal visible
3. Accordions / FAQ → expandir todos → screenshot
4. Tabs → navegar a cada tab → screenshot
5. Formularios → probar validación client-side (submit con campo requerido vacío)
6. Filtros / búsqueda → aplicar filtro → screenshot con resultados
7. Paginación → navegar a página 2 si existe
8. Tooltips / popovers → hover sobre iconos de ayuda

---

## Criterios de evaluación por página

| Columna | Valores |
|---------|---------|
| **Estado** | Operativa / Parcial / Rota / No auditada |
| **Datos** | BD real / Hardcodeado o mixto / Sin datos o vacía |
| **JS Errors** | Sí / No |
| **Interactivos** | OK / Roto / Parcial |
| **A11y** | OK / Issues / Crítico |
| **Estilos** | OK / Roto / Menor |
| **Issues** | 🔴 Bloquea uso · 🟡 Degradado · 🔵 Cosmético |

---

## Checklist de diseño profesional por página

### Accesibilidad — CRÍTICA (ui-ux-pro-max Priority 1)
- [ ] Contraste mínimo 4.5:1 en texto normal
- [ ] Focus rings visibles en elementos interactivos (no eliminados con `outline: none`)
- [ ] Alt text descriptivo en imágenes significativas
- [ ] ARIA labels en botones icon-only
- [ ] Orden de headings secuencial h1→h6
- [ ] Orden lógico de lectura para screen readers

### Interacción — CRÍTICA (ui-ux-pro-max Priority 2 + 8)
- [ ] Botones async desactivados + spinner durante la operación
- [ ] Touch targets mínimo 44×44px
- [ ] `cursor:pointer` en todos los elementos clicables
- [ ] Labels visibles en formularios (no solo placeholder)
- [ ] Errores de validación junto al campo afectado
- [ ] Campos requeridos marcados con indicador (`*` o equivalente)
- [ ] Empty states: mensaje útil + acción (no pantalla en blanco)
- [ ] Toasts con auto-dismiss 3–5s
- [ ] Modales con escape route (botón cancelar / tecla Esc)
- [ ] Confirmación antes de acciones destructivas

### Estilo y consistencia visual (ui-ux-pro-max Priority 4 + 6)
- [ ] Sin emojis en UI — sustituir por símbolo tipográfico o icono SVG
- [ ] Un solo set de iconos (mismo trazo, grosor, esquinas) en todo el producto
- [ ] Una única acción primaria por pantalla (CTAs secundarios subordinados)
- [ ] Sombras/elevaciones con escala consistente
- [ ] Sistema tipográfico: escala definida (12/14/16/18/24/32), sin tamaños ad-hoc
- [ ] Tokens semánticos CSS (`--color-primary`, `--color-error`) en lugar de hex raw
- [ ] Sin texto cuerpo menor de 12px

### Layout y responsive (ui-ux-pro-max Priority 5)
- [ ] Sin scroll horizontal en 1280px
- [ ] `max-width` de contenedores consistente entre páginas
- [ ] Navbar/topbar fija reserva padding al contenido
- [ ] Sin z-index overlapping no intencionados

### Navegación (ui-ux-pro-max Priority 9)
- [ ] Estado activo visible en nav lateral/topbar
- [ ] Items de nav: icono + etiqueta de texto (nunca icon-only)
- [ ] Back/cancelar predecible en flujos multi-paso y modales
- [ ] Breadcrumbs en backoffice con jerarquía profunda

### Coherencia estética del producto (frontend-design + interface-design)
- [ ] Identidad visual unificada entre módulos (público, backoffice, OPS, keeper)
- [ ] Tipografía con personalidad coherente con el concepto café
- [ ] Paleta de color consistente entre todos los módulos
- [ ] KPIs en dashboards comunican significado, no solo número-sobre-etiqueta

---

## Fases de ejecución

### FASE 0 — Verificación del entorno
- [ ] GET `http://localhost:8080/health` → confirmar JSON con `db`, `redis`, `queue` todos `ok`
- [ ] Localizar credenciales seeded en `scripts/` o seeders → recopilar email+password por rol
- [ ] Confirmar en `.env`: `FEATURE_BACKOFFICE=1`, `FEATURE_OPS=1`, `FEATURE_KEEPER=1`

### FASE 1 — Páginas públicas (sin login) — ~18 rutas

- [ ] `/` (home) — hero, widgets clima/estación, newsletter popup, datos café
- [ ] `/cafes` — listado carga con datos BD (nombres, fotos, slugs reales)
- [ ] `/cafes/{slug}` — reseñas, experiencias, formulario de reseña, galería
- [ ] `/menu` — carta con categorías y alérgenos desde BD
- [ ] `/quiz` — flujo completo funciona
- [ ] `/quiz/resultado` — resultado personalizado visible
- [ ] `/historia`
- [ ] `/faq` — accordions expandibles
- [ ] `/contacto` — formulario de contacto funcional
- [ ] `/legal/cookies`
- [ ] `/legal/privacidad`
- [ ] `/legal/terminos`
- [ ] `/reservar` — formulario paso-1, slots reales cargados
- [ ] `/waitlist/status/{token}` — con token real o fake
- [ ] `/newsletter/unsubscribe` — con token
- [ ] `/health` — JSON con todos los servicios `ok`
- [ ] `/ruta-inexistente` — página 404 con layout y estilos correctos
- [ ] Trigger 403 — página con layout correcto

### FASE 2 — Autenticación — ~5 rutas

- [ ] `/login` — layout, validación, mensaje de error con credenciales incorrectas
- [ ] `/registro` — formulario completo, validaciones JS activas
- [ ] `/forgot-password` — formulario envía, Mailpit recibe email
- [ ] `/reset-password?token=fake` — carga vista o rompe con 500
- [ ] `/verify-email` — si existe usuario sin verificar

Acciones:
- [ ] Login con credenciales inválidas → mensaje de error visible
- [ ] Login correcto como `user` → redirect correcto

### FASE 3 — Usuario autenticado (role=user) — ~10 rutas

- [ ] `/perfil` — datos del usuario cargados desde BD
- [ ] `/account/security`
- [ ] `/account/sessions`
- [ ] `/account/change-password`
- [ ] `/reservar` → paso 1 → paso 2 → paso 3 → confirmación (flujo completo)
- [ ] `/reservas` — lista reservas del usuario
- [ ] `/cart` — carrito con datos o empty state correcto
- [ ] `/favorites` — favoritos o empty state correcto
- [ ] `/waitlists` — lista de espera
- [ ] `/loyalty` o ruta de tarjeta de fidelidad (si existe)

### FASE 4 — Admin Backoffice (role=admin) — ~15 rutas

- [ ] `/admin` — dashboard con KPIs numéricos (datos reales o ceros)
- [ ] `/admin/users` — tabla paginada con datos
- [ ] `/admin/roles` — matriz de permisos
- [ ] `/admin/cafes` — listado + modal crear + modal editar
- [ ] `/admin/cafes/create`
- [ ] `/admin/cafes/{id}/edit`
- [ ] `/admin/products` — listado con filtros
- [ ] `/admin/products/create`
- [ ] `/admin/products/{id}/edit`
- [ ] `/admin/reservations` — tabla filtrable
- [ ] `/admin/reviews/pending` — reseñas pendientes de moderación
- [ ] `/admin/waitlist`
- [ ] `/admin/settings` — tabs: general, email, reservas, seguridad
- [ ] `/admin/logs`
- [ ] `/admin/logs/audit`
- [ ] `/admin/logs/auth`
- [ ] `/admin/data-viewer`

### FASE 5 — Manager Backoffice (role=manager) — ~8 rutas

- [ ] `/manager` — dashboard
- [ ] `/manager/cafe` — vista del propio café
- [ ] `/manager/products`
- [ ] `/manager/staff`
- [ ] `/manager/staff/{id}`
- [ ] `/manager/reservations`
- [ ] `/manager/reviews`
- [ ] `/manager/reports`

### FASE 6 — Supervisor (role=supervisor) — ~2 rutas

- [ ] `/supervisor` — dashboard
- [ ] `/supervisor/assignments`

### FASE 7 — Reception / OPS (role=reception) — ~2 rutas

- [ ] `/ops/reception` — KDS de reservas del día
- [ ] Verificar KPIs en tiempo real (datos seeded visibles)

### FASE 8 — Kitchen / KDS (role=kitchen) — ~2 rutas

- [ ] `/ops/kitchen` — display KDS
- [ ] `/ops/kitchen/history`

### FASE 9 — Keeper (role=keeper) — ~12 rutas

- [ ] `/keeper` — dashboard
- [ ] `/keeper/animals` — listado
- [ ] `/keeper/animals/create`
- [ ] `/keeper/animals/{id}` — show
- [ ] `/keeper/animals/{id}/edit`
- [ ] `/keeper/health-checks` — listado
- [ ] `/keeper/health-checks/create`
- [ ] `/keeper/health-checks/{id}` — show
- [ ] `/keeper/health-checks/history`
- [ ] `/keeper/incidents` — listado
- [ ] `/keeper/incidents/create`
- [ ] `/keeper/incidents/{id}` — show

### FASE 10 — Páginas de error — ~6 vistas

- [ ] 400 — layout correcto con estilos
- [ ] 401 — idem
- [ ] 403 — idem
- [ ] 404 — idem
- [ ] 419 — idem (CSRF expirado)
- [ ] 500 — idem

### FASE 11 — API endpoints (JSON quick-check) — ~6 endpoints

- [ ] `GET /api/v1/cafes` → JSON válido, HTTP 200
- [ ] `GET /api/v1/menu` (o `/api/v1/menu/items`) → JSON válido
- [ ] `GET /api/v1/holidays` → JSON válido
- [ ] `GET /api/v1/time-slots/available` → JSON válido
- [ ] `GET /api/v1/passes` → JSON válido (si existe)
- [ ] `GET /health` → JSON con servicios `ok`

### FASE 12 — Lighthouse en páginas clave — 4 audits

- [ ] `/` — scores de accesibilidad, best practices, SEO
- [ ] `/cafes/{slug}` — idem
- [ ] `/menu` — idem
- [ ] `/reservar` — idem

### FASE 13 — Compilación del reporte

- [ ] Crear `docs/audit-web-final.md` con:
  - Resumen ejecutivo (% operativas / parciales / rotas)
  - Tabla completa por página con todas las columnas
  - Top 10 issues críticos (🔴) ordenados por severidad — resolver antes de Railway
  - Issues de degradación (🟡)
  - Issues cosméticos (🔵) — post-entrega
  - Sección de hardcoded / datos faltantes detectados
  - Sección de coherencia estética global del producto
  - Recomendaciones de despliegue Railway accionables

---

## Formato tabla reporte (`docs/audit-web-final.md`)

```
| Ruta | Rol req. | Estado | Datos | JS Err | Interactivos | A11y | Estilos | Issues |
|------|----------|--------|-------|--------|--------------|------|---------|--------|
```

Leyenda:
- **Estado:** Operativa / Parcial / Rota
- **Datos:** BD real / Hardcodeado / Sin datos
- **JS Err:** Sí / No
- **Interactivos:** OK / Roto / Parcial
- **A11y:** OK / Issues / Crítico
- **Estilos:** OK / Roto / Menor
- **Issues:** lista con severidad 🔴 🟡 🔵

---

## Verificación de completitud

Antes de declarar el reporte terminado:

1. Las 13 fases están marcadas con `[x]`
2. El reporte cubre las ~130 rutas (ningún rol sin auditar)
3. Cada página tiene: screenshot + estado + datos + errores JS + resultado de interactivos
4. El checklist de diseño profesional se ha aplicado a cada módulo
5. Los 4 Lighthouse tienen score numérico registrado
6. Las recomendaciones Railway son accionables (ruta específica, issue concreto, severidad)

---

## Archivos de referencia

| Archivo | Relevancia |
|---------|------------|
| `app/routes.php` | Front controller de rutas |
| `app/routes/public.php` | Rutas públicas |
| `app/routes/auth.php` | Rutas de autenticación y cuenta |
| `app/routes/admin.php` | Rutas backoffice (admin, manager, supervisor) |
| `app/routes/ops.php` | Rutas OPS (reception, kitchen) y keeper |
| `resources/views/` | 153 vistas auditadas |
| `scripts/` | Seeders y credenciales de prueba |
| `docs/design-system/` | Paleta, tipografía y tokens de diseño |
| `docs/audit-web-final.md` | Output del plan (a crear en Fase 13) |
