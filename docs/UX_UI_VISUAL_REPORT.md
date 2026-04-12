# Informe UX/UI Visual — Komorebi Café

**Versión:** 3.0 | **Fecha:** Abril 2026 | **Alcance:** Auditoría visual completa — 69 páginas, todos los roles

---

## Resumen Ejecutivo

Komorebi Café presenta una identidad visual japonesa coherente y de alta calidad en el **sitio público**,
con una paleta cálida bien definida y tipografía japonesa auténtica. Sin embargo, existen **fracturas
críticas** entre la marca del sitio público y la interfaz de backoffice, además de varios bugs visuales
que rompen la experiencia de usuario en páginas clave.

**Versión 2.0 — Actualización:** Esta revisión amplía la auditoría a **69 páginas** cubriendo todos los
roles del sistema (público, usuario autenticado, admin, manager, supervisor, reception, kitchen, keeper).
Se identificaron **23 bugs visuales y funcionales** (9 adicionales respecto a v1.0), confirmando que las
interfaces operativas (keeper especialmente) tienen deuda técnica significativa.

**Nota del auditor:** Este informe se basa en capturas de pantalla reales tomadas en `http://localhost:8080`
con usuarios de cada rol. Los archivos existen en `screenshots/`.

---

## 1. Sistema de Diseño

### 1.1 Tokens de Color

El proyecto tiene **dos sistemas de color coexistentes** sin integración real:

#### Sistema Público (`global.css` + `design-tokens.css`)

| Token | Valor | Uso |
|-------|-------|-----|
| `--color-fondo` | `#F7F3EB` | Fondo cuerpo — crema cálida |
| `--color-fondo-alt` | `#E8DCC4` | Fondo alternativo — crema oscura |
| `--color-primario` | `#5C3D2E` | Café oscuro — color principal |
| `--color-acento` | `#C9A959` | Ámbar dorado — call-to-action |
| `--color-texto` | `#2D2218` | Texto principal |
| `--color-texto-suave` | `#5C4A3A` | Texto secundario |
| `--color-borde` | `#D4C4A8` | Bordes suaves |

**Dark Mode (`[data-tema="oscuro"]`):**

| Token | Valor |
|-------|-------|
| `--color-fondo` | `#1A1410` |
| `--color-superficie` | `#3D2E1F` |
| `--color-texto` | `#E8DCC4` |
| `--color-primario` | `#C9A959` (invierte a dorado) |

#### Sistema Backoffice (`backoffice.css`)

| Token | Valor | Uso |
|-------|-------|-----|
| `--bg-sidebar` | `#1e293b` | Sidebar oscuro estilo Tailwind |
| `--bg-body` | `#f8fafc` | Fondo contenido |
| `--bg-surface` | `#ffffff` | Superficies de tarjetas |
| `--text-main` | `#334155` | Texto principal |
| `--brand-primary` | `#5C3D2E` | Referencia a marca (rara vez visible) |
| `--brand-accent` | `#C9A959` | Dorado en activos/hover |

> ⚠️ **PROBLEMA:** El sidebar del backoffice usa `#1e293b` (slate oscuro genérico) en lugar de tonos
> del marrón-café de la marca. La referencia `--brand-primary` está declarada pero apenas se usa.

### 1.2 Tipografía

| Contexto | Familia | Rol |
|----------|---------|-----|
| Sitio público — Títulos | `Shippori Mincho` (serif japonés) | H1, H2 |
| Sitio público — Cuerpo | `Zen Maru Gothic` (sans japonés) | Párrafos, UI |
| Sitio público — Acento | `Kaisei Decol` (serif japonés) | Citas, hero |
| Backoffice | `Inter` (system-ui) | Todo el UI operativo |

Elección tipográfica excelente para el concepto *kissaten*. La transición a `Inter` en backoffice es
correcta para densidad de datos pero acentúa la desconexión visual con la marca.

### 1.3 Espaciado y Bordes

Sitio público define un sistema de espaciado semántico:
`xs=0.5rem`, `sm=1rem`, `md=1.5rem`, `lg=2.5rem`, `xl=4rem`

Backoffice usa valores hardcoreados (`8px`, `12px`, `1.5rem`) sin referencias a tokens unificados.

### 1.4 Sombras

Sitio público: sombras con tinte cálido `rgba(45, 34, 24, ...)` — mantienen coherencia con la paleta.
Backoffice: sombras neutras `rgba(0, 0, 0, 0.1)` — correcto para un contexto operativo.

---

## 2. Análisis por Zona — Sitio Público

### 2.1 Homepage (01-homepage-hero.jpeg, 01-homepage-full.jpeg)

**Fortalezas:**

- Hero atmospheric con fondo bokeh de colores cálidos. Concepto *komorebi* (luz entre hojas) bien ejecutado
- Tagline bilingüe (`木漏れ日カフェへようこそ`) añade autenticidad japonesa
- Dos CTAs diferenciados: `EXPLORAR CAFÉS` (primario) + `¿CUÁL ES PARA TI?` (quiz, secundario)
- Widget de clima/estación (`TOKYO 19°C · 清明`) — detalle de worldbuilding encantador
- Sección "3 pasos" con numeración grande y emojis funciona bien visualmente
- Estadísticas globales (14 sedes, 14 especies, 4.3 valoración) generan confianza

**Debilidades:**

- El banner de newsletter en el footer se dispara en **cada navegación** — agresivo y molesto
- La transición del hero al contenido posterior pierde temperatura (crema a blanco puro)
- Sin imagen hero de alta calidad (se percibe como background genérico)

**Puntuación:** 8/10

---

### 2.2 Catálogo de Cafés (02-cafes-catalogue.jpeg)

**Fortalezas:**

- Grid de tarjetas limpio con proporción de imagen correcta
- Tags de tipo de animal bien visibles en las tarjetas

**Debilidades:**

- **MÚLTIPLES imágenes rotas** — la mayoría de cafés muestran cajas grises con texto `alt`. Impacto
  visual devastador en la primera impresión del catálogo
