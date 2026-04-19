# Plan: Unificación de Estilos de Funciones y Clases Globales PHP

**Fecha:** 17 de abril de 2026
**Estado:** 🟡 En progreso — ejecutar `make cs-fix` para completar
**Rama sugerida:** `fix/unify-global-php-style`

---

## Contexto y Motivación

Una auditoría detectó inconsistencias en el estilo de referencias a clases y funciones
del namespace global de PHP. El proyecto tiene reglas definidas en `.php-cs-fixer.php`
que resuelven esto automáticamente.

## Estilo canónico (definido en `.php-cs-fixer.php`)

| Tipo | Estilo canónico | Regla php-cs-fixer |
|---|---|---|
| **Clases globales** (`PDO`, `Throwable`, `RuntimeException`, etc.) | `use PDO;` + `PDO::FETCH_ASSOC` (import con `use`) | `import_classes => true` |
| **Funciones nativas** (`time`, `trim`, `array_map`, etc.) | `\time()`, `\trim()` (FQFN con `\`) | `native_function_invocation` |
| **Constantes globales** (`PHP_EOL`, etc.) | `\PHP_EOL` (FQFN con `\`) | `import_constants => false` |

> **Nunca** usar FQFN `\` para clases globales (`\PDO`, `\Throwable`).
> **Nunca** usar `use function` para funciones nativas.
> Referencia: `AGENTS.md` → Critical Patterns.

## Implementación

**No se requiere edición manual.** El fixer aplica todas las reglas automáticamente:

```bash
make cs-fix        # Aplica estilo en todo el proyecto
make cs-check      # Verifica sin modificar (dry-run)
```

### Tasks

- [ ] **PASO 1** — Ejecutar `make cs-fix` dentro del contenedor Docker
- [ ] **PASO 2** — Ejecutar `make cs-check` — verificar 0 violaciones
- [ ] **PASO 3** — Ejecutar `make phpstan` — verificar 0 errores nuevos respecto a baseline
- [ ] **PASO 4** — Ejecutar `make test-unit` — verificar todos los tests en verde
- [ ] **PASO 5** — Revisar diff con `git diff --stat` para confirmar los cambios

### Categoría adicional (manual) — `DateTime` mutable → `DateTimeImmutable`

php-cs-fixer **no** migra `DateTime` a `DateTimeImmutable`. Estos cambios sí requieren intervención manual:

| Archivo | Cambio |
|---|---|
| `app/Services/ClimaContextoService.php` | `new DateTime(...)` → `new DateTimeImmutable(...)` |
| `app/Services/MicroestacionesService.php` | `new DateTime(...)` → `new DateTimeImmutable(...)` |
| `app/Services/FestivosJaponesesService.php` | `DateTime` → `DateTimeImmutable` + ajustar `->modify()` |
| `app/Core/Seeders/TimeSlotSeeder.php` | `new DateTime()` → `new DateTimeImmutable()` |
| `app/Repositories/ReservationRepository.php` | Verificar si usa `->modify()` mutable |

---

## Criterios de Aceptación

1. `make cs-check` → 0 violaciones.
2. `make phpstan` → 0 errores nuevos (igual o menor que baseline).
3. `make test-unit` → todos los tests en verde.
4. `grep -r "new DateTime(" app/Services/` → 0 resultados (`DateTime` mutable eliminado de Services).

---

## Lección aprendida

Este plan se creó originalmente proponiendo edición manual archivo por archivo con estilo FQFN
(`\PDO`, `\Throwable`), **contradiciendo** la configuración de `.php-cs-fixer.php` que define
`import_classes => true`. El resultado fue trabajo manual innecesario que php-cs-fixer revierte.

**Regla:** Siempre consultar la herramienta de formateo antes de editar estilo manualmente.
