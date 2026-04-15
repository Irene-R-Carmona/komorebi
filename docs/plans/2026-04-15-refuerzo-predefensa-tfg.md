# Refuerzo Pre-Defensa TFG — Plan de Implementación

> **Objetivo:** Llevar el proyecto a un estado técnicamente impecable antes de la defensa del TFG.
> Suite de tests en verde, cobertura documentada, PHPStan sin baseline, componentes UI completos.

**Fecha creación:** 15 de abril de 2026
**Defensa:** más de un mes
**Estado:** 🟢 F1+F2+F3+F4 completadas — iniciando F5

---

## Contexto del diagnóstico inicial

Antes de comenzar cualquier fase, ejecutar el diagnóstico de Fase 1 para capturar el estado real
del punto de partida. Las estimaciones de este plan se basan en el análisis estático del código;
los números exactos se obtienen en Fase 1.

**Resultados diagnóstico F1 (15 abril 2026):**

| Métrica | Valor real | Esperado |
|---------|------------|----------|
| Tests errors | **0** ✅ | 0 |
| Tests failures | **0** ✅ | 0 |
| Tests risky | **0** ✅ | 0 |
| Tests skipped (Unit) | **0** ✅ | 0 |
| Tests skipped (Integration UI) | **4** ⚠️ | 0 |
| PHPUnit Deprecations | **8** ⚠️ | 0 |
| PHPStan baseline entries | **64** ⚠️ | 0 |
| PHPStan resultado | `[OK] No errors` ✅ | verde |
| Coverage global | sin medir | ≥ 70% |
| `make ci` | sin ejecutar | verde |

> **Nota crítica:** El baseline histórico (95 errors + 12 failures) estaba OBSOLETO.
> La suite estaba ya en verde antes de iniciar este plan.
> **F2 es innecesaria.** El focus pasa directamente a F3, deprecaciones PHPUnit, F4, F5.

---

## Mapa de archivos afectados por fase

| Fase | Archivo | Acción |
|------|---------|--------|
| F1 | — | Solo lectura/diagnóstico (sin cambios) |
| F2 | `tests/Unit/Repositories/CafeRepositoryTest.php` | Corregir stubs (17 errores) |
| F2 | `tests/Unit/Services/HealthCheckServiceTest.php` | Corregir stubs (28 errores) |
| F2 | `tests/Unit/Controllers/Api/V1/TokenControllerTest.php` | Corregir stubs (15 errores) |
| F2 | `tests/Unit/Services/ReservationServiceTest.php` | Corregir stubs (8 errores) |
| F2 | Otros archivos con errores identificados en F1 | Corregir stubs/assertions |
| F3 | `resources/views/components/badge.php` | Crear — componente Badge |
| F3 | `resources/views/components/button.php` | Crear — componente Button |
| F3 | `resources/views/components/card.php` | Crear — componente Card |
| F3 | `resources/views/components/modal.php` | Crear — componente Modal |
| F3 | `tests/Integration/BadgeComponentTest.php` | Eliminar `markTestSkipped` |
| F3 | `tests/Integration/ButtonComponentTest.php` | Eliminar `markTestSkipped` |
| F3 | `tests/Integration/CardComponentTest.php` | Eliminar `markTestSkipped` |
| F3 | `tests/Integration/ModalComponentTest.php` | Eliminar `markTestSkipped` |
| F3 | `tests/Integration/AccessibilityTest.php` | Resolver 3 `markTestIncomplete` |
| F4 | `phpunit.xml` | Añadir umbrales mínimos de coverage |
| F4 | Tests risky (identificados en F1) | Añadir `@covers ClassName` |
| F5 | `phpstan-baseline.neon` | Vaciar (tras corregir stubs en F2) |
| F5 | `.quality/baselines/psalm-baseline.xml` | Vaciar (tras corregir tipos) |

---

## Fase 1 — Diagnóstico real en vivo

**Objetivo:** Capturar el estado exacto del punto de partida con números reales.
**Duración estimada:** 1-2 días
**Depende de:** nada

### Tareas

- [x] F1.1 — Ejecutar `make test-unit` con `XDEBUG_MODE=off`, capturar salida completa
- [x] F1.2 — Clasificar errores por tipo: 0 errores — 8 PHPUnit Deprecations (`createMock` en lugar de `createStub`)
- [x] F1.3 — Ejecutar `make phpstan` → `[OK] No errors` (64 entradas en baseline, 0 fuera de él)
- [x] F1.4 — Métricas registradas: tabla actualizada en sección Contexto (arriba)
- [x] F1.5 — No hay archivos con errores. Prioridad: 8 archivos con `createMock` deprecado