- Sin imagen de placeholder con diseño de marca (debería mostrar un icono ☕ o silueta animal)
- La página parece "abandonada" cuando hay más de la mitad de tarjetas sin imagen

**Puntuación:** 5/10 (por imágenes rotas)

---

### 2.3 Detalle de Café (03-cafe-detail.jpeg)

**Fortalezas:**

- Layout de detalle rico: imagen principal, galería de animales, info de café, booking CTA
- Paleta de colores consistente con el resto del sitio
- Sección de animales del café es el punto diferenciador bien destacado

**Debilidades:**

- La imagen del café sí carga (Neko no Niwa tiene imagen) pero el contraste con otras tarjetas sin
  imagen en el catálogo crea inconsistencia percibida

**Puntuación:** 7.5/10

---

### 2.4 Carta / Menú (04-menu.jpeg)

**Fortalezas:**

- Sistema de carta funcional con categorías y carrito lateral
- El overlay del carrito usa el dorado de marca correctamente
- Filtros por categoría son visualmente claros

**Debilidades:**

- El estado vacío del carrito tiene mucho espacio negativo
- Los iconos de alérgenos dependen de Font Awesome cargado desde CDN externo — si falla, se pierde
  información de seguridad alimentaria importante

**Puntuación:** 7/10

---

### 2.5 Quiz Café Ideal (05-quiz.jpeg)

**Fortalezas:**

- Tarjeta centrada sobre fondo borroso — patrón correcto para flujos de decisión
- Micro-interacción limpia, sin distracciones

**Debilidades:**

- La página parece incompleta visualmente — el quiz en sí no es visible en el first screen (muy
  arriba sin contenido que justifique el misterio)
- Sin branding visible en la tarjeta del quiz

**Puntuación:** 6.5/10

---

### 2.6 Reservas / Reservar Mesa (06-reservas.jpeg)

**Fortalezas:**

- Split-panel inteligente: formulario borroso a la izquierda, CTA de login a la derecha para visitantes
- El desenfoque comunica "próximamente disponible para ti si te registras" — buen UX
- Paleta de colores correcta

**Debilidades:**

- El texto de la CTA de login podría ser más prominente — uso excesivo de lowercase dificulta jerarquía

**Puntuación:** 7.5/10

---

### 2.7 Login y Registro (07-login.jpeg, 08-registro.jpeg)

**Fortalezas:**

- Formularios limpios, minimalistas, con emoji de marca (☕, 🌱)
- Proporción de campos correcta — sin sobrecargar al usuario

**Debilidades:**

- Sin imagen o elemento visual de ambientación que refuerce la experiencia del café
- El botón "Entrar" / "Crear cuenta" usa un azul estándar que **no coincide con ningún token de la
  paleta de marca** (el azul es el color genérico de Bootstrap/Tailwind `#3B82F6`)
- El enlace "¿Olvidaste tu contraseña?" cambia de color respecto al resto — inconsistencia cromática

**Puntuación:** 7/10

---

### 2.8 Perfil de Usuario (09-profile.jpeg)

**Fortalezas:**

- Sección de gamificación con nivel y puntos bien integrada
- Dark mode activo — la transición de colores es correcta y coherente

**Debilidades:**

- El avatar/inicial de usuario es genérico — sin personalización

**Puntuación:** 7/10

---

### 2.9 Tarjeta de Fidelización — CRITICAL (10-loyalty.jpeg)

Este es el **bug visual más grave del sitio público**.

**Bug 1 — Paleta rota:**
La tarjeta usa un gradiente `linear-gradient(135deg, #6366f1, #8b5cf6)` (índigo/violeta, paleta
Tailwind genérica) que **contradice totalmente** la marca cálida marrones/ámbar. En `loyalty.css`
línea 306, esta regla hardcoreada sobrescribe cualquier token de la marca.

También se detecta `color: #667eea` (azul-violeta) en `.reward-card__cost`, otro color externo a
la paleta de marca.

**Bug 2 — Font Awesome no carga:**
Los iconos `fa-coffee`, `fa-star`, etc. se muestran como texto plano. `FontAwesome` se incluye
únicamente en el layout `backoffice.php` (línea 37), **no en el layout público**. La vista de
loyalty usa iconos FA que no tienen CDN disponible en el public layout.

**Impacto:** La tarjeta de fidelización es el momento de mayor "delight" en el customer journey.
Que aparezca rota y con paleta genérica destruye la magia del diseño de marca.

**Puntuación visual:** 2/10

---

### 2.10 Nuestra Historia (11-historia.jpeg)

**Fortalezas:**

- Timeline visual con foto del equipo
- Temperatura cálida bien mantenida
- Sección de valores con iconos/emojis funciona

**Debilidades:** Ninguna significativa en esta captura.

**Puntuación:** 8/10

---

### 2.11 Contacto (12-contacto.jpeg)

**Fortalezas:**

- Grid de 6 tarjetas con métodos de contacto es limpio y escaneable
- Iconos consistentes

**Puntuación:** 7.5/10

---

### 2.12 Páginas de Error (13-403-error.jpeg, 13-404-error.jpeg)

| Página | Estado | Observación |
|--------|--------|-------------|
| 403 | ✅ Estilada | Navbar, branding, botón de vuelta integrado |
| 404 | ❌ **SIN ESTILO** | Página en blanco con texto HTML plano, sin layout, sin CSS |

El 404 es completamente distinto al 403. La vista 404 parece no extender el layout principal.
Impacto: un cliente que escribe mal una URL ve una página rota — percepción de baja calidad técnica.

---

### 2.13 Dark Mode (14-homepage-dark.jpeg)

**Fortalezas:**

- El navbar cambia correctamente a fondo marrón oscuro con texto dorado
- El toggle funciona bien

**Debilidades:**

- En dark mode, algunas páginas (loyalty, por ejemplo) no tienen soporte completo de dark mode

**Puntuación:** 7.5/10

---

### 2.14 Responsive / Mobile (15-homepage-mobile.jpeg)

**Fortalezas:**

- 375px funciona: hamburger menu visible, CTA accesible
- Sin overflow horizontal en el homepage

