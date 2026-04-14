# Diseño: Sprint QoL Holístico — Komorebi Café

**Fecha:** 14 de abril de 2026
**Estado:** Aprobado — en implementación
**Plan derivado:** `docs/plans/2026-04-14-qol-holistic-sprint.md`

---

## Objetivo

Mejorar la calidad de vida de todos los perfiles del proyecto —cliente final, personal operativo, gestor/admin y desarrollador— en un sprint estructurado en cuatro olas independientes y verificables.

## Contexto del análisis

Exploración realizada el 14/04/2026 sobre el estado del proyecto en v1.0.0. Se identificaron:

- 5 bugs críticos visibles que afectan funcionalidad en producción
- Deuda técnica DX que genera ruido en PHPStan y frena refactors seguros
- Gaps de QoL para staff operativo (KDS, recepción, keeper)
- Fragmentación visual entre site público y backoffice

---

## Ola 0 — Bugs Críticos

### 0.1 Quiz sin recomendación

**Síntoma:** POST `/quiz/resultado` calcula el café correcto pero el bloque `<?php if ($cafeData): ?>` no muestra nombre, imagen ni descripción.

**Causa raíz:** `resultado.php` accede a `$cafeData['imagen']`, `$cafeData['nombre']` y `$cafeData['descripcion']`, pero la tabla `cafes` tiene columnas `image_url`, `name` y `description`.

**Fichero afectado:** `resources/views/public/quiz/resultado.php`

**Fix:** Renombrar las tres claves para que coincidan con el esquema de BD. También `$cafeData['ubicacion']` → `$cafeData['location']` y `$cafeData['rating']` → `$cafeData['rating_avg']`.

---

### 0.2 Modal de reseñas admin muestra `[object HTMLTextAreaElement]`

**Síntoma:** Al rechazar una reseña desde el admin, el nombre del autor en el modal se muestra como `[object HTMLTextAreaElement]`.

**Causa raíz:** En `components/admin/review-card.php` el atributo Alpine es:

```php
@click="openRejectModal({id: <?= (int) $id ?>, author: '<?= e($author) ?>'})"
```

`e()` aplica HTML-encoding, convirtiendo `O'Brien` → `O&#039;Brien`. Alpine evalúa el atributo como expresión JS, decodifica las HTML entities, y el resultado `'O'Brien'` rompe el literal de cadena JS. El error de parseo hace que Alpine asigne el elemento DOM como fallback.

**Fichero afectado:** `resources/views/components/admin/review-card.php`

**Fix:** Usar `json_encode()` con flags de seguridad HTML para producir un literal JS correcto:

```php
@click="openRejectModal({id: <?= (int) $id ?>, author: <?= e(json_encode($author, JSON_HEX_APOS | JSON_HEX_TAG | JSON_HEX_AMP)) ?>})"
```

---

### 0.3 Keeper dashboard — KPIs incorrectos

**Síntoma:** "Animales Activos: 0" en el dashboard del keeper.

**Causa raíz (datos):** La tabla `animals` requiere que `make db-seed` se ejecute con stack levantado (`make dev`). El `AnimalSeeder` está registrado en `DatabaseSeeder` pero podría no haberse ejecutado en el entorno local.

**Causa raíz (código):** El KPI "Animales Activos" usa `$stats['total_animals']` (ALL non-deleted) en lugar de `$stats['healthy']` (status=`active`). El KPI "Promedio Interacciones" usa `$stats['avg_interactions']` que `getHealthStatistics()` no devuelve → siempre muestra 0.

**Fichero afectado:** `resources/views/backoffice/keeper/dashboard.php`

**Fix:**

- "Animales Activos" → usar `$stats['healthy']`
- "Promedio Interacciones" → reemplazar por "En Descanso" con `$stats['monitoring']` (valor que sí existe en el resultado de la query)

---

### 0.4 Loyalty card: iconos Bootstrap Icons no visibles (verificación)

**Síntoma reportado:** La tarjeta de fidelización muestra espacios vacíos donde deberían aparecer iconos de tier.

