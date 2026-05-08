# Plan: Documentación Oficial TFG — Komorebi Café (LaTeX → PDF)

**Estado:** 🔵 Plan creado — pendiente inicio  
**Fecha:** 2026-05-08  
**Estimación:** ~157 páginas PDF

---

## Datos de portada

| Campo | Valor |
|---|---|
| Título | Komorebi Café |
| Alumna | Irene Reyes Carmona |
| Centro | I.E.S. Ágora |
| Tutora | María Lourdes Calleja Flores |
| Año académico | 2025-2026 |
| Engine LaTeX | XeLaTeX + fontspec (Times New Roman) |
| Compilar | `xelatex -shell-escape documentacion-oficial.tex` (3 pases) |

---

## Decisiones técnicas finales

| Decisión | Elegido | Descartado | Motivo |
|---|---|---|---|
| Diagramas de secuencia | `pgf-umlsd` (nativo LaTeX) | `ltmermaid` | ltmermaid requiere Node en compilación; inestable |
| Glosario | `makeglossaries-lite` | `makeglossaries` | La versión original requiere Perl en Windows |
| Páginas landscape | `pdflscape` | `lscape` | pdflscape rota correctamente en visores PDF |
| Bibliografía | `biber` + `biblatex` | `natbib` | Mejor soporte Unicode y biblatex moderno |
| Estructura | Un solo `.tex` | `\input` por capítulos | Un único entregable |

**Stack de paquetes LaTeX:**
fontspec · geometry(a4,2.5cm) · babel(spanish,es-tabla) · csquotes ·
minted 3.x (-shell-escape + Pygments) · tcolorbox (minted,skins,breakable) ·
longtable · booktabs · tabularx · multirow · pdflscape ·
tikz (shapes,arrows.meta,positioning,calc,fit,backgrounds,shadows) ·
pgf-umlsd · forest · svg (Inkscape) ·
hyperref(hidelinks) · cleveref · glossaries-extra · makeglossaries-lite ·
fancyhdr · titlesec · setspace · microtype · caption · subcaption ·
float · graphicx · xcolor · enumitem · appendix(titletoc) · biblatex(biber,ieee)

---

## Fase 0 — Preparación del entorno y diagramas

### Fase 0.A — Verificación del entorno LaTeX (Windows + MiKTeX)

- [ ] **0.A.1** Verificar herramientas externas:

  ```powershell
  xelatex --version
  biber --version                        # NO viene en MiKTeX básico → mpm --install biber
  makeglossaries-lite --version          # usar -lite (Lua), no el original (Perl)
  python --version
  python -c "import pygments; print('OK Pygments', pygments.__version__)"
  inkscape --version                     # debe estar en PATH del sistema
  node --version
  ```

- [ ] **0.A.2** Pre-instalar paquetes vía MiKTeX Package Manager (PowerShell como admin):

  ```powershell
  mpm --update-db
  mpm --install minted tcolorbox pgf-umlsd forest pdflscape cleveref `
    glossaries-extra biblatex biber svg fontspec booktabs longtable `
    tabularx multirow fancyhdr titlesec setspace microtype caption `
    subcaption appendix csquotes enumitem xcolor float geometry `
    hyperref babel-spanish pgf
  ```

- [ ] **0.A.3** Verificar Inkscape en PATH (si falla, añadir `C:\Program Files\Inkscape\bin`)

- [ ] **0.A.4** Compilar test mínimo `docs/test-env.tex`:

  ```powershell
  cd docs
  xelatex -shell-escape test-env.tex
  # Sin errores fatales = entorno listo
  ```

  El `test-env.tex` ejercita: `minted` PHP, `pgf-umlsd`, `forest`, `tcolorbox`, `\includesvg`, texto español con tildes.

### Fase 0.B — Actualización de diagramas existentes (docs/diagrams/)

`architecture.md` — ✅ Correcto, sin cambios.

- [ ] **0.B.1** `docs/diagrams/request-lifecycle.md` — Ampliar de 6 a **15 middlewares**:
  - Orden: `ErrorHandler → SecurityHeaders → RequestLog → Session → CSRF → RateLimit →
    PayloadSize → Cors → Auth → ApiAuth → Role → ApiRole → Authorization → CafeScope → Idempotency`
  - Dos stacks paralelos: web vs. `/api/v1`
  - `IdempotencyMiddleware` marcado como exclusivo de `POST /api/v1/reservations`

