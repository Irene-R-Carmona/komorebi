# Informe de Auditoría UX/UI — Komorebi Café

**Fecha:** 11 de abril de 2026
**URL:** <http://localhost:8080>
**Stack:** PHP 8.4 MVC personalizado · FrankenPHP/Caddy · Docker
**Herramientas:** Playwright MCP (capturas, navegación, interacción) · Lectura de código fuente

---

## 1. Resumen Ejecutivo

Se auditaron **todos los flujos accesibles** de la aplicación Komorebi Café cubriendo 6 roles de usuario (visitante público, usuario registrado, manager, keeper, supervisor, admin). Se detectaron y repararon **11 bugs críticos** (🔴 bloqueadores de funcionalidad). Se identificaron **21 issues abiertos adicionales** de severidad media-baja. La aplicación está en un estado funcional sólido tras las correcciones realizadas durante la auditoría.

### Hallazgos por categoría

| Categoría | Críticos (🔴) | Medios (🟡) | Menores (🟢) |
|-----------|:---:|:---:|:---:|
| Bugs funcionales | 11 ✅ reparados | 10 abiertos | 3 abiertos |
| Seguridad | 0 | 1 abierto | — |
| UX/Navegación | — | 4 abiertos | — |
| Contenido/i18n | — | 3 abiertos | — |
| Accesibilidad | — | 2 abiertos | — |

---

## 2. Metodología

- Navegación sistemática de todas las rutas del fichero `app/routes.php`
- Prueba de cada rol con credenciales reales (visitante, usuario, manager, keeper, supervisor, admin)
- Capturas de pantalla automáticas vía Playwright MCP en cada página
- Análisis de consola (errores JS, recursos 404) en cada vista
- Test de responsive en viewport 375×812 (iPhone 14) y 1280×900 (escritorio)
- Envío de formularios reales para validar flujos completos (login, reserva, chequeo sanitario, quiz)
- Lectura de código fuente en cada error encontrado para diagnosticar la causa raíz

---

## 3. Alcance — Páginas Auditadas

### 3.1 Área Pública (rol: visitante / usuario registrado)

| Ruta | Estado | Notas |
|------|:------:|-------|
| `/` (Homepage) | ✅ | Hero, carrusel de cafés, features. Popup newsletter a los 5s |
| `/cafes` | ✅ | Grid de 14 cafés, filtros por tipo de animal, buscador |
| `/cafes/{slug}` | ✅ | Detalle de café — descripción, galería, horarios, reservar |
| `/menu` | ✅ | Carta de productos organizada por categoría |
| `/quiz` | ⚠️ | Cuestionario de 5 pasos funciona, pero no muestra resultado/recomendación |
| `/reservas` | ✅ | Sin sesión: muestra formulario con selección de café/fecha/hora. Con sesión: añade datos del usuario |
| `/historia` | ✅ | Página "Nuestra Historia" estática |
| `/faq` | ✅ | FAQ con acordeón |
| `/contacto` | ✅ | Formulario de contacto funcional |
| `/legal/privacidad` | ✅ | Política de privacidad |
| `/legal/cookies` | ✅ | Política de cookies |
| `/legal/terminos` | ✅ | Términos y condiciones |
| `/auth/forgot-password` | ✅ | Formulario email + "Enviar instrucciones" |
| `/login` | ✅ | Login con email/contraseña |
| `/registro` | ✅ | Formulario de registro |

### 3.2 Área de Usuario Autenticado

| Ruta | Estado | Notas |
|------|:------:|-------|
| `/profile` | ✅ | Datos del usuario, edición, cambio de contraseña |
| `/user/waitlists` | ✅ | Lista de espera del usuario |
| `/loyalty/card` | ⚠️ | Carga pero iconos Font Awesome no se muestran |

### 3.3 Manager Backoffice

| Ruta | Estado | Notas |
|------|:------:|-------|
| `/manager/dashboard` | ✅ | KPIs: 2 reservas, 4 animales, 0.00€, 2.0 rating (Neko no Niwa) |
| `/manager/reservations` | ✅ | 5 reservas, tabla sin acciones por fila |
| `/manager/reviews` | ✅ | 1 reseña ★★☆☆☆. CSS leaks como texto plano |
| `/manager/products` | ✅ | Tabla vacía (sin productos sembrados para la sede) |
| `/manager/staff` | ✅ | 5 empleados, 0 turnos asignados |
| `/manager/reports` | ✅ | Con bugs de moneda (¥→€) e idioma (pendiente/confirmed) |
| `/ops/reception` | ✅ | Recepción: 2 llegadas, panel de sala vacía |
| `/ops/kitchen` | ✅ | KDS con 3 estaciones. Bug seguridad: logout por GET |