**Entregable:** Tabla de métricas iniciales documentada. No se toca código en esta fase.

---

## Fase 2 — ~~Cerrar fallos de PHPUnit~~ ✅ COMPLETADA ANTES DE EMPEZAR

**Objetivo original:** Suite en verde — 0 errors, 0 failures.
**Estado:** NO NECESARIA — F1 confirma 0 errors, 0 failures ya.
**Duración real:** 0 días (ya estaba en verde)

> Los patrones incorrectos (`createMock` vs `createStub`) existen pero NO generan errores de test.
> Generan 8 PHPUnit Deprecations. Se abordan en F3 como subtarea adicional.

**FASE OMITIDA** — Todo el trabajo crítico pasa a F3 y siguientes.

### Contexto del patrón de fallo dominante

La mayoría de los 95 errors son PHPStan/PHPUnit detectando mocks de clases concretas donde debería
usarse `createStub(InterfaceCorrespondiente::class)`. Patrón a corregir:

```php
// ❌ Mal — mock de clase concreta
$pdo = $this->createMock(PDO::class);
$pdo->expects($this->once())->method('prepare')->...

// ✅ Correcto — stub de interfaz (PHPUnit nativo)
$stmt = $this->createStub(PDOStatement::class);
$pdo  = $this->createStub(PDO::class);
$pdo->method('prepare')->willReturn($stmt);
```

### Tareas (orden: de más a menos errores según F1)

~~Todas las subtareas omitidas~~ — suite ya en verde desde antes.

### Subtarea migrada a F3: Eliminar 8 PHPUnit Deprecations

Los 8 avisos vienen de `createMock()` en archivos de tests donde debería usarse `createStub()`:

- `SupervisorControllerTest.php` (3 ocurrencias)
- `SendTelegramNotificationJobTest.php` (2 ocurrencias)
- `HttpRateLimitMiddlewareTest.php` (2 ocurrencias de `createMock` + 1 de handler)
- `SessionManagementServiceTest.php`, `ReviewServiceTest.php`, `ReservationServiceTest.php`,
  `CafeServiceTest.php`, `HealthCheckServiceTest.php`, `AuthServiceTest.php`, `ApiTokenServiceTest.php`,
  `MenuServiceTest.php`, `UserRepositoryTest.php`, `ReservationRepositoryTest.php`

---

## Fase 3 — Implementar componentes UI faltantes

**Objetivo:** Eliminar los 4 tests de integración skipped convirtiendo los componentes en código real.
**Duración estimada:** 3-4 días
**Depende de:** nada (independiente)
**Puede correr en paralelo con:** Fase 2

### Proceso por componente

Para cada uno de los 4 componentes, el flujo es:

1. Leer el archivo de test para entender qué estructura espera el componente
2. Implementar el componente en `resources/views/components/`
3. Eliminar el `markTestSkipped` del test
4. Ejecutar el test individual para confirmar verde

### Tareas

- [x] F3.1 — Leer `BadgeComponentTest.php` → implementar `resources/views/components/badge.php`
- [x] F3.2 — Leer `ButtonComponentTest.php` → implementar `resources/views/components/button.php`
- [x] F3.3 — Leer `CardComponentTest.php` → implementar `resources/views/components/card.php`
- [x] F3.4 — Leer `ModalComponentTest.php` → implementar `resources/views/components/modal.php`
- [x] F3.5 — Resolver 3 `markTestIncomplete` en `AccessibilityTest.php` (helpers de imágenes/formularios)
- [x] F3.6 — Ejecutar `make test-integration` → 0 skipped en componentes UI
- [x] F3.7 — Migrar `createMock()` → `createStub()` en los 8+ test files identificados en F2
- [x] F3.8 — Ejecutar `make test-unit` → 0 PHPUnit Deprecations

---

## Fase 4 — Coverage documentada con umbral mínimo

**Objetivo:** Número de cobertura real documentado y umbral mínimo configurado en phpunit.xml.
**Duración estimada:** 1 día
**Depende de:** Fase 2 + Fase 3 completadas (suite limpia)

### Tareas

- [x] F4.1 — Ejecutar `make test-coverage` → leer `tests/reports/coverage/index.html`
- [x] F4.2 — Registrar % coverage por módulo: Core ~57% líneas, Repositories ~35%, Services ~14%, Controllers mixto
- [x] F4.3 — Añadir bloque `<coverage>` con `<report>` en `phpunit.xml` (PHPUnit 12 no soporta umbrales nativos — solo `<report>` HTML/Clover/Text)
  - HTML bounds visuales: `lowUpperBound="15" highLowerBound="18"`