**Análisis:** La vista `card.php` usa correctamente `<i class="bi <?= $tierIcon ?>">` con valores de `$tierIcons` array. Bootstrap Icons CDN está cargado en `layouts/main.php`. La variable `$tierEmojis` (definida con clases BI) no se renderiza en ninguna parte → dead code inocuo.

**Plan:** Verificar visualmente el stack levantado. Si los iconos aparecen correctamente, solo eliminar el array `$tierEmojis` muerto. Si no aparecen, verificar versión CDN y nombres de clase.

---

### 0.5 Imágenes rotas en catálogo y keeper

**Síntoma:** 50%+ de imágenes de cafés y animales no cargan.

**Causa raíz:** Las columnas `image_url` (cafés) y `image_url` (animales) almacenan rutas relativas. Los ficheros físicos no forman parte del repositorio. Los seeders usan rutas como `/images/cafes/neko-no-niwa.jpg` que no existen en `public/images/`.

**Fix:** Añadir `onerror="this.src='/images/ui/placeholder.svg'; this.onerror=null;"` en todos los `<img>` de cafés y animales en vistas públicas y keeper. Verificar que `public/images/ui/placeholder.svg` existe; crearlo si no.

---

## Ola 1 — Salud Técnica (DX)

### 1.1 Métodos legacy muertos en `AnimalCareController`

Ocho métodos marcados `TODO(plan7-cleanup)` sin rutas activas en `routes.php`:
`logCare`, `updateHealth`, `toggleActive`, `uploadPhoto`, `createIncident`, `resolveIncident`, `update`, `toggle`.

**Fix:** Eliminar los ocho métodos del controlador.

---

### 1.2 Métodos `@deprecated` en ContextService y NavigationService

Métodos estáticos legacy que conviven con la nueva API de instancia. Genera mezcla de patrones en producción.

**Fix:** Localizar todos los call sites via grep, migrar a la API de instancia, eliminar métodos deprecated. *(Per preferencia global del usuario: cero deprecated.)*

---

### 1.3 Ruido PHPStan: mezcla `createStub/createMock`

240+ errores suprimidos en `phpstan-baseline.neon` causados por tests que usan `createStub()` pero después llaman `.expects()` (API de mock). PHPStan no puede inferir los tipos combinados.

**Fix:** Estandarizar tests problemáticos: si el test usa `.expects()`, usar `createMock()`; si no usa expectations, usar `createStub()`. Regenerar baseline tras la corrección.

---

## Ola 2 — QoL Operativo Staff

### 2.1 KDS thresholds hardcodeados

Los tiempos de alerta del Sistema de Pantalla de Cocina (300s, 900s, 3600s) están como magic numbers en `KitchenController`. Cada café opera con ritmos distintos.

**Fix:** Extraer a constantes nombradas en el controlador. Opcional: leer de `system_settings` para override por café.

---

## Ola 3 — Coherencia Visual

### 3.1 Página 404 sin navegación

Estado de error completamente desconectado del site. El usuario no tiene forma de volver.

**Fix:** Añadir enlace "Inicio" y logo con link en la vista 404. Compartir template con el layout principal sin heredar el nav completo.

---

## Criterios de Aceptación por Ola

| Ola | Verificación |
|-----|-------------|
| 0 | `make dev` + recorrido manual quiz → resultado muestra café real; modal admin muestra nombre del autor; keeper dashboard muestra count correcto; imágenes tienen fallback visible |
| 1 | `make phpstan` baseline reducido; grep confirma cero referencias a métodos deprecated; `make test-unit` verde |
| 2 | KDS renderiza con nombres de constante en código; make test-unit verde |
| 3 | 404 tiene enlace; `make e2e-a11y` sin regresiones WCAG 2.1 AA |

---

## Nota sobre Ola 3 y planes existentes

La Ola 3 (coherencia visual) fue intencionalmente reducida a la tarea más urgente (404). Los planes `2026-04-13-uiux-vistas-publicas.md` y `2026-04-14-brand-visual-unification.md` ya cubren la unificación de tokens de diseño y el backoffice brand. No se duplica ese trabajo.