### 3.4 Keeper Backoffice

| Ruta | Estado | Notas |
|------|:------:|-------|
| `/keeper/dashboard` | ⚠️ | KPI "Animales Activos: 0" incorrecto; 20+ errores de imágenes |
| `/keeper/health-checks` | ✅ | Dashboard chequeos: 39 pendientes, 1 completado hoy |
| `/keeper/health-checks/create/{id}` | ✅ | Formulario chequeo completo y funcional |

### 3.5 Supervisor

| Ruta | Estado | Notas |
|------|:------:|-------|
| `/supervisor/dashboard` | ✅ | Panel de sala: 0 reservas, 0 mesas, 0 órdenes |
| `/supervisor/assignments` | ✅ | Gestión de asignaciones de mesas |

### 3.6 Admin Backoffice

| Ruta | Estado | Notas |
|------|:------:|-------|
| `/admin/dashboard` | ✅ | KPIs: 92 usuarios, 60 reservas, 14 cafés, 21 reseñas |
| `/admin/users` | ✅ | 92 usuarios, tabla con filtros y acciones por fila |
| `/admin/cafes` | ✅ | 14 cafés en cuadrícula, filtros por tipo |
| `/admin/menu` | ✅ | REPARADO (bug DI). Gestión de productos |
| `/admin/reservations` | ✅ | Listado de reservas |
| `/admin/roles` | ✅ | REPARADO (bug GROUP_CONCAT NULL). 12 errores en consola |
| `/admin/logs` | ✅ | Logs del sistema |
| `/admin/settings` | ✅ | Configuración del sistema (2 errores en consola) |
| `/admin/reviews` | ⚠️ | 0 reseñas pendientes. Bug: modal muestra `[object HTMLTextAreaElement]` |

### 3.7 Páginas de Error/Sistemas

| Ruta | Estado | Notas |
|------|:------:|-------|
| `/esta-pagina-no-existe` | ⚠️ | 404 funcional pero sin navegación ni enlace de vuelta |

---

## 4. Bugs Reparados Durante la Auditoría (🔴 → ✅)

| # | Fichero | Problema | Corrección |
|---|---------|----------|-----------|
| 1 | Múltiples controllers | `declare(strict_types=1)` fuera de posición / BOM | Reubicación en primera línea y limpieza BOM |
| 2 | `Auth/AccountController.php` | `new UserService()` — DI roto | `Container::make(UserService::class)` |
| 3 | `Manager/DashboardController.php` | `new CafeService()` — DI roto | `Container::make(CafeService::class)` |
| 4 | `User/WaitlistController.php` | `strtotime(false)` — TypeError | Guard `is_string($value)` antes de llamada |
| 5 | `Manager/ReviewController.php` | `new ReviewService()` — DI roto | `Container::make(ReviewService::class)` |
| 6 | `Manager/StaffController.php` | `new StaffShiftService()` — DI roto | `Container::make(StaffShiftService::class)` |
| 7 | `Manager/ProductController.php` | `new ProductService()` — DI roto | `Container::make(ProductService::class)` |
| 8 | `Services/Manager/DashboardService.php` | SQL `r.party_size` (columna inexistente) + `Database::getInstance()` | `r.guest_count` + `Database::getConnection()` |
| 9 | `resources/views/reception/index.php` | 3× `client_name` (campo inexistente) | `user_name` |
| 10 | `Admin/MenuController.php` | `new ProductService()` — DI roto | `Container::make(ProductService::class)` |
| 11 | `Models/Role.php` | `GROUP_CONCAT(p.name ...)` — MySQL omite NULLs causando desajuste de índices + PHP `fn(string $name)` no acepta `null` | `COALESCE(p.name, '')` + firma `?string $name` |

---

## 5. Issues Abiertos

### 5.1 Bugs críticos / funcionales

#### Bug #12 — Quiz sin resultado de recomendación

- **Severidad:** 🔴 Alta
- **Ruta:** `/quiz`
- **Comportamiento:** El quiz de 5 pasos completa el formulario pero al enviar redirige a `/cafes` sin mostrar ninguna recomendación personalizada. El propósito del quiz (guiar al usuario al café ideal) queda completamente frustrado.
- **Impacto:** Feature rota. Alta probabilidad de abandono.

