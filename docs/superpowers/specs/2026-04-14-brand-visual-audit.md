# Auditoría Visual Komorebi Café — Decisiones de Diseño

**Fecha:** 2026-04-14
**Alcance:** Backoffice (prioridad) + Sitio público
**Profundidad:** Refactor completo desde cero
**Fase:** A — Documento de decisiones previo a implementación

---

## 1. Fuente de verdad

`docs/design-system/brand-book.md` es el documento canónico. Toda decisión de implementación
debe alinearse con él. Donde contradicción exista entre `design-tokens.css` y `brand-book.md`,
gana el brand book.

---

## 2. Paleta de color

### 2.1 Paleta primaria (Confirmada ✅)

| Token CSS actual | Valor | Nombre |
|---|---|---|
| `--color-primario` / `--admin-primary` | `#5C3D2E` | Café Oscuro |
| `--color-acento` / `--admin-accent` | `#C9A959` | Ámbar Dorado |
| `--color-fondo` | `#F7F3EB` | Crema Cálida |
| `--color-texto` | `#2D2218` | Marrón Oscuro |
| `--color-fondo-alt` | `#E8DCC4` | Crema Oscura |
| `--color-texto-suave` | `#5C4A3A` | Marrón Medio |
| `--color-borde` | `#D4C4A8` | Crema Borde |

**Decisión:** Esta paleta es *correcta y auténtica*. No tiene AI slop. Caté/crema/oro es
genuinamente coherente con la identidad de un café japonés. **No cambiar.**

### 2.2 Bug crítico: `--color-primary-500` en design-tokens.css

**Problema:** El archivo `design-tokens.css` define una escala `--color-primary-*` como
"Bamboo Green" con `--color-primary-500: #3a9263`. Esta variable se usa activamente en:

- `button.css` líneas 61, 95, 97 → color de fondo del botón primary, borde, focus outline
- `stat-card.css`, `badge.css`, `modal.css`, `card.css` → focus outlines y bordes

Resultado: elementos UI muestran **verde bambú** en lugar de café oscuro. Bug visual real.

**Decisión:** Reemplazar toda la escala `--color-primary-*` por una escala café/marrón
centrada en `#5C3D2E` como `--color-primary-500` (el tono base).

### 2.3 Escala de color primario (Nueva escala café)

```css
--color-primary-50:  #faf7f4;   /* crema muy clara */
--color-primary-100: #f2ebe2;
--color-primary-200: #e2d0bf;
--color-primary-300: #cdb09a;
--color-primary-400: #b48e74;
--color-primary-500: #5C3D2E;   /* Café Oscuro — base */
--color-primary-600: #4a2f23;
--color-primary-700: #3d261c;
--color-primary-800: #2D2218;   /* Marrón Oscuro */
--color-primary-900: #1e170f;
```

### 2.4 Colores semánticos — Bug crítico

**Problema:** `design-tokens.css` tiene colores semánticos de alta saturación, totalmente
ajenos a la estética artesanal del café:

| Token actual | Valor actual | Problema | Valor correcto |
|---|---|---|---|
| `--color-success` | `#22c55e` | Verde fluorescente tech | `#5E6F64` |
| `--color-danger` | `#ef4444` | Rojo alarma tech | `#9B2335` |
| `--color-info` | `#3b82f6` | Azul digital genérico | `#5A9FD4` |
| `--color-warning` | `#f59e0b` | Ámbar OK (es brand) | `#C9A959` |

`global.css` (sitio público) **ya TIENe los colores correctos**: `--color-exito: #5E6F64`
y `--color-error: #9B2335`. El backoffice usa valores distintos — inconsistencia a corregir.

### 2.5 Sombras — Warm vs Cold

**Problema:** `design-tokens.css` usa sombras con `rgba(0,0,0,...)` (neutral/frío).
`global.css` usa `rgba(45, 34, 24, ...)` = Marrón Oscuro (cálido, coherente con la marca).

**Decisión:** Las sombras del sistema de tokens deben usar el tono cálido de la paleta:
`rgba(45, 34, 24, ...)` en lugar de `rgba(0,0,0,...)`.

### 2.6 `--shadow-glow` en backoffice-modern.css