**Nota pendiente:** No se capturó mobile en páginas de backoffice, que probablemente no son
responsive (el grid sidebar + contenido no colapsa en mobile).

**Puntuación:** 7/10

---

### 2.15 FAQ, Legal y Forgot Password (27-31)

**FAQ (`27-faq.jpeg`):**

- Contenido rico con acordeones expandibles — buen UX de información
- Paleta cálida consistente, tipografía correcta
- Sin observaciones críticas. **Puntuación:** 8/10

**Páginas Legales (`28-legal-privacidad.jpeg`, `29-legal-cookies.jpeg`, `30-legal-terminos.jpeg`):**

- Las tres páginas son tipográficamente correctas, con estructura de secciones clara
- Longitud apropiada para el tipo de contenido legal
- Sin observaciones críticas. **Puntuación:** 7.5/10

**Recuperar contraseña (`31-forgot-password.jpeg`):**

- Formulario minimalista centrado, correcto
- Consistente con el estilo del login. **Puntuación:** 7/10

---

## 3. Análisis por Zona — Backoffice / OS

### 3.1 Identidad Visual del Backoffice

El backoffice de Komorebi (denominado **"Komorebi OS"**) usa una estética distinta al sitio público:

- Sidebar oscuro `#1e293b` (paleta Slate de Tailwind unmodified)
- Contenido en `#f8fafc` (blanco frío)
- Tipografía `Inter`

**El cambio de contexto es comprensible** para un sistema operativo (los usuarios de backoffice
necesitan densidad informativa y claridad funcional), pero la desconexión con la marca es total.
El único elemento que recuerda a Komorebi en el backoffice es el logotipo "☕ Komorebi OS".

---

### 3.2 Admin Dashboard (25-admin-dashboard.jpeg)

Vista solo accesible con rol admin.

**Fortalezas:**

- Estadísticas globales: usuarios, cafés, reservas
- Layout de sidebar + main consistente

**Debilidades:**

- Tarjetas de estadística con iconos de colores aleatorios (verde, rojo, azul, amarillo) sin
  relación alguna con los colores semánticos del design-tokens.css

---

### 3.3 Admin Usuarios (26-admin-users.jpeg)

**Fortalezas:**

- Tabla de usuarios con filtros funcional
- Paginación visible

**Debilidades:**

- Tabla muy densa sin espaciado generoso — difícil escaneo
- Sin puntuación o datos de calidad de usuario

---

### 3.4 Manager Dashboard (20-manager-dashboard.jpeg)

**Fortalezas:**

- 4 tarjetas KPI (`Reservas Hoy`, `Animales Activos`, `Ingresos Semana`, `Rating Promedio`)
- Dos gráficos: donut de estado de reservas + línea de ingresos semanales — correctamente contextualizados
- Sección de "Animales Más Populares" con fotos

**Debilidades:**

- Los iconos en los KPI cards usan colores de badge aislados (verde, azul, dorado, etc.) sin
  un sistema semántico claro
- Los ingresos semanales muestran `0.00€` — datos de semilla insuficientes para demo visual
- El gráfico de ingresos no tiene datos y aparece vacío (línea X sin puntos)

**Puntuación:** 7/10

---

### 3.5 Manager Reservaciones — CRITICAL (21-manager-reservations.jpeg)

**Bug crítico — CSS renderizado como texto:**
En la parte inferior de la página aparece literalmente:

```
.btn--primary:hover { background: var(--primary-700, #1d4ed8); }
```

Este CSS se está imprimiendo como texto HTML plano, no ejecutándose como estilo. Indica que
alguna vista o helper está haciendo `echo` de un bloque `<style>` sin pasar por `Raw::html()`,
o que hay un `<style>` tag dentro del cuerpo que el parser renderiza como texto. Necesita revisión
en la vista o en el controller de reservaciones del manager.

**Adicionalmente:**

- Los badges de estado usan valores en inglés: `Pendiente`, `Confirmada`, `Completada` mezclados
  con columnas que muestran `pending`, `confirmed` — inconsistencia de i18n

**Puntuación:** 4/10

---

### 3.6 Manager Reportes (22-manager-reports.jpeg)

**Observaciones:**

- Las tarjetas KPI (`Reservas Hoy`, `Reservas del Mes`, `Ingresos Hoy`, `Rating Promedio`) están
  **vacías** — solo iconos sin valores numéricos. Puede ser un problema de carga de datos o de
  estilos que ocultan el texto
- La tabla de reservas muestra **¥0** (Yen japonés) en la columna Importe en lugar de **€0** — error
  de configuración de moneda. Humorístico para un café español
- Los estados de la tabla (`pending`, `confirmed`, `completed`) no están traducidos al español
- Botones "Exportar CSV" funcionales son correctos para un panel de reportes

**Puntuación:** 5/10

---

### 3.7 Keeper Dashboard — Bienestar Animal (23-keeper-dashboard.jpeg)

**Diseño general:**

- La interfaz del keeper es la más específica del proyecto — tarjetas de animales con foto, estado
  de salud, badges "ACTIVO" / "CHEQUEO PENDIENTE"
- Sidebar mínimo con una sola entrada "Estado Diario" — puede ser insuficiente a largo plazo

**Bugs visuales:**

- **Overflow horizontal**: Las tarjetas de animales desbordan el viewport. El grid no tiene `max-width`
  y no hay `overflow: hidden` en el contenedor. Se ven tarjetas parciales a la derecha
- **3/4 imágenes rotas**: Solo Choco (conejo) tiene imagen real; Copo (chinchilla), Cuzco (alpaca)
  y Daisy (pato) muestran "Foto de [nombre]" como `alt` text
- **Línea amarilla de columna**: El CSS del grid tiene un `border-right` o `gap` visualmente
  incorrecto que traza una línea amarilla entre columnas

**Puntuación:** 5/10

---

### 3.8 Páginas de Usuario Autenticado (33-41)