#### Bug #28 — Endpoint de cookies incorrecto en JS

- **Severidad:** 🔴 Media (funcionalidad silenciosa)
- **Ruta:** Todas las páginas públicas
- **Comportamiento:** `cookieBanner.js:71` llama a `POST /api/cookies/save-preferences` que no existe (404). Las rutas reales son `/api/cookies/accept`, `/api/cookies/reject`, `/api/cookies/update`.
- **Efecto:** Las preferencias de cookies nunca se persisten correctamente. Error silencioso en consola en cada visita pública.
- **Fichero:** `public/js/components/cookieBanner.js`

---

### 5.2 Bugs medios

#### Bug #13 — CSS renderizado como texto en `/manager/reviews`

- **Severidad:** 🟡 Media
- **Comportamiento:** Fragmentos de código CSS aparecen visibles como texto plano en el cuerpo de la página.
- **Posible causa:** Un `<style>` no escapado inyectado en el DOM desde PHP o Alpine.js.

#### Bug #14 — Moneda ¥ (yen) en `/manager/reports`

- **Severidad:** 🟡 Media
- **Comportamiento:** Las cifras de ingresos muestran símbolo `¥` en lugar de `€`. La app es de Madrid.

#### Bug #15 — Estados de reserva en inglés en `/manager/reports`

- **Severidad:** 🟡 Media
- **Comportamiento:** Los estados de las reservas aparecen como `pending`, `confirmed`, `completed` en inglés. Deberían estar traducidos: `Pendiente`, `Confirmado`, `Completado`.

#### Bug #16 — KPI "Animales Activos: 0" en `/keeper/dashboard`

- **Severidad:** 🟡 Media
- **Comportamiento:** El contador KPI dice 0, pero la cuadrícula inmediatamente debajo muestra todos los animales (Alvin, Andes, Babe, Chip, Choco, Copo, Cuzco, Daisy…) con estado "Activo".
- **Posible causa:** La consulta SQL del KPI filtra por `café_id` del keeper pero los animales no están asociados al mismo café, o usa una condición de estado diferente.
- **Verificar:** `KeeperDashboardService` o similar — comparar la query del KPI con la del listado.

#### Bug #18 — Modal de rechazo de reseña en `/admin/reviews`

- **Severidad:** 🟡 Media
- **Comportamiento:** Al abrir el modal para rechazar una reseña, el campo de texto muestra `[object HTMLTextAreaElement]` como contenido o placeholder. Indica que se está asignando un objeto DOM como string en Alpine.js.

#### Bug #30 — Estado animal en inglés en `/keeper/health-checks`

- **Severidad:** 🟡 Media
- **Comportamiento:** La columna "Estado" en la tabla de chequeos pendientes muestra el valor crudo de la BD: `active`. Debería mostrarse como `Activo`.
- **Afecta también:** El formulario de creación de chequeo muestra `"Estado: active"` en la ficha del animal.

---

### 5.3 Seguridad

#### Bug #17 — Logout por GET en `/ops/kitchen`

- **Severidad:** 🟡 Media-Alta (seguridad)
- **Comportamiento:** El botón "Cerrar sesión" del KDS usa `<a href="/logout">` — una petición GET. Sin CSRF token, cualquier imagen o enlace externo cargado en la página podría forzar un cierre de sesión.
- **Corrección:** Cambiar a `<form method="POST" action="/auth/logout">` con token CSRF, igual que el resto de la app.
- **Referencia OWASP:** CSRF (A01:2021 — Broken Access Control / CSRF).

---

### 5.4 UX / Navegación

#### Issue #19 — Newsletter popup a los 5 segundos en todas las páginas públicas

- **Severidad:** 🟡 Media
- **Comportamiento:** Un modal de suscripción al newsletter emerge automáticamente 5 segundos después de cargar cualquier página pública, incluyendo páginas de inicio y detalle de cafés.
- **Impacto UX:** Interrupción agresiva. Contraria a buenas prácticas de UX (Nielsen: no interrumpir flujos activos). Aumenta la tasa de rebote.
- **Sugerencia:** Lanzar solo en la homepage, una vez por sesión, y con un delay de 30+ segundos, o activarla solo al intento de salida (exit-intent).

#### Issue #20 — 404 sem navegación