- [ ] **0.B.2** `docs/diagrams/auth-flow.md` — Añadir **tercer diagrama**: Bearer token (API stateless):
  - Actores: Cliente API, TokenController, ApiTokenService, UserRepository, Redis
  - Flujo: `POST /api/v1/tokens` → generar token aleatorio → hash SHA-256 → almacenar
    `token_hash` + `expires_at` en `api_tokens` → devolver plain token (solo una vez)
  - Request posterior: `Authorization: Bearer <token>` → `ApiAuthMiddleware` extrae y hashea
    → verifica en BD (no revocado, no expirado) → autentica sin sesión

- [ ] **0.B.3** `docs/diagrams/reservation-flow.md` — Añadir dos elementos:
  - Paso previo a `create()`: `IdempotencyMiddleware` verifica UUID v4 en header →
    hit Redis (respuesta cacheada) o miss (continúa, guarda 24h)
  - Nota final: "`invoice_pdf_url` es NULL en la confirmación; se asigna de forma
    asíncrona tras generar el PDF"

- [ ] **0.B.4** `docs/diagrams/er.puml` — 4 cambios:
  - Añadir entidad `api_tokens` (id, user_id FK→users, name, token_hash UNIQUE,
    abilities, expires_at, last_used_at, revoked_at, created_at) + relación `users ||--o{ api_tokens`
  - Añadir columna `reservations.invoice_pdf_url VARCHAR(500) nullable`
  - Añadir columna `animal_incidents.resolution TEXT nullable`
  - Actualizar enum `animal_incidents.severity`: `('low','medium','high','critical')`

---

## Fase 1 — Preparación de assets visuales

- [ ] **1.1** Crear directorio `docs/images/logos/`
- [ ] **1.2** Copiar `public/images/logos/komorebi-logo-stacked.svg` → `docs/images/logos/`
- [ ] **1.3** Copiar `public/images/logos/komorebi-logo-icon.svg` → `docs/images/logos/`

---

## Fase 2 — Capturas de pantalla (~62 capturas con Playwright MCP)

**Login:** `admin@komorebi.local` / `Admin123!`  
**Destino:** `docs/screenshots/`

- [ ] **2.1** Páginas públicas (12): `01-home` · `02-cafes-lista` · `03-cafe-detalle` ·
  `04-menu-catalogo` · `05-menu-filtros` · `06-quiz-inicio` · `07-quiz-resultado` ·
  `08-historia` · `09-faq` · `10-contacto` · `11-cookies-banner` · `12-cookies-preferencias`

- [ ] **2.2** Autenticación (6): `13-login-form` · `14-registro-form` · `15-forgot-password` ·
  `16-forgot-password-ok` · `17-reset-password-form` · `18-verify-email`

- [ ] **2.3** Usuario autenticado (11): `19-perfil` · `20-perfil-seguridad` ·
  `21-perfil-sesiones` · `22-mis-reservas` · `23-reservar-step1` · `24-reservar-step2` ·
  `25-reservar-confirmacion` · `26-loyalty-card` · `27-favoritos` · `28-carrito` ·
  `29-waitlist-status`

- [ ] **2.4** Admin backoffice (19): `30-admin-dashboard` · `31-admin-usuarios` ·
  `32-admin-cafes` · `33-admin-menu` · `34-admin-reservas` · `35-admin-animales` ·
  `36-admin-reviews` · `37-admin-loyalty` · `38-admin-newsletter` · `39-admin-reportes` ·
  `40-admin-settings` · `41-admin-logs-auditoria` · `42-admin-logs-auth` ·
  `43-admin-waitlists` · `44-admin-roles` · `45-admin-menu-crear` ·
  `46-admin-usuario-crear` · `47-admin-animal-crear` · `48-admin-reserva-detalle`

- [ ] **2.5** Manager (5): `49-manager-dashboard` · `50-manager-productos` ·
  `51-manager-staff` · `52-manager-reservas` · `53-manager-reportes`

- [ ] **2.6** Operaciones (4): `54-reception-dashboard` · `55-reception-reservas` ·
  `56-kitchen-orders` · `57-kitchen-history`

- [ ] **2.7** Keeper (5): `58-keeper-dashboard` · `59-keeper-animals` ·
  `60-keeper-health-checks` · `61-keeper-health-check-crear` · `62-keeper-incidents`

---

## Fase 3 — Generar docs/documentacion-oficial.tex

### Compilación

```powershell
cd docs
xelatex -shell-escape documentacion-oficial.tex   # pase 1
biber documentacion-oficial                        # bibliografía
makeglossaries-lite documentacion-oficial          # glosario (sin Perl)
xelatex -shell-escape documentacion-oficial.tex   # pase 2
xelatex -shell-escape documentacion-oficial.tex   # pase 3 — TOC + refs estables
```

