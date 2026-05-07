---
mode: agent
description: "Code review estructurado con checklist completo del proyecto. Revisa patrones PHP, seguridad, tests, accesibilidad y rendimiento antes de aprobar un PR."
---

# Revisión de Código — Estructurada

Voy a realizar un code review sistemático siguiendo los estándares del proyecto.

## Información

**PR o rama a revisar:** ${input:pr_or_branch:Número de PR o nombre de rama}
**Tipo de cambio:** ${input:change_type:feature/bugfix/refactor/vista/migración/api}

## Flujo obligatorio

### Paso 1 — Obtener el diff

```
git_pull_request_read(method="get_diff", pullNumber: ${input:pr_or_branch:})
git_pull_request_read(method="get_files", pullNumber: ${input:pr_or_branch:})
```

### Paso 2 — Invocar `requesting-code-review`

Leer y seguir la skill antes de emitir cualquier opinión.

### Paso 3 — Checklist de revisión

#### 🔒 Seguridad y Convenciones PHP

- [ ] `declare(strict_types=1)` en todos los archivos PHP nuevos/modificados
- [ ] Sin `eval()`, `exec()`, `shell_exec()`, `system()` sin justificación
- [ ] Sin secretos hardcodeados — todo vía `Env::get()` / `SecretLoader`
- [ ] Sin `var_dump()`, `print_r()`, `error_log()`, `dd()` en código de producción
- [ ] Tipos declarados en parámetros y retornos de función
- [ ] `#[\Override]` presente en métodos que implementan/sobreescriben

#### 🏗️ Arquitectura y Patrones

- [ ] Services devuelven `Result::ok()` / `Result::fail()` — nunca excepciones esperadas
- [ ] Controllers inyectan **interfaces** de `app/Services/Contracts/`, no clases concretas
- [ ] DTOs son `final readonly class` con `fromArray()` + `toViewArray()`
- [ ] `View::render()` solo recibe escalares, arrays o `Raw`
- [ ] Repositories extienden `AbstractRepository` con `#[\Override]` en los dos métodos abstractos
- [ ] CSRF middleware en **todas** las rutas POST/PUT/PATCH/DELETE
- [ ] Flash messages usan helpers semánticos (`Flash::success/error/warning/info`)

#### 🧪 Tests

- [ ] Hay tests para toda lógica de negocio nueva
- [ ] Tests tienen el docblock obligatorio (3 preguntas)
- [ ] Clases de test son `final`
- [ ] Se usa `createStub()` — no `getMock()`, no Mockery
- [ ] Aserciones sobre `$result->ok`, `$result->data`, `$result->getMessage()`
- [ ] Sin infrastructure real en tests unitarios (BD, Redis, email)

#### 🎨 Vistas y Accesibilidad (si hay cambios visuales)

- [ ] Contraste de color ≥ 4.5:1
- [ ] Focus visible en elementos interactivos
- [ ] Imágenes con `alt` descriptivo
- [ ] Roles ARIA correctos
- [ ] Dark mode no roto
- [ ] Lighthouse accesibilidad ≥ 90

#### ⚡ Rendimiento

- [ ] Sin queries N+1 (revisar bucles con llamadas a la BD)
- [ ] Índices en columnas usadas en WHERE/JOIN de migraciones nuevas
- [ ] Sin `SELECT *` en repositories

#### 📝 Calidad del código

- [ ] Nombres descriptivos (sin abreviaciones crípticas)
- [ ] Sin código comentado o `TODO` sin ticket
- [ ] CHANGELOG.md actualizado si el cambio es relevante para el usuario
- [ ] Logs con nivel correcto y sin datos sensibles

### Paso 4 — Emitir el review

Usar GitHub MCP para comentar en el PR:

```
git_pull_request_review_write(
    method="create",
    event: "APPROVE" | "REQUEST_CHANGES" | "COMMENT"
)
```

Para comentarios de línea específica:
```
git_add_comment_to_pending_review(path, line, body)
```

### Paso 5 — Si hay cambios solicitados

Invocar `receiving-code-review` para procesar el feedback con rigor técnico.

---
**Referencia:** `DEFINITION_OF_DONE.md` | skill `requesting-code-review` | skill `receiving-code-review`