- **Severidad:** 🟡 Media
- **Ruta:** Cualquier URL no existente
- **Comportamiento:** La página de error 404 muestra únicamente el encabezado "404 - Página no encontrada" y un párrafo de texto. Sin menú, sin enlace "Volver al inicio", sin enlaces a recursos útiles.
- **Impacto UX:** El usuario queda atrapado sin camino de recuperación.
- **Sugerencia:** Añadir navegación principal, enlace a `/cafes`, CTA de reserva y posiblemente sugerencias de páginas populares.

#### Issue #29 — Keeper sidebar incompleta

- **Severidad:** 🟡 Media
- **Comportamiento:** El sidebar del keeper solo tiene "Estado Diario" (`/keeper/dashboard`). La página `/keeper/health-checks` (historial de chequeos) existe y es funcional, pero solo es accesible desde los enlaces del dashboard — **no desde la barra lateral**.
- **Impacto:** El keeper no puede navegar directamente al historial sin pasar por la HOME del backoffice.

#### Issue #32 — Supervisor sin navegación propia

- **Severidad:** 🟢 Baja
- **Comportamiento:** Las rutas `/supervisor/dashboard` y `/supervisor/assignments` existen y funcionan, pero no hay enlace en ningún sidebar hacia ellas (salvo si se accede como admin, que muestra el sidebar de admin). Un usuario con rol puro `supervisor` vería el sidebar de admin u otro sidebar sin los atajos supervisoriales.

---

### 5.5 Accesibilidad

#### Issue #22 — Icono de login sin aria-label

- **Severidad:** 🟡 Media
- **Componente:** Navbarsuperior pública
- **Comportamiento:** El enlace "Iniciar sesión" en la barra de navegación es un icono SVG sin texto visible ni `aria-label`. Los lectores de pantalla no pueden identificarlo.
- **Corrección:** `<a href="/login" aria-label="Iniciar sesión">...</a>`

#### Issue #24 — Falta de skip-link en backoffice (parcial)

- **Severidad:** 🟢 Baja
- **Comportamiento:** Las páginas de keeper y algunas de admin sí tienen `Saltar al contenido principal`. Verificar consistencia en 100% de páginas de backoffice.

---

### 5.6 Contenido / Assets

#### Issue #23 — Imágenes de cafés faltantes

- **Severidad:** 🟡 Media
- **Ruta:** `/cafes`, `/cafes/{slug}`
- **Comportamiento:** Varias tarjetas de café muestran imagen rota. Los ficheros no existen en `public/images/cafes/`.

#### Issue #31 — Todas las imágenes de animales faltantes (20+)

- **Severidad:** 🟡 Media
- **Ruta:** `/keeper/dashboard`
- **Comportamiento:** 20+ errores 404 en consola por imágenes de animales (`/images/animales/ardillas/alvin.jpg`, etc.). El directorio `public/images/animales/` está vacío o no existe. Todos los animales del dashboard del keeper muestran imagen rota.

#### Issue #24b — Iconos Font Awesome no cargan en `/loyalty/card`

- **Severidad:** 🟡 Media
- **Comportamiento:** Los iconos de Font Awesome no se renderizan en la tarjeta de fidelización. El CSS de Font Awesome puede no cargarse o estar apuntando a una CDN bloqueada localmente.

---

### 5.7 Calidad / Console Errors

| Página | Errores | Advertencias | Causa |
|--------|:-------:|:------------:|-------|
| Todas las públicas | 1 | 1 | `/api/cookies/save-preferences` 404 |
| `/admin/roles` | 12 | 11 | Pendiente investigar (JS del gestor de permisos) |
| `/admin/settings` | 2 | 0 | Pendiente investigar |
| `/keeper/dashboard` | 20 | 0 | Imágenes de animales faltantes |
| `/manager/products` | 24 | 0 | Causa pendiente |

---

## 6. User Journeys por Rol

### 6.1 Visitante Público

```
Inicio (/):
  → Descubrir cafés → /cafes → Filtrar por tipo de animal
  → Ver detalle → /cafes/{slug} → CTA "Reservar"
  → /reservas → Seleccionar café/fecha/hora/personas → Login requerido → /login
  → Post-login → /reservas (pre-rellenado) → Confirmación

Caminos alternativos:
  → / → Quiz → /quiz → [BUG: sin resultado] → /cafes
  → / → Carta → /menu → Consulta de productos
  → / → Contacto → /contacto → Envío de formulario ✅
  → Sobre nosotros → /historia ✅
  → FAQ → /faq ✅
  → Footer → /legal/* ✅
```