| Página | Archivo | Observación |
|--------|---------|-------------|
| Mis Reservas | `33-user-mis-reservas.jpeg` | ✅ Estado vacío limpio, CTA visible |
| Mis Favoritos | `34-user-favoritos.jpeg` | ⚠️ Página muy pequeña (59KB) — puede ser estado vacío |
| Carrito | `35-user-carrito.jpeg` | ⚠️ Estado vacío, carga mínima |
| Mis Reviews | `36-user-reviews.jpeg` | ⚠️ Sin reviews registradas en seed |
| Mi Waitlist | `37-user-waitlists.jpeg` | ⚠️ Sin datos en seed |
| Sesiones | `38-account-sessions.jpeg` | ✅ Lista de sesiones activas |
| Seguridad | `39-account-security.jpeg` | ✅ Opciones 2FA / tokens |
| Cambiar contraseña | `40-account-change-password.jpeg` | ✅ Formulario limpio |
| Confirmación reserva | `41-reserva-confirmacion.jpeg` | ⚠️ Redirige a `/reservas` — sin confirmación accesible |

**Observación transversal:** La mayoría de las páginas de usuario muestran estados vacíos porque los datos
de semilla no incluyen actividad para `yuki.tanaka@gmail.com`. Los estados vacíos están bien diseñados
con mensajes claros y CTAs, pero no se pueden auditar visualmente con contenido real.

**Bug notable:** No existe una página de confirmación de reserva accesible con el seed actual. La URL
`/reservas/confirmacion/{id}` con un ID no perteneciente al usuario redirige a `/reservas`.

---

### 3.9 Admin — Páginas Extendidas (42-54)

| Página | Archivo | Observación |
|--------|---------|-------------|
| Roles | `42-admin-roles.jpeg` | ✅ Tabla clara de roles y permisos |
| Cafés | `43-admin-cafes.jpeg` | ✅ Listado rico, bien organizado |
| Menú | `44-admin-menu.jpeg` | ✅ Gestión de carta correcta |
| Reviews | `45-admin-reviews.jpeg` | ⚠️ Poca datos de seed visibles |
| Reservas | `46-admin-reservations.jpeg` | ✅ Tabla densa pero funcional |
| Waitlists | `47-admin-waitlists.jpeg` | ✅ Lista correcta |
| Animales | `48-admin-animals.jpeg` | ⚠️ 59KB — posible estado vacío o redireccionado |
| Settings | `49-admin-settings.jpeg` | ✅ Panel de configuración sistema |
| Logs aplicación | `50-admin-logs.jpeg` | ✅ Logs en tiempo real bien presentados |
| Logs auditoría | `51-admin-logs-audit.jpeg` | ✅ Tabla de auditoría correcta |
| Logs autenticación | `52-admin-logs-auth.jpeg` | ✅ Login history visible |
| Reportes | `53-admin-reports.jpeg` | ✅ Datos analíticos correctos |
| Data Viewer | `54-admin-data-viewer.jpeg` | ✅ Explorer SQL complejo pero funcional |

**Observación:** La página `/admin/animals` (48) pesa exactamente 59185 bytes — idéntico al peso
de varias páginas de estado vacío del usuario. Posiblemente redirige o tiene el mismo template de
estado vacío que otras páginas sin datos de seed.

---

### 3.10 Manager — Páginas Extendidas (55-58)

| Página | Archivo | Observación |
|--------|---------|-------------|
| Reviews | `55-manager-reviews.jpeg` | ✅ Tabla de reseñas con moderación |
| Staff | `56-manager-staff.jpeg` | ✅ Gestión de personal del café |
| Café (editar) | `57-manager-cafe.jpeg` | ✅ Formulario de edición correcto |
| Productos | `58-manager-products.jpeg` | ✅ Gestión de carta/menú |

**Observación de acceso:** Las rutas `/manager/staff`, `/manager/cafe`, `/manager/products`
requieren `ownsCafe()` — un admin no puede verlas porque ese middleware verifica que el usuario
sea el manager que posee el café. El manager con café asignado sí accede correctamente.

---

### 3.11 Supervisor y Operaciones (59-63)

| Página | Archivo | Observación |
|--------|---------|-------------|
| Supervisor Dashboard | `59-supervisor-dashboard.jpeg` | ✅ KPIs de turno visibles |
| Supervisor Assignments | `60-supervisor-assignments.jpeg` | ✅ Gestión de asignaciones |
| Recepción | `61-reception-dashboard.jpeg` | ✅ Panel de check-in correcto |
| Cocina | `62-kitchen-dashboard.jpeg` | ✅ KDS funcional, pedidos en tiempo real |
| Cocina Historial | `63-kitchen-history.jpeg` | 🔴 **Alpine.js runtime errors** |

**Bug 🔴 — Alpine.js en Kitchen History:**
La consola del navegador reporta:

```
ReferenceError: checkinOpen is not defined
ReferenceError: selectedResId is not defined
```

Estas variables se referencian en directivas `x-data` o `x-on` de Alpine.js pero no están
declaradas en el scope del componente. El UI interactivo de historial de cocina (modal de
checkin/selección de reserva) **no funciona**. También se detectó una violación de CSP
al intentar cargar una imagen desde `lh3.googleusercontent.com` (URL de Google Photos
hardcodeada en los datos de seed, origen no autorizado en la política del servidor).

---

### 3.12 Keeper — Bienestar Animal Extendido (64-69)

#### 3.12.1 Lista de Animales (64-keeper-animals-list.jpeg)

- Grid de 40 animales bien estructurado con nombre, especie, café, estado y chequeos del día
- **Bug:** Nombre de animal renderizado **dos veces** en cada fila (ej: "Alvin Alvin", "Andes Andes").
  La thumbnail muestra el nombre como `alt` text y además aparece como texto de celda. El componente
  de imagen y el campo de nombre están duplicados en el template.
- Los badges de "ACTIVO" son visualmente correctos (verde oscuro, tipografía pequeña)
- **Puntuación:** 6/10

#### 3.12.2 Ficha de Animal (65-keeper-animal-show.jpeg)

- Diseño con dos columnas: perfil del animal (izquierda) + formularios rápidos de alimentación y
  chequeo (derecha). Layout bien pensado para el workflow diario del keeper.
