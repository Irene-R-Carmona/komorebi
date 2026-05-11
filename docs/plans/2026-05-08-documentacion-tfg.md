# Plan: Documentación Oficial TFG — Komorebi Café (LaTeX → PDF)

**Estado:** � En implementación
**Fecha:** 2026-05-08 · **Actualizado:** 2026-05-11
**Estimación:** ~160 páginas PDF

---

## Datos del proyecto

| Campo | Valor |
|---|---|
| Título | Komorebi Café |
| Alumna | Irene Reyes Carmona |
| Centro | I.E.S. Ágora |
| Tutora | María Lourdes Calleja Flores |
| Año académico | 2025-2026 |
| Engine LaTeX | XeLaTeX + MiKTeX 25.12 (fontspec) |
| Compilar | `xelatex -shell-escape documentacion-oficial.tex` (3 pases) |
| App URL | <http://localhost:8080> (Docker corriendo) |
| Credenciales admin | <admin@komorebi.cafe> / komorebi2024 |

---

## Decisiones técnicas finales

| Decisión | Elegido | Descartado | Motivo |
|---|---|---|---|
| Diagramas de secuencia | `pgf-umlsd` (nativo LaTeX) | `ltmermaid` | ltmermaid requiere Node en compilación; inestable |
| Glosario | `makeglossaries-lite` | `makeglossaries` | La versión original requiere Perl en Windows |
| Páginas landscape | `pdflscape` | `lscape` | pdflscape rota correctamente en visores PDF |
| Bibliografía | `biber` + `biblatex` | `natbib` | Mejor soporte Unicode y biblatex moderno |
| Estructura | Un solo `.tex` | `\input` por capítulos | Un único entregable |
| Portada | `\includepdf{portada-oficial.pdf}` | titlepage LaTeX custom | IES Ágora exige su plantilla oficial |
| Fuente principal | Arial | Times New Roman | IES Ágora especifica Arial |
| Número de página | `\fancyfoot[C]{\thepage}` | `\fancyhead[LE,RO]{\thepage}` | IES Ágora: pie de página centrado |
| Agradecimientos | ❌ Eliminado | Sección presente | Decisión de la alumna |

**Stack de paquetes LaTeX:**
fontspec · geometry(a4,2.5cm) · babel(spanish,es-tabla) · csquotes ·
**pdfpages** · minted 3.x (-shell-escape + Pygments) · tcolorbox (minted,skins,breakable) ·
longtable · booktabs · tabularx · multirow · pdflscape ·
tikz (shapes,arrows.meta,positioning,calc,fit,backgrounds,shadows) ·
pgf-umlsd · forest · svg (Inkscape) ·
hyperref(hidelinks) · cleveref · glossaries-extra · makeglossaries-lite ·
fancyhdr · titlesec · setspace · microtype · caption · subcaption ·
float · graphicx · xcolor · enumitem · appendix(titletoc) · biblatex(biber,ieee)

---

## Estructura definitiva del documento (IES Ágora)

```
[PORTADA OFICIAL]  portada-oficial.pdf via \includepdf
Resumen (ES) + Abstract (EN)        ← SIN Agradecimientos
Índice de contenidos (TOC)
Índice de figuras (LOF)
Índice de tablas (LOT)

Cap 1  — Introducción General                  ✅ completo
Cap 2  — Desarrollo Técnico                    ✅ completo
Cap 3  — Alternativas Consideradas             ❌ por escribir (nuevo)
Cap 4  — Manual de Usuario                     ⚠️  37 \fbox por completar con capturas
Cap 5  — Resultados                            ❌ por escribir (nuevo)
Cap 6  — Conclusiones y Futuras Mejoras        ✅ completo
Cap 7  — Referencias Bibliográficas            ✅ completo

Apéndice A — API REST (~95 endpoints)          ✅ completo
Apéndice B — Esquema BD (19 migraciones)       ✅ completo
Apéndice C — Variables de entorno              ✅ completo
Apéndice D — Glosario de términos              ✅ completo
Apéndice E — Manual Técnico (instalación)      ❌ por escribir (nuevo)
```

---

## Reglas de tono y registro por tipo de sección

> Estas reglas aplican a **todo contenido nuevo** y a las revisiones de contenido existente.

| Tipo de sección | Persona | Tiempo verbal | Reglas clave |
|---|---|---|---|
| Secciones técnicas (Cap 2, Apéndices) | Impersonal | Presente | Tabla > prosa; código > descripción; sin adjetivos valorativos |
| Alternativas (Cap 3) | Primera persona | Pretérito | Limitación concreta de este proyecto; no "se consideró que" genérico |
| Resultados (Cap 5) | Impersonal | Presente | Solo métricas verificables; sin interpretación subjetiva |
| Manual de Usuario (Cap 4) | Imperativo directo | Presente | Máx 2-3 frases por sección; descripción de la captura |
| Manual Técnico (Apéndice E) | Imperativo directo | Presente | Paso → comando → resultado esperado |
| Conclusiones (Cap 6) | Primera persona / impersonal mixto | Pretérito + presente | Sin "se ha demostrado que"; afirmaciones concretas |

