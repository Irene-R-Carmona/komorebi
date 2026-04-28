# Plan: Mejora de Cobertura de Tests — Komorebi Café

**Fecha:** 28 de abril de 2026
**Estado:** 🟡 En implementación — Fase 1 en progreso
**Objetivo:** Llevar la cobertura de líneas del 43.88% actual hasta ~54% mediante
infraestructura de tests reutilizable y profundización de los tests existentes.

---

## Baseline (28-04-2026, coverage.xml)

| Métrica | Valor |
|---|---|
| Lines | **43.88%** (7026 / 16012) |
| Methods | 41.65% (786 / 1887) |
| Classes | 23.72% (65 / 274) |

Distribución:
- 0% cobertura: 53 clases
- 1–29% cobertura: 48 clases
- 30–79% cobertura: 66 clases
- ≥80% cobertura: 107 clases

---

## Fases

### FASE 1 — Infraestructura de tests (base class + factories)

**Objetivo:** Eliminar duplicación de `makePdo()` en 30 repositorios y centralizar
factories de filas PDO y DTOs de prueba.

- [x] F1.1 — Crear `tests/Unit/Repositories/RepositoryTestCase.php` (base class con `makePdo()`)
- [x] F1.2 — Crear `tests/Unit/Repositories/RowFactory.php` (arrays de fila PDO por entidad)
- [x] F1.3 — Expandir `tests/Unit/Services/ServiceTestCase.php` (factories: `makeUser()`, `makeReservation()`, `makeLoyaltyCard()`, `makeWaitlistEntry()`)
- [ ] F1.4 — Verificar que todos los tests pasan (`phpunit --no-coverage`)

---

### FASE 2 — Repositorios infrautilizados

**Objetivo:** Llevar los 6 repositorios más grandes de <25% a ≥75%.
Ganancia estimada: **+657 statements → ~48% lines**.

| Repositorio | Stmts | Actual | Target |
|---|---|---|---|
| `ProductRepository` | 335 | 21.5% | 75% |
| `UserRepository` | 248 | 16.5% | 75% |
| `ReservationRepository` | 255 | 22.4% | 75% |
| `WaitlistRepository` | 139 | 17.3% | 75% |
| `ReviewRepository` | 92 | 26.1% | 75% |
| `CafeRepository` | 244 | 43% | 75% |

- [ ] F2.1 — `ProductRepositoryTest`: ampliar a ≥75%
- [ ] F2.2 — `UserRepositoryTest`: ampliar a ≥75%
- [ ] F2.3 — `ReservationRepositoryTest`: ampliar a ≥75%
- [ ] F2.4 — `WaitlistRepositoryTest`: ampliar a ≥75%
- [ ] F2.5 — `ReviewRepositoryTest`: ampliar a ≥75%
- [ ] F2.6 — `CafeRepositoryTest`: ampliar a ≥75%

---

### FASE 3 — Servicios con tests delgados

**Objetivo:** Profundizar los servicios que solo cubren validaciones de entrada.
Ganancia estimada: **+493 statements → ~52% lines**.

| Servicio | Stmts | Actual | Target |
|---|---|---|---|
| `ReservationService` | 250 | 22.4% | 70% |
| `WaitlistService` | 262 | 53.8% | 75% |
| `LoyaltyService` | 187 | 41.2% | 70% |
| `AuthService` | 171 | 31.6% | 70% |
| `CartService` | 123 | 27.6% | 70% |
| `AdminActivityService` | 147 | 36.1% | 70% |
| `ProductService` | 135 | 37.8% | 70% |

- [ ] F3.1 — `ReservationServiceTest`: ampliar a ≥70%
- [ ] F3.2 — `WaitlistServiceTest`: ampliar a ≥75%
- [ ] F3.3 — `LoyaltyServiceTest`: ampliar a ≥70%
- [ ] F3.4 — `AuthServiceTest`: ampliar a ≥70%
- [ ] F3.5 — `CartServiceTest`: ampliar a ≥70%
- [ ] F3.6 — `AdminActivityServiceTest`: ampliar a ≥70%
- [ ] F3.7 — `ProductServiceTest`: ampliar a ≥70%

---

### FASE 4 — Utilidades de dominio (0% → cubiertos)

**Objetivo:** Cubrir clases pequeñas sin dependencias HTTP.
Ganancia estimada: **+250 statements → ~54% lines**.

- [ ] F4.1 — `Core\Raw`: 20 stmts
- [ ] F4.2 — `Core\Time`: 23 stmts
- [ ] F4.3 — `Domain\Reservation\ReservationStatus`: 11 stmts
- [ ] F4.4 — `Domain\AnimalVocabulary`, `AvatarOptions`, `CareLogVocabulary`: 18 stmts
- [ ] F4.5 — `Jobs\SendEmailJob`, `Jobs\RewardUnlockedJob`: 177 stmts
- [ ] F4.6 — `Core\Flash`: 31 stmts (requiere mocking de `$_SESSION`)

---

## Principios aplicados

1. **Test el comportamiento, no la implementación** — qué devuelve el método con datos dados.
2. **Un escenario por test** — naming `testNombreMetodo_condicion_resultado()`.
3. `createStub()` por defecto; `createMock()` solo cuando el número de llamadas importa.
4. `#[DataProvider]` para variantes de un mismo camino lógico (validaciones).
5. `#[CoversClass]` obligatorio en todos los test files.
6. No tests de integración disfrazados de unitarios — repos usan PDO stub, no DB real.

---

## Herramienta de auditoría

`scripts/coverage-audit.php` — parsea `tests/reports/coverage.xml` y muestra cobertura
por clase ordenada de menor a mayor. Uso: `docker compose exec app php scripts/coverage-audit.php /app/tests/reports/coverage.xml [zero|low|all]`