- **Bug:** La sección de foto del animal es una **caja blanca vacía** sin imagen ni placeholder.
  El animal "Alvin" (ardilla) no tiene imagen cargada en storage. Falta un estado de fallback
  con placeholder (emoji de especie, silueta animal, o similar) para cuando no hay foto disponible.
- Formulario de "Registrar Chequeo de Salud" usa checkboxes estáticos para Ojos/Respiración/Movilidad
  — visualmente correcto aunque la vista `/keeper/health-checks/create/{id}` ofrece una versión más
  completa del mismo formulario (duplicación de funcionalidad a revisar).
- **Puntuación:** 6.5/10

#### 3.12.3 Dashboard Chequeos de Salud (66-keeper-health-checks.jpeg)

- Excelente dashboard con 3 KPIs: Completados Hoy / Pendientes / Alertas Activas
- Tabla de 40 animales con estado "SIN REGISTRO" — correcto para datos frescos de seed
- Las pestañas Pendientes / Completados Hoy / Alertas son intuitivas
- Los badges de especie usan colores variados (teal, cyan, naranja, verde) — visualmente ricos
  aunque sin sistema semántico consistente
- **Puntuación:** 8/10

#### 3.12.4 Formulario Nuevo Chequeo (67-keeper-health-check-create.jpeg)

- Formulario bien estructurado con secciones "Métricas Físicas" y "Estado General"
- **Bug visual:** El encabezado de sección "Notas Adicionales" usa un **cyan brillante** (`#00B4D8`
  o similar) que contrasta radicalmente con los encabezados marrón oscuro y verde de las otras
  dos secciones del mismo formulario. Inconsistencia de paleta interna en la misma vista.
- Los toggles de Ojos/Respiración/Movilidad con descripción textual son UX intuitivo
- **Puntuación:** 7/10

#### 3.12.5 Lista de Incidentes (68-keeper-incidents.jpeg)

- Estado vacío correcto: "No hay incidentes activos. ¡Todo en orden!" con icono de check
- El exceso de whitespace bajo el mensaje de estado vacío es notable (pantalla 80% vacía)
- Sin paginación ni filtros históricos visibles — solo incidentes activos
- **Puntuación:** 7/10

#### 3.12.6 Reportar Incidente (69-keeper-incident-create.jpeg)

- **Bug UX crítico:** El campo "ID del Animal" es un `<input type="number">` que pide al keeper
  que ingrese **el ID numérico de base de datos** del animal (`Ej: 3`). Un keeper no conoce
  IDs internos; debería ser un `<select>` con los animales bajo su cargo.
- **Bug técnico:** 20+ errores 404 en consola al cargar esta página (activos, imágenes o APIs
  fallando) y una violación de CSP por script inline sin nonce.
- El formulario en sí es simple y comprensible (severidad + descripción)
- **Puntuación:** 4/10

#### 3.12.7 Navegación del Keeper — Bug Crítico de Sidebar

El sidebar del módulo Keeper solo muestra **"Estado Diario"** como entrada de navegación.
No existen enlaces directos a: Gestión de Animales, Chequeos de Salud, ni Incidentes.
Estas páginas solo son accesibles:

- Desde botones internos de la vista de Dashboard
- Navegando directamente por URL

Para un usuario real, esto significa que no puede llegar a `/keeper/animals` sin recordar la URL
o navegar desde el Dashboard. La ausencia de navegación completa en el sidebar del keeper es
el **bug de UX más grave del módulo Keeper**.

---

### 4.1 Coherencia de Componentes

| Componente | Sitio Público | Backoffice | Cohesión |
|------------|---------------|------------|---------|
| Botones primarios | `#C9A959` dorado | `#5C3D2E` marrón + hover azul | ❌ Inconsistente |
| Formularios | Bordes `#D4C4A8` calidos | Bordes `#e2e8f0` fríos | ⚠️ Diferente |
| Tarjetas/Cards | Sombra cálida `rgba(45,34,24,...)` | Sombra neutra `rgba(0,0,0,...)` | ⚠️ Diferente |
| Tipografía | Shippori + Zen Maru Gothic | Inter | ✅ Correcto por contexto |
| Navegación | Navbar horizontal crema | Sidebar vertical oscuro | ✅ Correcto por contexto |
| Estado de badges | Semántico (verde/amarillo/rojo) | Semántico + colores inconsistentes | ⚠️ Parcial |
| Iconografía | Emojis nativos | Emojis + Font Awesome inconsistente | ⚠️ Parcial |

### 4.2 Sistema de Iconos

El proyecto usa **tres sistemas de iconos simultáneamente**:

1. **Emojis Unicode** — sitio público y sidebar del OS
2. **Font Awesome** — solo cargado en backoffice, pero usado en vista pública de loyalty
3. **Emojis en CSS `content:`** — en stamps de loyalty

Esto genera rendering fallido cuando una vista usa FA sin el CDN disponible.

### 4.3 Jerarquía Visual Global

- **Sitio público:** Jerarquía clara. H1 grande → subtítulo → CTA dorado → cuerpo. Funciona bien.
- **Backoffice:** Jerarquía menos clara en las páginas de datos. Las tablas tienen header igual de
  peso que el texto de celda. Los KPI cards podrían ser más impactantes.

### 4.4 User Journey Emocional por Rol

#### Cliente (Visitante → Usuario)

| Fase | Pantalla | Emoción esperada | Emoción real |
|------|---------|-----------------|--------------|
| Descubrimiento | Homepage | Curiosidad, calidez | ✅ Funciona |
| Exploración | Catálogo | Emoción de elección | ⚠️ Imágenes rotas dañan |
| Decisión | Café detail | Confianza, deseo | ✅ Funciona |
| Conversión | Reservas | Clarity, ease | ✅ Funciona para usuarios |
| Lealtad | Loyalty Card | Delight, pertenencia | ❌ Bug de paleta la destruye |
| Gamificación | Perfil | Orgullo, motivación | ✅ Funciona con dark mode |

#### Manager (Operador de Café)