**Patrones prohibidos** (AI slop a evitar):

- "Desde el punto de vista técnico..." → Empezar directamente con el hecho técnico
- "Al inicio del proyecto se plantearon..." → Nombrar la tecnología/decisión directamente
- "se ha demostrado la comprensión profunda de..." → Sustituir por qué funciona concretamente
- Enumeraciones disfrazadas de prosa (lista de tecnologías en párrafo seguido)
- Adjetivos vacíos: "robusto", "escalable", "evocador", "elegante" sin datos que los soporten
- Simetría artificial en listas: no añadir ítem C solo para equilibrar A y B

---

## Fase 0 — Correcciones de cumplimiento IES Ágora en documentacion-oficial.tex

> El archivo existe (2053 líneas, ~70% completo). Estas son las 8 correcciones estructurales
> obligatorias antes de cualquier compilación.

- [ ] **0.1** Añadir `\usepackage{pdfpages}` al preámbulo (tras `\usepackage{graphicx}`)

- [ ] **0.2** Reemplazar el bloque `\begin{titlepage}...\end{titlepage}` (portada LaTeX custom)
  por:

  ```latex
  \includepdf[pages=1]{portada-oficial.pdf}
  ```

  Asegurarse de que `docs/portada-oficial.pdf` existe en esa ruta antes de compilar.

- [ ] **0.3** Cambiar fuente principal:

  ```latex
  % Antes:
  \setmainfont{Times New Roman}
  % Después:
  \setmainfont{Arial}
  ```

- [ ] **0.4** Actualizar configuración `fancyhdr` para número de página en pie:

  ```latex
  % Cabecera: autor izquierda, título derecha
  \fancyhead[L]{\small Irene Reyes Carmona}
  \fancyhead[R]{\small Komorebi Café}
  \fancyhead[C]{}
  % Pie: número de página centrado
  \fancyfoot[C]{\thepage}
  \fancyfoot[L]{}
  \fancyfoot[R]{}
  ```

- [ ] **0.5** Añadir espaciado entre párrafos (tras `\setlength{\parindent}`):

  ```latex
  \setlength{\parskip}{0.5\baselineskip}
  ```

- [ ] **0.6** Eliminar el capítulo de Agradecimientos completo
  (bloque `\chapter*{Agradecimientos}...\clearpage`).

- [ ] **0.7** Añadir esqueletos de capítulos nuevos en su posición correcta:
  - Después de Cap 2 y antes de `\chapter{Manual de Usuario}`:

    ```latex
    \chapter{Alternativas Consideradas}
    \label{ch:alternativas}
    % TODO: contenido Fase 3
    ```

  - Después de Cap 4 (Manual) y antes de Conclusiones:

    ```latex
    \chapter{Resultados}
    \label{ch:resultados}
    % TODO: contenido Fase 3
    ```

  - Al final de apéndices (tras Apéndice D Glosario):

    ```latex
    \chapter{Manual Técnico}
    \label{ch:manual-tecnico}
    % TODO: contenido Fase 3
    ```

- [ ] **0.8** Verificar y corregir etiquetas `\label{ch:*}` de los capítulos renumerados
  (Conclusiones pasa de Cap 4→6; Referencias de Cap 5→7).

---

## Fase 1 — Compilación base (verificar que el .tex compila tras Fase 0)

```powershell
cd docs
xelatex -shell-escape documentacion-oficial.tex   # pase 1 — detectar errores
biber documentacion-oficial
makeglossaries-lite documentacion-oficial
xelatex -shell-escape documentacion-oficial.tex   # pase 2
xelatex -shell-escape documentacion-oficial.tex   # pase 3 — TOC + refs estables
```

- [ ] **1.1** Compilar pase 1 y resolver todos los errores fatales
- [ ] **1.2** Compilar pases 2 y 3 — confirmar que el PDF se genera
- [ ] **1.3** Verificar: PDF abre, portada muestra imagen, TOC generado, número de página en pie
- [ ] **1.4** Confirmar que `docs/portada-oficial.pdf` está guardado en esa ruta exacta

---

## Fase 2 — Capturas de pantalla (62 PNG via Playwright MCP)

**URL base:** `http://localhost:8080`
**Login admin:** `admin@komorebi.cafe` / `komorebi2024`
**Destino:** `docs/screenshots/`
**Formato:** PNG, viewport 1280×800

