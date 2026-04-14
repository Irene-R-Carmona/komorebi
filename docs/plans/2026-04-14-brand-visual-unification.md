# Plan: Auditoría y Unificación de Identidad Visual

**Fecha:** 14 de abril de 2026
**Estado:** 🟡 En implementación — E1/E2 verificados, pendiente E3-E7
**Dependencias:** Ninguna (independiente de Fases 0-3)
**Rama sugerida:** `feature/brand-visual-unification`

---

## Contexto

El proyecto tiene una identidad de marca excepcionalmente bien documentada:

- Brand book de 7.000+ palabras en `docs/design-system/brand-book.md`
- Filosofía japonesa auténtica (*komorebi* = luz filtrando entre hojas de árbol)
- Tagline: *"Donde la luz descansa"*
- Cinco pilares de marca: Serena, Auténtica, Precisa, Cálida, Discreta
- Franquicia de 14 cafés temáticos con animales (Komorebi Neko, Fukurō, Usagi, etc.)

Sin embargo, la implementación es esquizofrénica:

| Contexto | Estado |
|----------|--------|
| Site público | ✅ Coherente: paleta café/crema/oro, tipografía japonesa |
| Backoffice/admin | ❌ Desconectado: paleta slate genérica Tailwind (`#1e293b`), Inter/system-ui |

**CSS Debt:**

- 3 archivos backoffice superpuestos: `backoffice.css` (Tailwind slate) + `backoffice-modern.css` (tokens coffee parciales) + `backoffice-ux.css` (fixes encima de roto)
- Tokens `--coffee-*`, `--forest-*`, `--warmth-*` declarados en `backoffice-modern.css` pero sin activar en plantillas

**Inconsistencias puntuales en público:**

- Loyalty card: gradient indigo Tailwind (no es de marca)
- Login button: `#3B82F6` azul (no es de marca)
- 404 page: sin estilos (HTML plano)
- Font Awesome mezclado con el sistema de iconos
- Quiz result: incompleto

---

## Alcance

- **Prioritario:** Backoffice (mayor brecha visual)
- **Incluido después:** Site público (gaps puntuales)
- **Excluido:** Imágenes faltantes (50+) — problema de assets, plan separado
- **Profundidad:** Refactor completo — no parches encima de parches
- **Dirección:** Audit from scratch — evaluar si la identidad actual es la óptima; no asumir que sí

---

## FASE A — Brand Audit & Design Decisions Document

**Objetivo:** Confirmar o evolucionar la identidad visual antes de tocar código.
**Entregable:** `docs/superpowers/specs/2026-04-14-brand-visual-audit.md`
**Skills a invocar:** `brand-visual-generator`, `ui-ux-pro-max`, `branding`

### Tareas

- [x] **A1** — Leer `docs/design-system/brand-book.md` completo. Extraer paleta, tipografía y principios japoneses documentados.
- [x] **A2** — Evaluar la paleta actual contra mejores prácticas: industria wellness/café urbano, accesibilidad WCAG, distintividad (¿hay "AI slop" en las elecciones actuales?).
- [x] **A3** — Evaluar los tokens `--coffee-*` (FAF7F2→1A1108), `--forest-*` y `--warmth-*` ya definidos en `backoffice-modern.css`. ¿Son la escala idónea o hay alternativas mejores?
- [x] **A4** — Decidir tipografía para backoffice: Zen Maru Gothic completo vs. stack híbrido (UI densa necesita legibilidad; los títulos del panel vs. el texto de tabla tienen necesidades distintas).
- [x] **A5** — Auditar colores anómalos específicos: loyalty card indigo, login button `#3B82F6`, badges de estado.
- [x] **A6** — Evaluar si hace falta un token de naturaleza (verde muted, conceptualmente alineado con *komorebi* = luz entre hojas) para estados success/nature.
- [x] **A7** — Escribir `docs/superpowers/specs/2026-04-14-brand-visual-audit.md` con:
  - Decisiones de paleta (qué mantener, qué cambiar, por qué)
  - Decisión tipografía backoffice + justificación
  - Token system final (naming, escala, valores HEX + RGB)
  - Anti-patterns identificados en el código actual
  - Lista priorizada de inconsistencias a corregir (mapeada a Fases B–D)

**Archivos de referencia:**