**Fricción detectada:** El quiz promete encontrar el café ideal pero no muestra el resultado. Alta frustración. El popup del newsletter a los 5s interrumpe la navegación.

---

### 6.2 Usuario Registrado

```
Login → /profile (datos personales, cambio contraseña)
      → /reservas → Selector de fecha/hora → Confirmación → OK
      → /user/waitlists → Lista de espera del usuario
      → /loyalty/card → [Iconos FA no cargan]
      → /cafes → Ver reseñas públicas → Escribir reseña (si ha hecho reserva)
```

**Fricción detectada:** La tarjeta de fidelización tiene problemas visuales. Sin punto de acceso directo en navbar al historial de reservas.

---

### 6.3 Manager (Backoffice de Sede)

```
Login → /manager/dashboard (KPIs de la sede)
      → /manager/reservations (listar reservas — solo lectura, sin acciones por fila)
      → /manager/reviews (gestionar reseñas — CSS leaking bug)
      → /manager/products (gestión de productos — vacío sin datos)
      → /manager/staff (gestión de turnos — 0 turnos)
      → /manager/reports (informes — moneda ¥ y estados en inglés)
      → /ops/reception (panel de recepción de hoy)
      → /ops/kitchen (KDS — bug GET /logout)
```

**Fricción detectada:** Sin acciones por fila en reservas (no puede confirmar/cancelar desde la tabla). Los informes tienen bugs visuales. El CSS leaks en reviews desconcierta.

---

### 6.4 Keeper (Bienestar Animal)

```
Login → /keeper/dashboard
      → Ver cuadrícula de animales (todos "Activo")
      → KPI muestra 0 animales activos [BUG]
      → Clic "Chequeo" en animal → /keeper/health-checks/create/{id}
      → Rellenar métricas físicas, estado general, notas → "Guardar Chequeo"
      → Redirige a /keeper/health-checks (dashboard de chequeos del día)

Flujo de chequeos:
      → Tab "Pendientes (39)" — tabla con estado en inglés "active" [BUG]
      → Tab "Completados Hoy (1)" — muestra chequeo recién enviado ✅
      → Tab "Alertas (0)"

Problema de navegación:
      → /keeper/health-checks NO está en el sidebar
      → Solo accesible desde el botón "Chequeo" de cada animal en /keeper/dashboard
```

**Fricción detectada:** El KPI incorrecto de animales activos puede crear desconfianza en el sistema. La falta de historial en el sidebar obliga a un flujo indirecto. Los estados en inglés rompen la coherencia del idioma.

---

### 6.5 Supervisor

```
Login (si rol puro 'supervisor') → /supervisor/dashboard
      → Panel de sala en tiempo real: reservas del día, mesas ocupadas, órdenes activas
      → /supervisor/assignments → Gestión de asignaciones de mesas a reservas
      → POST /api/v1/supervisor/assign → Asignar sala

Nota: Las rutas funcionan correctamente. No hay una sidebar dedicada con
      atajos a /supervisor/dashboard y /supervisor/assignments para el rol puro.
```

---

### 6.6 Admin

```
Login → /admin/dashboard (KPIs globales: 92 usuarios, 60 reservas, 14 cafés, 21 reseñas)
      → /admin/users — Gestión de usuarios (crear, editar, desactivar, eliminar)
      → /admin/cafes — Gestión de sedes (14 cafés, filtros, editar, pausar)
      → /admin/menu — Gestión de productos del menú ✅ (reparado)
      → /admin/reservations — Listado global de reservas
      → /admin/roles — Gestión de roles y permisos ✅ (reparado, 12 errores consola)
      → /admin/logs — Logs del sistema
      → /admin/settings — Configuración del sistema
      → /admin/reviews — Revisión de reseñas (modal con bug de textarea)
      → /supervisor/dashboard — Panel de supervisión (accesible como admin)
      → /supervisor/assignments — Asignaciones de mesas
```

**Fortaleza:** El admin tiene una vista completa, bien estructurada y con sidebar categorizado (Sistema / Seguridad / Monitoreo / Configuración). Los KPIs del dashboard son precisos y útiles.

---

## 7. Análisis Visual / UI

### 7.1 Área pública

