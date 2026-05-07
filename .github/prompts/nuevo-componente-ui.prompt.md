---
mode: agent
description: "Crear un componente de vista PHP con Alpine.js aplicando ui-ux-pro-max. Incluye auditoría Lighthouse automática con chrome-devtools después de implementar."
---

# Nuevo Componente UI — Con Auditoría

Voy a crear un componente de vista aplicando la skill `ui-ux-pro-max` y auditando con Lighthouse.

## Parámetros

**Nombre del componente:** ${input:component_name:Ej: reservation-card, menu-item, staff-shift-row}
**Vista padre / layout:** ${input:layout:Ej: main, backoffice, kds, mobile, reception}
**Descripción del componente:** ${input:description:¿Qué muestra o hace este componente?}
**URL para auditar (después de implementar):** ${input:url:URL donde estará el componente}

## Flujo obligatorio

### Paso 1 — Invocar `ui-ux-pro-max` (SIEMPRE primero)

Esta skill es **obligatoria** para TODO trabajo visual en el proyecto.
Proporciona 99 guías UX, 50+ estilos, paletas, tipografías, checklists WCAG 2.1.

Preguntas que debe responder el diseño:
- ¿Qué estado muestra? (vacío, cargando, error, éxito, normal)
- ¿Hay interactividad Alpine.js? (x-data, x-show, x-bind, @click, etc.)
- ¿Es responsivo? ¿mobile-first?
- ¿Cumple WCAG 2.1 AA? (contraste, aria-label, focus visible, roles semánticos)
- ¿Tiene dark mode? (respetar sistema de tokens CSS del proyecto)

### Paso 2 — Consultar Context7 si se usa Alpine.js

```
resolve-library-id("Alpine.js")
get-library-docs(id, topic="directives")
```

### Paso 3 — Implementar el componente

Ubicación: `resources/views/{rol}/{nombre-componente}.php`

Reglas de vistas en este proyecto:
- **`View::render()`** solo recibe escalares, arrays o `Raw`
- Para datos Alpine.js: `Raw::json($array)` en el controller, `x-data="<?= $jsonData ?>"` en la vista
- Para HTML pre-sanitizado: `Raw::html($safe)`
- Escape manual con `e($variable)` para casos edge
- CSS del proyecto: variables CSS custom + Tailwind/CSS del layout actual

```php
<!-- Estructura básica de componente -->
<div
    x-data="componentName()"
    class="..."
    role="..."
    aria-label="..."
>
    <!-- contenido -->
</div>

<script>
function componentName() {
    return {
        // estado Alpine.js
    };
}
</script>
```

### Paso 4 — Auditoría automática con Chrome DevTools

Después de implementar, navegar a `${input:url:}` y ejecutar:

```
chr_lighthouse_audit(mode="snapshot")
```

Objetivos mínimos del proyecto:
- Accesibilidad: ≥ 90
- Best Practices: ≥ 90
- SEO: ≥ 80

Si el score es inferior: analizar los insights de Lighthouse y corregir antes de considerar completado.

### Paso 5 — Verificación final

```
pla_browser_snapshot   → árbol de accesibilidad (aria roles, landmarks)
pla_browser_take_screenshot → captura visual final
```

Invocar `verification-before-completion` antes de declarar completado.

## Checklist de accesibilidad (WCAG 2.1 AA)

- [ ] Contraste de color ≥ 4.5:1 (texto normal) / ≥ 3:1 (texto grande)
- [ ] Focus visible en todos los elementos interactivos
- [ ] Imágenes con `alt` descriptivo (o `alt=""` si decorativas)
- [ ] Botones y links con texto accesible (no solo iconos)
- [ ] Roles ARIA correctos (`role="button"`, `aria-label`, `aria-expanded`)
- [ ] Funciona sin JavaScript (degradación elegante)
- [ ] Auditoría Lighthouse ≥ 90 en accesibilidad

---
**Referencia:** skill `ui-ux-pro-max` | `.github/instructions/ai-workflow.instructions.md` | `docs/UX_UI_AUDIT_REPORT.md`