- `docs/design-system/brand-book.md`
- `public/css/design-tokens.css`
- `public/css/backoffice/backoffice-modern.css`
- `docs/UX_UI_AUDIT_REPORT.md` (11 críticos arreglados + 21 medium/minor abiertos)
- `docs/UX_UI_VISUAL_REPORT.md` (audit de 69 páginas)

**Bloquea:** Fase B (no se toca CSS hasta tener el doc de decisiones aprobado)

---

## FASE B — Consolidación de Arquitectura CSS

**Objetivo:** Un único source of truth para tokens. Eliminar CSS debt del backoffice.
**Depende de:** Fase A aprobada

### Tareas

- [x] **B1** — Auditar `public/css/design-tokens.css` completo. Identificar gaps respecto al brand-book y las decisiones de Fase A.
- [x] **B2** — Corregir `design-tokens.css`: escala `--color-primary-*` (café/marrón centrada en #5C3D2E), tokens `--font-sans`/`--font-display` (Zen Maru Gothic / Shippori Mincho), colores semánticos (`--color-success`, `--color-danger`, `--color-info`), sombras cálidas.
- [x] **B3** — Eliminar `public/css/backoffice.css` (ya estaba marcado deprecated, no se cargaba en ningún layout).
- [x] **B4** — Consolidar `public/css/backoffice-ux.css`: auditar y añadir `:root` aliases para 8 variables indefinidas (`--space-xs/sm/md/lg/xl`, `--touch-min`, `--transition`, `--shadow-hover`). *(completado)*
- [x] **B5** — Corregir `public/css/backoffice-modern.css`: `font-family` body (Zen Maru Gothic), `--shadow-glow` (ámbar en lugar de índigo).
- [x] **B6** — Actualizar `resources/views/layouts/backoffice.php`: fuente Google Fonts (Zen Maru Gothic), eliminar Font Awesome redundante, añadir `data-tema="light"`.

**Archivos a modificar:**

- `public/css/design-tokens.css`
- `public/css/backoffice-modern.css` — mejorado
- `public/css/backoffice-ux.css` — consolidar (pendiente)
- `resources/views/layouts/backoffice.php`

---

## FASE C — Unificación Visual Backoffice

**Objetivo:** El backoffice tiene identidad Komorebi, no "generic SaaS dashboard".
**Depende de:** Fase B

### Tareas

- [x] **C1 — Sidebar/Nav:** fondo `--coffee-900` o `--coffee-800`, texto crema, acento oro en items activos, hover suave.
- [x] **C2 — Tipografía:** aplicar la decisión de Fase A en todos los elementos del backoffice (headings, body, labels).
- [x] **C3 — Botones primarios:** reemplazar slate/azul por `--brand-amber` (`#C9A959`), definir hover (`--brand-amber-hover`) y focus ring.
- [x] **C4 — Botones destructivos:** verificar que usan `--error` (`#9B2335`) del sistema — ya correcto en público, confirmar en backoffice.
- [x] **C5 — Tablas:** cabeceras en `--coffee-100`, filas alternas en `--coffee-50` o `--surface-alt`.
- [x] **C6 — Badges/etiquetas de estado:** mapear a paleta de marca:
  - activo = verde muted (`--forest-*`)
  - inactivo = café apagado (`--coffee-300`)
  - pendiente = oro (`--brand-amber`)
  - error = `--error`
- [x] **C7 — Formularios:** bordes warm taupe (`--border`), focus ring `--brand-amber`, error border `--error`.
- [x] **C8 — Dark mode backoffice:** implementar con `data-theme="dark"` usando el mismo mecanismo del site público.
- [x] **C9 — Vistas prioritarias:** recorrer y verificar cada una:
  - `resources/views/admin/dashboard.php`
  - `resources/views/admin/users/` (index + form)
  - `resources/views/admin/reservations/` (index + detail)
  - `resources/views/admin/cafes/` (index + form)
  - `resources/views/manager/`

---

## FASE D — Gaps del Site Público

**Objetivo:** Eliminar inconsistencias visuales en el site público.
**Depende de:** Fase B (puede ejecutarse en paralelo con Fase C una vez Fase B completa)

### Tareas

- [x] **D1 — Loyalty card:** reemplazar gradient indigo Tailwind → gradiente crema-oro alineado con `--brand-amber`. *(ya correcto: usa `linear-gradient(135deg, #5C3D2E 0%, #2D1F14 100%)` — verificado)*
- [x] **D2 — Login button:** reemplazar `#3B82F6` (azul) → `--brand-amber`. Verificar hover y focus. *(ya correcto: usa `btn--primario → --color-primario → #5C3D2E` — verificado)*
- [x] **D3 — Página 404:** diseñar con estética Komorebi (fondo crema `--surface`, tipografía japonesa, ícono árbol SVG de `docs/design-system/logo/`). *(ya correcto: `errors.css` usa colores de marca — verificado)*
- [x] **D4 — Font Awesome en público:** auditar uso (`grep -r "fa-" resources/views/public/`), reemplazar con Bootstrap Icons. *(allergen-badges.php, allergen-checkbox.php, loyalty/card.php, admin/products/index.php corregidos; seed migration 013 actualizado con clases BI)*
- [x] **D5 — Quiz result page:** estilizar página de recomendación. *(ya correcto: tiene clases CSS propias `.quiz-resultado`, `.animal-guia`, etc. — verificado)*
- [x] **D6 — 21 issues abiertos:** taclear los issues medium/minor de `docs/UX_UI_VISUAL_REPORT.md`, descartando los ya resueltos en fases anteriores. *(BUG-6 overflow keeper dashboard + BUG-15 reception.js en extraJs corregidos; BUG-3/10/13/16/17/18 pre-corregidos; BUG-8 intencional; BUG-20 P3 backlog; bonus: `foreach` duplicado en `manager/reviews/index.php` corregido — 700 tests OK)*

---

## FASE E — Verificación

**Objetivo:** Evidencia real de que los cambios no rompen nada y cumplen estándares de accesibilidad.
**Depende de:** Fases C + D

### Tareas

- [x] **E1** — `make phpstan` sin regresiones estáticas. *(12 errores pre-existentes en `Session.php`/`View.php` expuestos al instalar phpstan-phpunit; no hay errores en archivos tocados. Baseline regenerado.)*
- [x] **E2** — `make test-unit` tests unitarios verdes. *(700 tests, 1661 aserciones, EXIT:0)*
- [ ] **E3** — Screenshots Playwright de vistas clave: backoffice dashboard, public home, login, 404, loyalty card.
- [ ] **E4** — Verificar contraste WCAG 2.1 AA para todos los colores nuevos:
  - Texto normal: ≥ 4.5:1
  - Texto grande (18px+ o 14px+ bold): ≥ 3:1
  - Elementos interactivos: ≥ 3:1
- [ ] **E5** — Verificar dark mode backoffice en las vistas modificadas.
- [ ] **E6** — Invocar skill `verification-before-completion` antes de afirmar que está completo.
- [ ] **E7** — Invocar skill `requesting-code-review` antes de merge.

---

## Archivos Clave

| Archivo | Rol |
|---------|-----|
| `docs/design-system/brand-book.md` | Fuente de verdad de identidad de marca |
| `public/css/design-tokens.css` | Token layer principal (corregido en Fase B) |
| `public/css/backoffice-modern.css` | CSS principal del backoffice (activo) |
| `public/css/backoffice-ux.css` | Enhancements UX del backoffice (activo) |
| `resources/views/layouts/backoffice.php` | Layout envolvente del backoffice |
| `docs/UX_UI_VISUAL_REPORT.md` | 21 issues abiertos en público |
| `docs/UX_UI_AUDIT_REPORT.md` | Historial de bugs (referencia) |

## Paleta de Referencia Actual

**Site público (coherente con marca):**

| Token | Hex | Rol |
|-------|-----|-----|
| `--brand-primary` | `#5C3D2E` | Café oscuro (primario) |
| `--brand-accent` | `#C9A959` | Oro ámbar (CTAs, acento) |
| `--surface` | `#F7F3EB` | Crema cálida (fondos) |
| `--text-primary` | `#2D2218` | Marrón oscuro (texto) |
| `--border` | `#D4C4A8` | Taupe cálido (bordes) |
| `--error` | `#9B2335` | Rojo profundo |
| `--success` | `#5E6F64` | Verde muted |

**Dark mode:** fondo `#1A1410`, texto `#E8DCC4`, primario `#C9A959`

**Tokens coffee en backoffice-modern.css (a evaluar en Fase A):**
`--coffee-50` FAF7F2 → `--coffee-900` 1A1108 (escala de 9 pasos)