- [ ] **2.1** Páginas públicas (12):
  `01-home` · `02-cafes-lista` · `03-cafe-detalle` · `04-menu-catalogo` · `05-menu-filtros` ·
  `06-quiz-inicio` · `07-quiz-resultado` · `08-historia` · `09-faq` · `10-contacto` ·
  `11-cookies-banner` · `12-cookies-preferencias`

- [ ] **2.2** Autenticación (6):
  `13-login-form` · `14-registro-form` · `15-forgot-password` · `16-forgot-password-ok` ·
  `17-reset-password-form` · `18-verify-email`

- [ ] **2.3** Usuario autenticado (11):
  `19-perfil` · `20-perfil-seguridad` · `21-perfil-sesiones` · `22-mis-reservas` ·
  `23-reservar-step1` · `24-reservar-step2` · `25-reservar-confirmacion` ·
  `26-loyalty-card` · `27-favoritos` · `28-carrito` · `29-waitlist-status`

- [ ] **2.4** Admin backoffice (19):
  `30-admin-dashboard` · `31-admin-usuarios` · `32-admin-cafes` · `33-admin-menu` ·
  `34-admin-reservas` · `35-admin-animales` · `36-admin-reviews` · `37-admin-loyalty` ·
  `38-admin-newsletter` · `39-admin-reportes` · `40-admin-settings` ·
  `41-admin-logs-auditoria` · `42-admin-logs-auth` · `43-admin-waitlists` ·
  `44-admin-roles` · `45-admin-menu-crear` · `46-admin-usuario-crear` ·
  `47-admin-animal-crear` · `48-admin-reserva-detalle`

- [ ] **2.5** Manager (5):
  `49-manager-dashboard` · `50-manager-productos` · `51-manager-staff` ·
  `52-manager-reservas` · `53-manager-reportes`

- [ ] **2.6** Recepción y Cocina (4):
  `54-reception-dashboard` · `55-reception-reservas` · `56-kitchen-orders` · `57-kitchen-history`

- [ ] **2.7** Keeper (5):
  `58-keeper-dashboard` · `59-keeper-animals` · `60-keeper-health-checks` ·
  `61-keeper-health-check-crear` · `62-keeper-incidents`

---

## Fase 3 — Escritura de contenido pendiente

### 3.A — Completar Cap 4 (Manual de Usuario) — 37 secciones con `\fbox`

Para cada sección con `\fbox{CAPTURA PENDIENTE}`:

- Reemplazar por `\figura{screenshots/NN-nombre.png}{Descripción breve}{fig:NN-nombre}`
- Añadir 2-3 frases descriptivas en imperativo/presente sobre lo que muestra la captura
- No añadir interpretación ni valoración — describir únicamente lo que se ve

- [ ] **3.A.1** Secciones 3.1 (área pública) — 12 capturas
- [ ] **3.A.2** Secciones 3.2 (autenticación) — 6 capturas
- [ ] **3.A.3** Secciones 3.3 (usuario registrado) — 11 capturas
- [ ] **3.A.4** Secciones 3.4 (admin backoffice) — 19 capturas
- [ ] **3.A.5** Secciones 3.5–3.7 (manager, recepción/cocina, keeper) — 14 capturas

### 3.B — Escribir Cap 3 (Alternativas Consideradas)

Estructura:

```
3.1  Framework web: MVC custom vs. Laravel vs. Slim
3.2  ORM vs. repositorios con PDO directo
3.3  Autenticación: sesiones propias vs. JWT vs. OAuth2
3.4  Frontend: Alpine.js vs. React vs. Livewire
3.5  Base de datos: MySQL vs. PostgreSQL
3.6  Despliegue: Railway vs. VPS propio vs. Shared hosting
3.7  Servidor PHP: FrankenPHP vs. nginx+php-fpm vs. Apache mod_php
3.8  Cola de tareas: Redis custom vs. Laravel Horizon vs. Beanstalkd
```

Reglas de escritura para este capítulo:

- Primera persona: "Elegí X porque en este proyecto..."
- Limitación concreta, no genérica: "Laravel añadía ~15 MB de dependencias que no utilizaría"
- Una tabla comparativa por alternativa (criterios ponderados)
- Pretérito para la decisión; presente para las propiedades de las alternativas

- [ ] **3.B.1** Redactar las 8 subsecciones con tabla comparativa + párrafo de justificación

### 3.C — Escribir Cap 5 (Resultados)

Estructura:

```
5.1  Cobertura de requisitos funcionales (tabla checklist)
5.2  Métricas de pruebas (X unit tests, Y integration tests, cobertura Z%)
5.3  Rendimiento (tiempos de respuesta p50/p95 en local)
5.4  Accesibilidad (score Lighthouse)
5.5  Líneas de código y complejidad (cloc, PHPStan level 5 sin errores)
```

