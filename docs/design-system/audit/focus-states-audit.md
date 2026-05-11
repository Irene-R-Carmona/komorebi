# Auditoría de estados de foco — Komorebi Café

## Resumen

Auditoría de accesibilidad de estados de foco visibles en todos los componentes interactivos
del sistema de diseño de Komorebi Café, siguiendo WCAG 2.1 AA (criterio 2.4.7).

## Alcance

- Formularios: login, registro, contacto, reservas, reseñas
- Navegación: menú principal, menú móvil, breadcrumbs
- Botones: primarios, secundarios, de peligro, de icono
- Controles: checkboxes, radios, selects, toggles
- Modales y diálogos
- Tarjetas y elementos clicables

## Criterios WCAG aplicados

| Criterio | Nivel | Descripción |
|----------|-------|-------------|
| 2.4.7    | AA    | El foco del teclado es siempre visible |
| 2.4.11   | AA    | El foco del teclado no está oculto (WCAG 2.2) |
| 2.4.12   | AAA   | El foco del teclado tiene contraste mínimo 3:1 |

## Componentes auditados

### Botones

- **Estado normal**: visible sin indicador de foco
- **Estado hover**: color de fondo cambia (`hover:bg-amber-700`)
- **Estado focus**: anillo de foco `focus:ring-2 focus:ring-amber-500 focus:ring-offset-2`
- **Estado disabled**: opacity 50%, no focusable

### Inputs de formulario

- **Estado normal**: borde `border-stone-300`
- **Estado focus**: `focus:ring-2 focus:ring-amber-500 focus:border-amber-500`
- **Estado error**: borde rojo, aria-invalid="true"

### Navegación

- **Links del menú**: `focus:outline-amber-500` visible
- **Skip link**: posicionado fuera de pantalla, visible al recibir foco

## Hallazgos

| ID | Componente | Descripción | Severidad | Estado |
|----|-----------|-------------|-----------|--------|
| F-01 | Modal | Foco se mantiene dentro del modal con focus trap | Info | ✅ OK |
| F-02 | Cards | Cards clicables tienen role="button" y tabindex="0" | Info | ✅ OK |
| F-03 | Select | Select nativo mantiene foco del navegador | Info | ✅ OK |

## Resultado

**Sin hallazgos críticos.** Todos los componentes interactivos muestran foco visible
conforme a WCAG 2.1 AA.

---

*Fecha de auditoría: 2026-05*
*Herramientas: inspección manual + axe-core*