### Estructura del documento

```
\frontmatter
  Portada (logo SVG, datos alumna/tutora/centro)
  Resumen (ES + EN)
  Agradecimientos
  Índice de contenidos (TOC)
  Índice de figuras
  Índice de tablas

\mainmatter
  Capítulo 1 — Introducción General
    1.1 Origen y concepto (komorebi, brand-book)
    1.2 Los 14 cafés temáticos
    1.3 Misión, visión y valores
    1.4 Alcance y finalidad del proyecto
    1.5 Stack tecnológico (tabla)
    1.6 Metodología (Scrum + Trello)

  Capítulo 2 — Desarrollo Técnico / Manual Técnico
    2.1 Arquitectura del sistema (C4 Context + Container — TikZ)
    2.2 Ciclo de petición HTTP (TikZ flowchart — 15 middlewares)
    2.3 Flujos de autenticación (3 diagramas pgf-umlsd)
    2.4 Flujo de reservas con idempotencia (pgf-umlsd)
    2.5 Modelo entidad-relación (TikZ + tabla columnas)
    2.6 Implementación frontend (Alpine.js, views, CSS)
    2.7 Implementación backend
      2.7.1 Rutas (~150) — longtable landscape
      2.7.2 Catálogo de servicios (48) — longtable
      2.7.3 Repositorios (29) — longtable
      2.7.4 API REST (~80 endpoints) — longtable landscape
    2.8 Pruebas (PHPUnit, cobertura, E2E Playwright)
    2.9 Despliegue (Docker Compose, Railway, 12-Factor)
    2.10 Git Flow y control de versiones
    2.11 Estructura de directorios (forest)
    2.12 APIs externas y servicios de terceros

  Capítulo 3 — Manual de Usuario
    3.1 Área pública (12 capturas)
    3.2 Autenticación y cuenta (6 capturas)
    3.3 Usuario registrado (11 capturas)
    3.4 Administrador — backoffice (19 capturas)
    3.5 Manager (5 capturas)
    3.6 Recepción y Cocina (4 capturas)
    3.7 Keeper (5 capturas)

  Capítulo 4 — Conclusiones y Futuras Mejoras
    4.1 Objetivos alcanzados
    4.2 Aprendizajes técnicos
    4.3 Futuras mejoras propuestas

  Capítulo 5 — Referencias Bibliográficas

\backmatter (Apéndices)
  Anexo A — Catálogo completo API REST
  Anexo B — Esquema de base de datos (19 migraciones)
  Anexo C — Variables de entorno
  Anexo D — Glosario de términos
```

- [ ] **3.1** Crear `docs/test-env.tex` y compilar (validación entorno)
- [ ] **3.2** Crear `docs/documentacion-oficial.tex` — \frontmatter (portada, resumen, TOC)
- [ ] **3.3** Capítulo 1 — Introducción General
- [ ] **3.4** Capítulo 2 — Desarrollo Técnico (diagramas TikZ/pgf-umlsd, tablas longtable)
- [ ] **3.5** Capítulo 3 — Manual de Usuario (62 capturas)
- [ ] **3.6** Capítulos 4, 5 y Apéndices
- [ ] **3.7** Compilación completa (3 pases) y verificación de páginas (≥ 100)

---

## Archivos que se crean

```
docs/
  test-env.tex                          ← test de entorno (descartable tras Fase 0.A)
  documentacion-oficial.tex             ← entregable principal
  images/
    logos/
      komorebi-logo-stacked.svg
      komorebi-logo-icon.svg
  screenshots/
    01-home.png … 62-keeper-incidents.png
```

---

## Checklist de verificación final

- [ ] `test-env.tex` compila sin errores fatales (Fase 0.A)
- [ ] `documentacion-oficial.tex` compila sin errores fatales (3 pases)
- [ ] PDF ≥ 100 páginas (objetivo ~157)
- [ ] TOC, índice de figuras e índice de tablas generados y numerados
- [ ] Todas las capturas visibles y referenciadas con `\cref`
- [ ] Logos SVG en portada renderizados correctamente
- [ ] Tipografía: portada 20 pt, subtítulos portada 18 pt, cuerpo 12 pt
- [ ] Tildes, ñ y comillas «» correctas (sin caracteres extraños)
- [ ] Tablas `longtable` en `pdflscape` sin desbordamiento
- [ ] Las 5 secciones de la plantilla TFG cubiertas