Reglas de escritura para este capítulo:

- Solo métricas obtenidas ejecutando comandos reales (`make test-coverage`, `make phpstan`, Lighthouse)
- Impersonal, presente: "La cobertura de tests unitarios es del X%"
- Tablas con datos; sin párrafos narrativos de "se ha conseguido"
- Si un dato no se puede obtener, la celda dice "— (no medido)"

- [ ] **3.C.1** Ejecutar `make test-coverage` dentro del contenedor y anotar métricas
- [ ] **3.C.2** Ejecutar `make phpstan` y anotar nivel + errores (debe ser 0)
- [ ] **3.C.3** Ejecutar auditoría Lighthouse via chrome-devtools MCP en las páginas clave
- [ ] **3.C.4** Redactar las 5 subsecciones con las métricas reales

### 3.D — Escribir Apéndice E (Manual Técnico)

Estructura:

```
E.1  Requisitos del sistema (Docker Desktop, Node 20, MiKTeX —si aplica—)
E.2  Clonar el repositorio
E.3  Configurar variables de entorno (.env desde .env.example)
E.4  Levantar el stack con Docker Compose
E.5  Aplicar migraciones y seeders
E.6  Acceder a la aplicación
E.7  Ejecutar tests
E.8  Despliegue en Railway (variables Railway, railway up)
```

Reglas de escritura para este apéndice:

- Imperativo directo: "Ejecuta", "Abre", "Copia"
- Cada paso: acción → comando en `\mintinline{bash}{}` o bloque `minted` → resultado esperado
- Sin párrafos de contexto antes del paso — directo al grano

- [ ] **3.D.1** Redactar los 8 pasos con comandos reales verificados

---

## Fase 4 — Compilación final y verificación

```powershell
cd docs
xelatex -shell-escape documentacion-oficial.tex
biber documentacion-oficial
makeglossaries-lite documentacion-oficial
xelatex -shell-escape documentacion-oficial.tex
xelatex -shell-escape documentacion-oficial.tex
```

- [ ] **4.1** Compilar sin errores fatales (warnings son aceptables)
- [ ] **4.2** Verificar número de páginas del PDF (objetivo ≥ 150)
- [ ] **4.3** Revisar TOC: todos los capítulos y apéndices listados con páginas correctas
- [ ] **4.4** Revisar LOF: todas las 62 capturas referenciadas
- [ ] **4.5** Revisar LOT: todas las tablas numeradas
- [ ] **4.6** Verificar portada: PDF oficial visible (no página en blanco)
- [ ] **4.7** Verificar pie de página: número centrado en todas las páginas
- [ ] **4.8** Verificar fuente: cuerpo en Arial 12pt
- [ ] **4.9** Verificar que NO aparece el capítulo de Agradecimientos
- [ ] **4.10** Verificar que los 3 capítulos nuevos aparecen (Alternativas, Resultados, Apéndice E)
- [ ] **4.11** Spot-check tildes y ñ en al menos 5 páginas aleatorias

---

## Archivos involucrados

```
docs/
  documentacion-oficial.tex      ← entregable principal (EXISTE — 2053 líneas)
  portada-oficial.pdf            ← portada IES Ágora (copiar aquí antes de Fase 1.4)
  referencias.bib                ← bibliografía (EXISTE)
  screenshots/                   ← CREAR en Fase 2
    01-home.png … 62-keeper-incidents.png
  images/
    logos/
      komorebi-logo-stacked.svg  ← YA EXISTE
      komorebi-logo-icon.svg     ← YA EXISTE
```

---

## Checklist IES Ágora (cumplimiento obligatorio)

- [ ] Portada: plantilla oficial PDF adjunta via `\includepdf`
- [ ] Fuente: Arial en todo el cuerpo del texto
- [ ] Número de página: pie de página centrado
- [ ] `\parskip` configurado (espaciado entre párrafos visible)
- [ ] Capítulo "Alternativas" presente y con contenido
- [ ] Capítulo "Resultados" presente con métricas reales
- [ ] "Manual Técnico" presente como Apéndice E con pasos de instalación
- [ ] Agradecimientos: NO aparece en el documento

## Checklist de calidad general

- [ ] PDF compila sin errores fatales en 3 pases
- [ ] PDF ≥ 150 páginas
- [ ] TOC, LOF, LOT generados y correctos
- [ ] Todas las 62 capturas visibles y referenciadas con `\cref`
- [ ] Tablas `longtable` en `pdflscape` sin desbordamiento
- [ ] Tildes, ñ y comillas «» sin caracteres extraños
- [ ] PHPStan level 5 — 0 errores (referencia para Cap 5 Resultados)
