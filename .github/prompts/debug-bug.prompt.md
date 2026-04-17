---
mode: agent
description: "Debugging sistemático con evidencia real antes de proponer cualquier fix. Usa chrome-devtools MCP para errores JS/red y playwright para capturar estado visual."
---

# Debug Bug — Sistemático con Evidencia

Voy a depurar un problema siguiendo el flujo sistemático del proyecto. **Cero fixes sin evidencia.**

## Información del bug

**Descripción del problema:** ${input:bug_description:Describe el bug, test fallido o comportamiento inesperado}
**URL afectada (si es visual):** ${input:url:URL donde ocurre el bug, o vacío si es backend}

## Flujo de diagnóstico

### Fase 1 — Invocar `systematic-debugging`

Leer y seguir la skill antes de hacer cualquier otra cosa.

### Fase 2 — Recopilar evidencia

**Si es un bug visual o de red:**
```
chr_list_console_messages   → Errores JS en consola
chr_list_network_requests   → Peticiones fallidas (4xx, 5xx, CORS)
pla_browser_snapshot        → Estado del DOM / árbol de accesibilidad
pla_browser_take_screenshot → Captura visual del estado del bug
```

**Si es un bug de backend:**
```bash
make logs-app   # Tail de logs del contenedor
make test-unit  # ¿Hay tests que fallen?
make phpstan    # ¿PHPStan detecta el problema?
```

**Si es un problema de rendimiento:**
```
chr_performance_start_trace  → Iniciar traza
chr_performance_stop_trace   → Detener y analizar
chr_lighthouse_audit         → Auditoría Lighthouse
```

### Fase 3 — Hipótesis y reproducción

Antes de tocar código:
1. Formular hipótesis basada en evidencia
2. Invocar `test-driven-development` para escribir un test que reproduzca el fallo
3. Confirmar que el test falla (rojo) antes de implementar el fix

### Fase 4 — Fix

Aplicar el fix mínimo que resuelva el problema reproducido por el test.
Seguir patrones del proyecto (`Result`, `declare(strict_types=1)`, etc.).

### Fase 5 — Verificación

Invocar `verification-before-completion`:
```bash
make test-unit
make phpstan
```
El test debe pasar (verde). No hay fix confirmado sin evidencia de que el test pasa.

---
**Referencia:** `.github/instructions/ai-workflow.instructions.md` | skill `systematic-debugging`