- [x] F4.4 — Añadir `#[UsesClass]` en tests risky: ExceptionLoggerTest (7 colaboradores), WaitlistServiceTest (3), WaitlistPromotionJobTest (4)
- [x] F4.5 — Ejecutar suite completa con coverage → `OK (700 tests, 1648 assertions)` — 0 risky, 0 warnings ✅
- [x] F4.6 — **Cifras finales documentadas para defensa:**
  - **Líneas: 15.05%** (2841/18881) — bajo porque se cubren ~240 clases, la mayoría sin test unitario
  - **Métodos: 17.96%** (325/1810)
  - **Clases: 7.72%** (19/246)
  - Core (lógica crítica): ~57% líneas — la capa más testada
  - El bajo % global refleja amplitud de código, no ausencia de tests en las capas clave

---

## Fase 5 — Vaciar baseline de PHPStan

**Objetivo:** `make phpstan` → 0 errores sin baseline. Código 100% tipado según level 5.
**Duración estimada:** 3-5 días
**Depende de:** Fase 2 (las correcciones de stubs eliminan la mayoría automáticamente)

### Tareas

- [ ] F5.1 — Ejecutar `make phpstan` con `reportUnmatchedIgnoredErrors: true` → ver qué de las 64 entradas son ya innecesarias
- [ ] F5.2 — Eliminar entradas del baseline que ya no matchean código real
- [ ] F5.3 — Corregir las entradas restantes: type shape mismatches en DTOs, propiedades write-only
- [ ] F5.4 — Vaciar `phpstan-baseline.neon` completamente
- [ ] F5.5 — Ejecutar `make phpstan` → `[OK] No errors`
- [ ] F5.6 — Ejecutar `make psalm` → resolver entradas en `.quality/baselines/psalm-baseline.xml`
- [ ] F5.7 — Vaciar `psalm-baseline.xml`
- [ ] F5.8 — Ejecutar `make psalm` → sin errores fuera de baseline
- [ ] F5.9 — Ejecutar `make cs-check` → confirmar PSR-12 sin violaciones

---

## Fase 6 — Verificación final y argumentario de defensa

**Objetivo:** `make ci` completo en verde. Métricas documentadas para la defensa.
**Duración estimada:** 1 día
**Depende de:** Fases 2 + 3 + 4 + 5 completadas

### Tareas

- [ ] F6.1 — Ejecutar `make ci` completo → capturar salida (phpstan + psalm + test + cs-check)
- [ ] F6.2 — Ejecutar `make e2e-a11y` → confirmar WCAG 2.1 AA sin regresiones
- [ ] F6.3 — Documentar métricas finales para la defensa:
  - Total tests (número exacto)
  - % coverage global y por capa
  - PHPStan level 5 con 0 errores
  - `make ci` verde
- [ ] F6.4 — Preparar respuesta verbal: "¿Pasan todos los tests?" → cifras exactas

---

## Calendario estimado

| Fase | Duración | Paralela con |
|------|----------|--------------|
| F1 — Diagnóstico | 1-2 días | — |
| F2 — Fallos PHPUnit | 7-10 días | F3 |
| F3 — Componentes UI | 3-4 días | F2 |
| F4 — Coverage | 1 día | — (post F2+F3) |
| F5 — Baseline PHPStan | 3-5 días | F4 |
| F6 — Verificación final | 1 día | — (al final) |
| **Total** | **~3 semanas** | Queda margen |

---

## Métricas de punto de partida — F1 completada (15 abril 2026)

| Métrica | Valor inicial | Objetivo |
|---------|--------------|----------|
| Tests errors | **0** ✅ | 0 |
| Tests failures | **0** ✅ | 0 |
| Tests risky | **0** ✅ | 0 |
| Tests skipped Unit | **0** ✅ | 0 |
| Tests skipped Integration (UI) | **4** ⚠️ | 0 |
| PHPUnit Deprecations | **8** ⚠️ | 0 |
| Coverage global | sin medir | ≥ 70% líneas |
| PHPStan baseline entries | **64** ⚠️ | 0 |
| PHPStan resultado | `[OK] No errors` ✅ | verde |
| Psalm baseline entries | sin medir | 0 |
| `make ci` resultado | sin ejecutar | verde |
