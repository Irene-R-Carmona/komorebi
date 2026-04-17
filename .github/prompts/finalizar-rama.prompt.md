---
mode: agent
description: "Flujo completo para finalizar una rama de desarrollo: verificación, quality gate, PR con Definition of Done. Usa github MCP para crear el PR."
---

# Finalizar Rama — Verificación + PR

Voy a verificar que el trabajo está completo y crear el PR siguiendo la Definition of Done.

## Información

**Rama a finalizar:** ${input:branch_name:Ej: feature/reservation-reminders}
**Descripción del trabajo:** ${input:work_description:¿Qué se implementó en esta rama?}
**Número de issue (si existe):** ${input:issue_number:Número de issue de GitHub o vacío}

## Flujo obligatorio

### Paso 1 — Invocar `verification-before-completion`

**Sin evidencia real no hay "completado".** Ejecutar:

```bash
make test-unit        # Tests unitarios en paralelo
make test-integration # Tests de integración
make phpstan          # Análisis estático nivel 5
make psalm            # Psalm (segundo análisis estático)
make cs-check         # PSR-12 dry-run
```

Si alguno falla → corregir antes de continuar. No crear PR con tests en rojo.

### Paso 2 — Verificar Definition of Done

Leer `DEFINITION_OF_DONE.md` y confirmar cada criterio aplicable:

**Para código PHP:**
- [ ] `declare(strict_types=1)` en todos los archivos nuevos
- [ ] PHPStan nivel 5 sin errores nuevos en la baseline
- [ ] Tests unitarios para toda lógica de negocio nueva
- [ ] Result pattern en todos los services nuevos/modificados
- [ ] CSRF en todas las rutas mutantes
- [ ] `#[\Override]` en métodos de override/implementación

**Para vistas:**
- [ ] Auditoría Lighthouse accesibilidad ≥ 90
- [ ] Dark mode funcionando (si aplica)
- [ ] Responsivo mobile-first

**General:**
- [ ] Sin `var_dump`, `print_r`, `error_log`, `dd()` en código de producción
- [ ] Sin secretos hardcodeados — usar `Env::get()` / `SecretLoader`
- [ ] CHANGELOG.md actualizado (si el cambio es relevante para el usuario)

### Paso 3 — Invocar `requesting-code-review`

Seguir la skill para auto-revisar el diff antes de solicitar review externo.

### Paso 4 — Crear PR con GitHub MCP

```
git_create_pull_request(
    title: "feat: ${input:work_description:}",
    head: "${input:branch_name:}",
    base: "main",
    body: [plantilla de PR abajo]
)
```

**Plantilla del body del PR:**

```markdown
## ¿Qué hace este PR?
${input:work_description:}

## Cambios incluidos
- 

## Tests
- [ ] `make test-unit` ✅
- [ ] `make test-integration` ✅
- [ ] `make phpstan` ✅

## Capturas (si hay cambios visuales)
<!-- Pegar screenshots de Playwright aquí -->

## Issues relacionados
Closes #${input:issue_number:}
```

### Paso 5 — Invocar `finishing-a-development-branch`

Para decidir la estrategia de integración (merge / squash / rebase) y limpieza post-merge.

---
**Referencia:** `DEFINITION_OF_DONE.md` | `CONTRIBUTING.md` | skill `finishing-a-development-branch`