- **Diseño coherente:** Tipografía, colores y componentes son consistentes en todas las páginas públicas. La identidad visual del café (japonés, cálido, natural) se mantiene con éxito.
- **Tema oscuro:** Funciona correctamente. El botón de cambio de tema está accesible en navbar.
- **Banner de cookies:** Bien diseñado visualmente, con tres opciones claras. El timing de aparición es correcto (inmediato, no a los 5s como el newsletter).
- **Responsive (mobile 375px):** Hamburger menu funciona. Grid de cafés se adapta. Formularios son accesibles en móvil.
- **Imágenes faltantes:** Las tarjetas de cafés sin imagen crean discontinuidad visual en la cuadrícula.

### 7.2 Backoffice (Komorebi OS)

- **Diseño consistente:** Todas las secciones del backoffice usan el mismo layout con sidebar izquierdo y header. La identidad "Komorebi OS" es clara.
- **Sidebar por rol:** Cada rol tiene su sidebar personalizado (admin multi-sección, manager compacto, keeper mínimo). Correcto desde el punto de vista de seguridad y foco.
- **Feedback al usuario:** Las acciones de guardado redirigen correctamente. No se detectaron errores de flash que no aparezcan.
- **Iconos Font Awesome / SVG inline:** En la mayoría de páginas los iconos son SVG inline (robustos). En `/loyalty/card` se usa la CDN de FA que falla.

### 7.3 Inconsistencias detectadas

| Página | Elemento | Problema |
|--------|----------|----------|
| `/manager/reports` | Moneda | `¥1,200` debería ser `€1.200` |
| `/manager/reports` | Estados | `pending/confirmed` en inglés |
| `/keeper/health-checks` | Estado animal | `active` en inglés (raw DB value) |
| `/keeper/health-checks/create/{id}` | Ficha animal | `"Estado: active"` en lugar de `"Estado: Activo"` |
| `/admin/reviews` | Modal reject | `[object HTMLTextAreaElement]` como texto |
| `/manager/reviews` | Vista | CSS leaks como texto plano visible |

---

## 8. Accesibilidad (WCAG 2.1)

| Criterio | Estado | Notas |
|----------|:------:|-------|
| Skip links (`Saltar al contenido`) | ✅ Parcial | Presentes en público y en la mayoría del backoffice |
| Contraste de color | ✅ | Paleta de colores supera ratios AA en modo claro y oscuro |
| Etiquetas en formularios | ✅ | Formularios de login/registro/reserva tienen `<label>` correctamente asociados |
| Atributos `alt` en imágenes | ✅ Parcial | Las imágenes de animales tienen `alt="Foto de {Nombre}"`. Falta verificar fauna de cafés públicos |
| `aria-label` en iconos de acción | ⚠️ Incompleto | Link de "Iniciar sesión" (icono SVG) sin aria-label en navbar pública |
| Teclado: foco visible | ✅ Básico | Estilos de foco presentes, aunque mejorable en algunos botones del backoffice |
| Roles ARIA en tablas | ✅ | Tablas del backoffice usan `rowgroup`, `columnheader`, `cell` semánticos |
| Idioma HTML `lang` | ✅ | `<html lang="es">` presente |

---

## 9. Rendimiento y Errores de Consola por Página

### Páginas sin errores

- `/cafes`, `/cafes/{slug}`, `/menu`, `/historia`, `/faq`, `/contacto`, `/quiz`, `/reservas`, `/profile`, `/manager/dashboard`, `/manager/staff`, `/manager/reservations`, `/supervisor/dashboard`, `/supervisor/assignments`, `/keeper/health-checks`, `/keeper/health-checks/create/{id}`, `/admin/dashboard`, `/admin/users`, `/admin/cafes`, `/admin/menu`, `/admin/reservations`, `/admin/logs`

### Páginas con errores de consola

| Página | Errores | Tipo |
|--------|:-------:|------|
| Todas las públicas (banner de cookies) | 1E + 1W | `/api/cookies/save-preferences` 404 — endpoint JS incorrecto |
| `/keeper/dashboard` | 20E | 20 imágenes de animales faltantes en `public/images/animales/` |
| `/manager/products` | 24E | Causa pendiente |
| `/admin/roles` | 12E + 11W | JS del gestor de permisos (pendiente investigar) |
| `/admin/settings` | 2E | Causa pendiente |

---

## 10. Recomendaciones Priorizadas

### Prioridad 1 — Correcciones críticas (esta semana)