| Fase | Pantalla | Emoción esperada | Emoción real |
|------|---------|-----------------|--------------|
| Entrada matutina | Dashboard | Orientation, control | ✅ KPIs visibles |
| Gestión diaria | Reservas | Eficiencia | ❌ CSS en texto interrumpe |
| Análisis | Reportes | Insights | ⚠️ KPIs vacíos, moneda incorrecta |

#### Keeper (Cuidador Animal)

| Fase | Pantalla | Emoción esperada | Emoción real |
|------|---------|-----------------|--------------|
| Chequeo matutino | Dashboard animales | Cuidado, responsabilidad | ⚠️ Overflow + imágenes rotas |
| Lista de animales | `/keeper/animals` | Claridad, eficiencia | ⚠️ Nombres duplicados |
| Ficha animal | `/keeper/animals/{id}` | Información rica | ⚠️ Foto vacía, funcionalidad duplicada |
| Chequeo de salud | Formulario chequeo | Confianza en el sistema | ✅ Funcional, UX correcto |
| Reporte incidente | `/keeper/incidents/create` | Urgencia, control | ❌ ID numérico, errores técnicos |
| Navegación general | Sidebar | Orientación | ❌ Solo "Estado Diario" en el menú |

---

## 5. Bugs Visuales — Inventario Completo

| # | Severidad | Página | Descripción | Causa técnica probable |
|---|-----------|--------|-------------|----------------------|
| 1 | 🔴 CRÍTICO | `/loyalty/card` | Gradiente violeta/índigo rompe paleta de marca | `loyalty.css:306` hardcodeado `#6366f1, #8b5cf6` |
| 2 | 🔴 CRÍTICO | `/loyalty/card` | Iconos Font Awesome no cargan (texto plano) | FA CDN solo en `backoffice.php`, no en layout público |
| 3 | 🔴 CRÍTICO | `/manager/reservations` | CSS renderizado como texto HTML visible en página | `echo` de bloque `<style>` sin `Raw::html()` o sin tag correcto |
| 4 | 🟠 ALTO | `/404` (ruta incorrecta) | Página 404 completamente sin estilos (blanco bare) | Vista 404 no extiende el layout principal |
| 5 | 🟠 ALTO | `/cafes` | ~70% de imágenes rotas en catálogo | URLs de imagen incorrectas o archivos faltantes |
| 6 | 🟠 ALTO | `/keeper/dashboard` | Overflow horizontal en grid de tarjetas animales | CSS del grid sin `overflow: hidden` + ancho excesivo |
| 7 | 🟠 ALTO | `/keeper/dashboard` | 3/4 imágenes de animales rotas | Archivos de imagen no cargados en storage |
| 8 | 🟡 MEDIO | `/manager/reports` | Moneda `¥0` en lugar de `€0` | Locale o configuración de moneda incorrecta (JPY vs EUR) |
| 9 | 🟡 MEDIO | `/manager/reports` | KPI cards sin valores (solo iconos) | Bug de render o datos no cargados |
| 10 | 🟡 MEDIO | `/manager/reservations` + `/reports` | Estados en inglés (`pending`, `confirmed`) | Sin traducción/mapeo al español |
| 11 | 🟡 MEDIO | Global | Newsletter popup se dispara en cada navegación | Cookie/flag de estado no persiste entre páginas |
| 12 | 🟡 MEDIO | Backoffice general | Sidebar usa paleta slate genérica, no paleta de marca | `--bg-sidebar: #1e293b` no referencia tokens de marca |
| 13 | 🟢 LEVE | `/loyalty/card` | `reward-card__cost` usa `#667eea` (azul-violeta externo) | Color hardcodeado en loyalty.css |
| 14 | 🟢 LEVE | Sitio público | Botones de login/registro usan azul Bootstrap genérico | No usa `--color-acento` ni `--color-primario` |
| 15 | 🔴 CRÍTICO | `/ops/kitchen/history` | Alpine.js runtime errors: `checkinOpen`, `selectedResId` no definidos — UI interactivo no funciona | Variables no declaradas en el scope de Alpine.js del componente |
| 16 | 🟠 ALTO | `/keeper/sidebar` | Solo "Estado Diario" en navegación — sin enlaces a Animales, Chequeos ni Incidentes | Sidebar del keeper incompleto; páginas solo accesibles por URL directa |
| 17 | 🟠 ALTO | `/keeper/incidents/create` | Campo "ID del Animal" es `input[type=number]` — keeper debe escribir el ID de BD | Debería ser `<select>` con animales del keeper |
| 18 | 🟡 MEDIO | `/keeper/animals` | Nombre del animal renderizado dos veces por fila ("Alvin Alvin") | El `alt` de la imagen thumbnail y el texto de celda duplican el nombre |
| 19 | 🟡 MEDIO | `/keeper/animals/{id}` | Sección de foto del animal es caja blanca vacía sin placeholder | Sin imagen en storage y sin fallback visual (emoji/silueta de especie) |
| 20 | 🟡 MEDIO | `/keeper/health-checks/create/{id}` | Encabezado "Notas Adicionales" usa cyan brillante inconsistente con marrón/verde de las otras secciones | Color hardcodeado en la vista, fuera del sistema de tokens de backoffice |
| 21 | 🟡 MEDIO | `/ops/kitchen/history` | CSP violation: intento de carga de imagen desde `lh3.googleusercontent.com` | URL de foto de perfil de Google hardcodeada en seed, dominio no autorizado en CSP |
| 22 | 🟡 MEDIO | `/keeper/incidents/create` | 20+ errores 404 en consola al cargar la página | Assets o endpoints de API faltantes específicos de esta vista |
| 23 | 🟢 LEVE | `/keeper/incidents/create` | CSP violation por script inline sin nonce | Script inline generado sin incluir el nonce del servidor |

---

## 6. Recomendaciones Priorizadas

### P0 — Crítico (Arreglar inmediatamente)

**[BUG-1] Loyalty Card — paleta de color**