**`--shadow-glow: 0 0 20px rgba(99, 102, 241, 0.3)`** — Índigo, completamente fuera
de marca. Corresponde a un preset de design system genérico.

**Decisión:** `0 0 20px rgba(201, 169, 89, 0.3)` (Ámbar Dorado, coherente con la marca).

---

## 3. Tipografía

### 3.1 Fuentes del sitio público (Correctas ✅)

| Rol | Fuente | Token | Estado |
|---|---|---|---|
| Titular H1/H2 | Shippori Mincho | `--fuente-titulo` | ✅ Correcto |
| Cuerpo/UI | Zen Maru Gothic | `--fuente-cuerpo` | ✅ Correcto |
| Citas/acento | Kaisei Decol | `--fuente-acento` | ✅ Correcto |

### 3.2 Fuentes backoffice — Bug crítico

**Problema:** `backoffice.php` carga `Zen Kaku Gothic New` (Google Fonts). Esta fuente:

- NO está especificada en el brand book
- Es similar pero diferente a `Zen Maru Gothic`
- El brand book designa Zen Maru Gothic como fuente de UI pública

Adicionalmente, `design-tokens.css` define:

- `--font-sans: 'Space Grotesk'` — fuente de SaaS genérico, listada explícitamente como
  "AI slop a evitar" en las guías de diseño visual
- `--font-display: 'Epilogue'` — misma categoría, no tiene relación con Komorebi

`backoffice-modern.css` línea 199 establece `font-family: 'Zen Kaku Gothic New'` en el body.

**Decisión tipografía backoffice:**

El brand book §4.3 establece:

- UI pública: Zen Maru Gothic
- Documentos operativos internos (sistemas de gestión, recibos): Inter

Para el backoffice se elige **Zen Maru Gothic como fuente primaria**. Razones:

1. Es la fuente oficial de UI del brand book
2. Diseñada específicamente para pantallas y densidad de información
3. Mantiene la identidad japonesa en el panel de operaciones
4. Ya existe en el proyecto y en el sitio público — coherencia visual

Inter queda como alternativa operativa válida pero no es la opción principal aquí.
Space Grotesk y Epilogue se eliminan del sistema de tokens.

| Token anterior | Valor anterior | Valor correcto |
|---|---|---|
| `--font-sans` | `'Space Grotesk', ...` | `'Zen Maru Gothic', 'Noto Sans JP', sans-serif` |
| `--font-display` | `'Epilogue', ...` | `'Shippori Mincho', serif` |
| `--font-mono` | `'Roboto Mono', ...` | `'Roboto Mono', 'Courier New', monospace` ✅ |

### 3.3 Googlefonts preload en backoffice

**Cambio en `resources/views/layouts/backoffice.php`:**

- Reemplazar `Zen+Kaku+Gothic+New:wght@400;500;600` por `Zen+Maru+Gothic:wght@400;500;700`

---

## 4. Sistema de iconos

### 4.1 Estado actual vs brand book

| Sistema | Brand book | Backoffice actual |
|---|---|---|
| Phosphor Icons | ✅ Especificado (outline 1.5px, 24px) | ❌ No cargado |
| Bootstrap Icons | — | ✅ Cargado |
| Font Awesome 6.5.1 | — | ✅ Cargado (redundante) |

### 4.2 Decisión pragmática

Migrar todo Bootstrap Icons → Phosphor requiere cambiar **cientos de clases** en todas las
vistas del backoffice. Costo: muy alto. Beneficio visual: marginal (ambos son outline).

**Decisión:** Conservar Bootstrap Icons en el backoffice para todas las features existentes.
Eliminar Font Awesome (redundante, carga extra ~100 KB que BI ya cubre).

Para el sitio público donde Font Awesome aparece en puntos específicos: sustituir por SVG
inline o clases de BI cuando se encuentren en la auditoría de Phase D.

---

## 5. Arquitectura CSS

### 5.1 Stacks separados (Mantener ✅)

El proyecto usa stacks CSS limpios y separados — mantener así:

```
Sitio público:   global.css + page-specific CSS
Backoffice:      design-tokens.css + backoffice-modern.css + backoffice-ux.css + sections/admin/admin-common.css
KDS / Recepción: susus propios layouts
```