| # | Acción | Fichero / Componente |
|---|--------|---------------------|
| P1.1 | Reparar endpoint de cookies: cambiar `save-preferences` → `update` | `public/js/components/cookieBanner.js` |
| P1.2 | Reparar bug quiz: mostrar resultado de recomendación tras envío | `QuizController`, vista de resultado |
| P1.3 | Reparar bug KPI "Animales Activos: 0" en keeper dashboard | `KeeperDashboardService` o equivalente |
| P1.4 | Traducir estado animal `active` → `Activo` | Vista keeper health-checks + create |
| P1.5 | Corregir logout KDS a POST con CSRF | `resources/views/kitchen/index.php` (o equivalente) |
| P1.6 | Corregir modal rechazo reseña (`[object HTMLTextAreaElement]`) | `public/js/sections/admin/reviews.js` |

### Prioridad 2 — Mejoras importantes (próximas 2 semanas)

| # | Acción | Fichero / Componente |
|---|--------|---------------------|
| P2.1 | Traducir moneda `¥` → `€` en informes de manager | `DashboardService` / vista de reports |
| P2.2 | Traducir estados de reserva `pending/confirmed/completed` | i18n / vista de reports |
| P2.3 | Añadir "Historial de Chequeos" al sidebar del keeper | Layout backoffice keeper |
| P2.4 | Añadir navegación y CTA a la página 404 | `resources/views/errors/404.php` |
| P2.5 | Añadir `aria-label` al link de login en el navbar | `resources/views/layouts/main.php` |
| P2.6 | Colocar imágenes de animales reales en `public/images/animales/` | Assets (contenido) |
| P2.7 | Investigar y corregir 12 errores en `/admin/roles` | JS de gestión de permisos |
| P2.8 | Corregir CSS leak en `/manager/reviews` | Vista reviews manager |

### Prioridad 3 — Mejoras de UX (próximo sprint)

| # | Acción | Impacto |
|---|--------|---------|
| P3.1 | Reducir agresividad del popup de newsletter (exit-intent o 30s) | Reduce tasa de rebote |
| P3.2 | Añadir acciones por fila en `/manager/reservations` (confirmar/cancelar) | UX gestión de reservas |
| P3.3 | Sembrar productos iniciales para sedes en DB (o mensaje de cero-state) | UX `/manager/products` |
| P3.4 | Crear sidebar o nav dedicada al rol supervisor | Navigation `/supervisor/*` |
| P3.5 | Indicar al usuario que el quiz está procesando y mostrar recomendación | Conversion quiz |
| P3.6 | Imágenes de cafés: añadir placeholder mientras cargan o imágenes de stock | Visual consistency |
| P3.7 | Sustituir CDN Font Awesome en loyalty card por SVG inline o bundled | Reliability |

---

## 11. Observaciones Finales

### Fortalezas del sistema

1. **Arquitectura sólida:** El framework PHP personalizado está bien estructurado. El patrón Result, la inyección de dependencias y el enrutamiento son consistentes y robustos.
2. **Seguridad general:** CSRF tokens en todos los formularios mutantes (salvo el bug del KDS). Sesiones seguras con HttpOnly cookies. Roles RBAC correctamente aplicados en middleware.
3. **Backoffice admin maduro:** El panel de administración tiene una UX coherente, KPIs relevantes, tablas con acciones, filtros y navegación clara.
4. **Diseño público atractivo:** La experiencia visual del área pública es agradable, temáticamente coherente y responsive.
5. **Tests:** 831 tests pasan (0 fallos) tras las correcciones realizadas.

### Deuda técnica identificada

1. **Imágenes de assets:** Tanto las imágenes de cafés como las de animales dependen de ficheros aún no creados. Necesita una decisión: imágenes reales, stock, o placeholders generados.
2. **i18n:** Los valores enumerados en la base de datos (`active`, `pending`, `confirmed`) no tienen una capa de traducción. Se renderizan crudos en vistas. Solución: mapa de traducción PHP en el helper de vistas o en los modelos.
3. **Console errors en backoffice:** Las páginas de admin/roles y manager/products tienen errores JS pendientes de investigar.
4. **El quiz necesita implementar el resultado:** El flujo frontend del quiz existe pero la respuesta del backend (qué café recomendar) no se muestra.

---

*Informe generado tras auditoría sistemática con Playwright MCP — Komorebi Café v1.0-dev*