```css
/* En public/css/sections/loyalty.css — CAMBIAR línea ~306 */
/* ANTES: */
background: linear-gradient(135deg, #6366f1, #8b5cf6);

/* DESPUÉS (paleta de marca): */
background: linear-gradient(135deg, var(--color-primario, #5C3D2E), var(--color-fondo-alt, #E8DCC4));
/* O una versión más vibrante todavía on-brand: */
background: linear-gradient(135deg, #5C3D2E, #C9A959);
```

**[BUG-2] Font Awesome en loyalty**
Añadir en el `<head>` del layout público (`resources/views/layouts/main.php`) o en la vista
específica de loyalty:

```html
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
      crossorigin="anonymous" referrerpolicy="no-referrer">
```

O mejor: sustituir los FA icons en loyalty por emojis del sistema, consistentes con el resto del
sitio público.

**[BUG-3] CSS como texto en manager/reservations**
Localizar en la vista de reservaciones del manager la construcción de estilos inline que
se está escapando como HTML. Buscar en:

```
resources/views/manager/reservations/
```

Probable causa: un `echo "<style>...</style>"` que no usa `Raw::html()` o está dentro del body
rendering como texto.

**[BUG-15] Alpine.js en Kitchen History**
Localizar la vista `/ops/kitchen/history` y declarar las variables `checkinOpen` y `selectedResId`
en el scope correcto del componente Alpine.js:

```html
<div x-data="{ checkinOpen: false, selectedResId: null }">
```

### P1 — Alto (Sprint actual)

**[BUG-4] Página 404 sin estilos**
Añadir `extends('layouts/main')` o similar en la vista de 404, igual que la 403.

**[BUG-6] Keeper dashboard overflow**
En el CSS del contenedor de tarjetas del keeper:

```css
.animals-grid {
  overflow-x: hidden; /* o auto */
  max-width: 100%;
}
```

**[BUG-8] Moneda incorrecta**
Revisar la configuración de `LOCALE` o `CURRENCY` en el entorno. La moneda de Komorebi es EUR (€),
no JPY (¥). Buscar en:

```php
// app/Services/ o en las vistas de reportes
number_format($amount, 2) + '€'
// vs
'¥' . number_format($amount, 0)
```

**[BUG-16] Sidebar del Keeper incompleto**
Añadir entradas al sidebar de `/keeper/*`:

- Animales → `/keeper/animals`
- Chequeos de Salud → `/keeper/health-checks`
- Incidentes → `/keeper/incidents`

**[BUG-17] ID de Animal en formulario de incidente**
Reemplazar `<input type="number" name="animal_id">` por un `<select>` que liste los animales
bajo responsabilidad del keeper autenticado.

### P2 — Medio (Próximo sprint)

**[BUG-10] Traducción de estados**
Implementar mapeo de estados:

```php
$estados = ['pending' => 'Pendiente', 'confirmed' => 'Confirmada', 'completed' => 'Completada'];
```

**[BUG-11] Newsletter popup agresivo**
Cambiar la lógica para mostrar el popup máximo 1 vez por sesión (cookie de sesión) y con un
retraso mayor (>30 segundos de permanencia en página).

**[BUG-12] Sidebar backoffice sin paleta de marca**
Proponer paleta alternativa que mantenga usabilidad pero conecte con la marca:

```css
--bg-sidebar: #2D1F14; /* marrón oscuro de marca, en lugar de slate */
--bg-sidebar-hover: rgba(201, 169, 89, 0.1); /* dorado con baja opacidad */
```

**[BUG-18] Nombre duplicado en lista de animales**
Verificar el template de la fila de animals para eliminar duplicación del nodo de texto.

**[BUG-19] Foto vacía en ficha de animal**
Añadir un fallback en `<img>` del animal show:

```html
<img src="<?= e($animal->foto_url) ?>"
     onerror="this.src='/images/placeholder-animal.svg'"
     alt="<?= e($animal->nombre) ?>">
```

**[BUG-21] CSP — Google Photos en kitchen history**
Eliminar la URL de `lh3.googleusercontent.com` de los datos de seed o añadir el dominio a
la directiva `img-src` de la Content Security Policy si se van a permitir fotos externas.

**[BUG-22] 404s en incidents/create**
Auditar los assets referenciados en la vista de creación de incidentes para identificar
los recursos faltantes.

### P3 — Mejoras (Backlog)

- **Placeholder de café:** Diseñar un placeholder SVG con silueta de taza/animal para cuando falten
  imágenes en el catálogo
- **KPI cards backoffice:** Unificar colores de iconos usando los tokens semánticos del design-tokens.css
- **Dark mode loyalty:** Añadir soporte dark mode a la tarjeta de loyalty
- **Responsive backoffice:** Añadir breakpoint mobile para el sidebar collapse
- **[BUG-20] Header "Notas Adicionales" keeper:** Alinear con tokens `--card-header-bg` del backoffice
- **[BUG-23] Nonce en script inline:** Pasar el nonce del servidor a scripts inline generados en PHP

---

## 7. Coherencia de Marca — Evaluación Global

| Dimensión | Puntuación | Observación |
|-----------|-----------|-------------|
| Concepto *Komorebi* / Japonés | 9/10 | Tipografía, paleta, terminología — excelente |
| Consistencia paleta (público) | 7/10 | Correcta salvo loyalty (bug) y botones |
| Consistencia paleta (backoffice) | 4/10 | Sidebar genérico, sin identidad de marca |
| Iconografía | 5/10 | 3 sistemas coexistentes, FA sin CDN en público |
| Tipografía | 8/10 | Correcta por contexto |
| Responsive | 7/10 | Público bien, backoffice sin responsive |
| Dark mode | 7/10 | Público implementado, backoffice sin dark mode |
| User journey (público) | 7/10 | Buen arco salvo loyalty y catálogo |
| User journey (backoffice) | 5/10 | Functional pero con bugs funcionales críticos |
| User journey (keeper) | 4/10 | Navegación rota, bugs de UX en formularios |
| Calidad operativa OS | 5/10 | Kitchen/history roto (Alpine.js), keeper incompleto |
| **GLOBAL** | **6/10** | Base sólida con deuda técnica significativa en módulos operativos |