### 5.2 Estado de `backoffice.css`

El archivo `public/css/backoffice.css` tiene en su cabecera:
> "Este archivo ya no se carga en ningún layout. Conservado como referencia histórica."

Se confirma que **NO está cargado** en ningún layout. Listo para eliminar físicamente.

### 5.3 Jerarquía de variables `--admin-*`

**Hallazgo importante:** `backoffice-ux.css` usa `var(--admin-primary)` y similares.
Estas variables **SÍ están definidas** en `sections/admin/admin-common.css`:

```css
--admin-primary: #5C3D2E;      /* Café Oscuro ✅ */
--admin-primary-hover: #4a2f23;
--admin-accent: #C9A959;       /* Ámbar ✅ */
--admin-text: #2D2218;         /* Marrón Oscuro ✅ */
```

No hay variables rotas en runtime. Los colores `--admin-*` son correctos.

---

## 6. Dark mode

**`backoffice.php` usa `data-bs-theme="light"`** (atributo de Bootstrap).
**`design-tokens.css` usa `[data-tema="oscuro"]`** (atributo del proyecto).

Estos son mecanismos distintos. El toggle de dark mode del proyecto cambia `data-tema`,
pero Bootstrap responde a `data-bs-theme`. Resultado: Bootstrap no activa su dark theme
aunque el proyecto lo hagaa.

**Decisión:** Mantener `data-bs-theme` para Bootstrap pero añadir también `data-tema="light"`
al elemento `<html>`. El JS de toggle del proyecto debe actualizar ambos atributos.

---

## 7. Resumen de cambios aprobados

### Alta prioridad — Bugs visuales reales

| # | Archivo | Cambio |
|---|---|---|
| B1 | `design-tokens.css` | Reemplazar `--color-primary-*` escala bambú → café |
| B2 | `design-tokens.css` | `--font-sans` → Zen Maru Gothic |
| B3 | `design-tokens.css` | `--font-display` → Shippori Mincho |
| B4 | `design-tokens.css` | `--color-success` → `#5E6F64` |
| B5 | `design-tokens.css` | `--color-danger` → `#9B2335` |
| B6 | `design-tokens.css` | `--color-info` → `#5A9FD4` |
| B7 | `design-tokens.css` | Sombras: `rgba(0,0,0,...)` → `rgba(45,34,24,...)` |
| B8 | `backoffice-modern.css` | `--shadow-glow` → amber `rgba(201,169,89,0.3)` |
| B9 | `backoffice-modern.css` | `font-family` línea 199: Zen Kaku → Zen Maru Gothic |
| B10 | `backoffice.php` | Google Fonts: Zen Kaku Gothic New → Zen Maru Gothic |

### Media prioridad — Limpieza y coherencia

| # | Archivo | Cambio |
|---|---|---|
| C1 | `public/css/backoffice.css` | Eliminar físicamente (ya deprecado, no cargado) |
| C2 | `backoffice.php` | Eliminar Font Awesome CDN (Bootstrap Icons lo cubre) |
| C3 | `backoffice.php` | Añadir `data-tema="light"` al `<html>` |

### Baja prioridad — Sitio público (Phase D)

| # | Área | Cambio |
|---|---|---|
| D1 | Login | Verificar si el botón muestra azul — corregir a café/#admin-primary |
| D2 | Loyalty card | Verificar inconsistencia de color reportada |
| D3 | 404 page | Verificar coherencia de paleta |

---

## 8. Lo que NO cambia

- La paleta base del brand book es correcta y no se toca
- La arquitectura de layouts separados es correcta — mantener
- Los tokens `--admin-*` de `admin-common.css` son correctos — no tocar
- Los tokens de `global.css` son correctos — no tocar
- La escala `--coffee-*` / `--forest-*` / `--warmth-*` en backoffice-modern.css es buena
- Bootstrap 5 en backoffice: integración profunda, no se migra ahora
- Bootstrap Icons: se mantienen en backoffice
- Phosphor Icons: aspiracional, no se implementa en este ciclo (costo/beneficio)

---

*Documento generado por auditoría automatizada — Fase A del plan 2026-04-14-brand-visual-unification.md*