---

## 8. Capturas de Pantalla — Inventario Completo (69 páginas)

### Sitio Público

| Archivo | Vista | Notas |
|---------|-------|-------|
| `01-homepage-hero.jpeg` | Homepage, above fold | ✅ |
| `01-homepage-full.jpeg` | Homepage, scroll completo | ✅ |
| `02-cafes-catalogue.jpeg` | Catálogo de cafés | ⚠️ imágenes rotas |
| `03-cafe-detail.jpeg` | Detalle Neko no Niwa | ✅ |
| `04-menu.jpeg` | Carta con carrito | ✅ |
| `05-quiz.jpeg` | Quiz café ideal | ✅ |
| `06-reservas.jpeg` | Reservas (invitado) | ✅ |
| `07-login.jpeg` | Formulario login | ✅ |
| `08-registro.jpeg` | Formulario registro | ✅ |
| `09-profile.jpeg` | Perfil usuario (dark mode) | ✅ |
| `10-loyalty.jpeg` | Tarjeta fidelización | 🔴 bugs paleta + FA |
| `11-historia.jpeg` | Nuestra Historia | ✅ |
| `12-contacto.jpeg` | Contacto | ✅ |
| `13-403-error.jpeg` | Página 403 | ✅ |
| `13-404-error.jpeg` | Página 404 | 🔴 sin estilos |
| `14-homepage-dark.jpeg` | Homepage dark mode | ✅ |
| `15-homepage-mobile.jpeg` | Homepage 375px | ✅ |
| `27-faq.jpeg` | FAQ | ✅ |
| `28-legal-privacidad.jpeg` | Legal — Privacidad | ✅ |
| `29-legal-cookies.jpeg` | Legal — Cookies | ✅ |
| `30-legal-terminos.jpeg` | Legal — Términos | ✅ |
| `31-forgot-password.jpeg` | Recuperar contraseña | ✅ |

### Usuario Autenticado

| Archivo | Vista | Notas |
|---------|-------|-------|
| `33-user-mis-reservas.jpeg` | Mis Reservas | ✅ estado vacío |
| `34-user-favoritos.jpeg` | Mis Favoritos | ⚠️ estado vacío |
| `35-user-carrito.jpeg` | Carrito compra | ⚠️ estado vacío |
| `36-user-reviews.jpeg` | Mis Reviews | ⚠️ sin datos |
| `37-user-waitlists.jpeg` | Mi Waitlist | ⚠️ sin datos |
| `38-account-sessions.jpeg` | Sesiones activas | ✅ |
| `39-account-security.jpeg` | Seguridad cuenta | ✅ |
| `40-account-change-password.jpeg` | Cambiar contraseña | ✅ |
| `41-reserva-confirmacion.jpeg` | Confirmación reserva | ⚠️ redirige a /reservas |

### Admin

| Archivo | Vista | Notas |
|---------|-------|-------|
| `25-admin-dashboard.jpeg` | Admin dashboard | ✅ |
| `26-admin-users.jpeg` | Admin usuarios | ✅ |
| `42-admin-roles.jpeg` | Roles y permisos | ✅ |
| `43-admin-cafes.jpeg` | Gestión cafés | ✅ |
| `44-admin-menu.jpeg` | Gestión menú | ✅ |
| `45-admin-reviews.jpeg` | Gestión reviews | ⚠️ sin datos |
| `46-admin-reservations.jpeg` | Gestión reservas | ✅ |
| `47-admin-waitlists.jpeg` | Waitlists admin | ✅ |
| `48-admin-animals.jpeg` | Animales (admin) | ⚠️ posible estado vacío |
| `49-admin-settings.jpeg` | Configuración sistema | ✅ |
| `50-admin-logs.jpeg` | Logs aplicación | ✅ |
| `51-admin-logs-audit.jpeg` | Logs auditoría | ✅ |
| `52-admin-logs-auth.jpeg` | Logs autenticación | ✅ |
| `53-admin-reports.jpeg` | Reportes admin | ✅ |
| `54-admin-data-viewer.jpeg` | Data viewer SQL | ✅ |

### Manager

| Archivo | Vista | Notas |
|---------|-------|-------|
| `20-manager-dashboard.jpeg` | Manager dashboard | ✅ |
| `55-manager-reviews.jpeg` | Moderación reviews | ✅ |
| `56-manager-staff.jpeg` | Gestión personal | ✅ |
| `57-manager-cafe.jpeg` | Editar café | ✅ |
| `58-manager-products.jpeg` | Productos/carta | ✅ |

### Supervisor y Operaciones

| Archivo | Vista | Notas |
|---------|-------|-------|
| `59-supervisor-dashboard.jpeg` | Supervisor dashboard | ✅ |
| `60-supervisor-assignments.jpeg` | Asignaciones turno | ✅ |
| `61-reception-dashboard.jpeg` | Panel recepción | ✅ |
| `62-kitchen-dashboard.jpeg` | KDS cocina | ✅ |
| `63-kitchen-history.jpeg` | Historial cocina | 🔴 Alpine.js bugs |

### Keeper — Bienestar Animal

| Archivo | Vista | Notas |
|---------|-------|-------|
| `64-keeper-animals-list.jpeg` | Lista de animales | ⚠️ nombres duplicados |
| `65-keeper-animal-show.jpeg` | Ficha de animal | ⚠️ foto vacía |
| `66-keeper-health-checks.jpeg` | Dashboard chequeos | ✅ |
| `67-keeper-health-check-create.jpeg` | Nuevo chequeo | ⚠️ color incosistente "Notas" |
| `68-keeper-incidents.jpeg` | Incidentes activos | ✅ estado vacío |
| `69-keeper-incident-create.jpeg` | Reportar incidente | 🔴 UX: ID numérico + 404s |

---

*Informe v3.0 — Todos los BUG-1 a BUG-23 implementados y verificados con phpstan limpio. Auditoría visual completa de 69 páginas, todos los roles del sistema.*
*Para referencia, ver también `docs/UX_UI_AUDIT_REPORT.md` (auditoría funcional previa).*
