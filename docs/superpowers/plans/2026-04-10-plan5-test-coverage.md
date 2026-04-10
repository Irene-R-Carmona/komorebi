# Plan 5 — Cobertura de Tests en Servicios

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps marked ✅ son confirmaciones de estado — NO re-implementar lo que ya está hecho.

**Goal:** Cerrar tres gaps de cobertura identificados por auditoría: (1) `ReservationTimeSlotService` no tiene test unitario propio —solo un `QueueTest` que no toca la lógica del servicio—, (2) `AuthServiceTest` tiene 2 tests comentados bloqueados por el bug DI de Plan 1, (3) `AdminServiceTest` cubre solo el método interno `calculateTrend()` dejando los métodos de dominio sin verificación.

**Architecture:** Los tres grupos son independientes y ejecutables en paralelo. El Grupo B depende de que Plan 1 esté completado (constructor de `AuthService` ya recibe `RateLimitingServiceInterface` inyectado). Todos los tests siguen el patrón del proyecto: docblock obligatorio, `createStub()` para dependencias, assertions sobre `Result`.

**Tech Stack:** PHP 8.4, PHPUnit 12, `createStub()` (no `createMock()`), `Result` pattern, `TransactionalService`.

---

## Estado confirmado por auditoría (NO re-implementar)

| Item | Estado | Evidencia |
|---|---|---|
| 42 de 43 servicios tienen test | ✅ | `tests/Unit/Services/` — 42 archivos |
| `WaitlistServiceTest` 9 tests | ✅ | Cubre todos los métodos públicos de dominio |
| `ReviewServiceTest` 16 tests | ✅ | Validaciones, moderación, eventos |
| `AuthServiceTest` 14 tests activos | ✅ | Login, registro, hash, normalización |
| `AdminServiceTest` `calculateTrend()` | ✅ | 6 tests para la utilidad matemática interna |
| `ReservationTimeSlotServiceQueueTest` | ✅ | Testa `Queue::push()` — NO la lógica del servicio |
| `AccountDeletionServiceTest` 3 tests | ✅ | `deleteAndAnonymize()`: ok, PDOException, prepare false |
| `SettingsServiceTest` 6 tests | ✅ | validate, getSmtpConfig, getStats, getByGroup, isSmtpEnabled |
| `UserManagementServiceTest` 8 tests | ✅ | Validaciones de datos: nombre, email, password, roleId |
| `HolidayServiceTest` 7 tests | ✅ | Validaciones de rango y formato de fecha |
| `AllergenServiceTest` 11 tests | ✅ | Validaciones de IDs y datos de alérgeno |

---

## Mapa de archivos afectados

| Grupo | Archivo | Acción |
|---|---|---|
| A | `tests/Unit/Services/ReservationTimeSlotServiceTest.php` | CREAR — 5 tests unitarios de dominio |
| B | `tests/Unit/Services/AuthServiceTest.php` | Descomentar y completar 2 tests comentados |
| C | `tests/Unit/Services/AdminServiceTest.php` | Añadir tests para métodos de dominio |

---

## Grupo A — Crear `ReservationTimeSlotServiceTest`

### A1: Leer el servicio para mapear métodos

- [ ] Leer `app/Services/ReservationTimeSlotService.php` completo — documentar métodos públicos, parámetros y caminos de éxito/error
- [ ] Leer `app/Core/TransactionalService.php` — entender qué hereda (`executeInTransaction()`, rollback automático)
- [ ] Leer `tests/Unit/Services/ReservationTimeSlotServiceQueueTest.php` — ver qué ya está cubierto para no duplicar

---

### A2: Crear el archivo de test con setUp() y 5 tests mínimos

**Docblock requerido al inicio del archivo:**

```php
/**
 * ¿Qué pruebas aquí?
 * Lógica de negocio de ReservationTimeSlotService: coordinación atómica de reservas y time slots.
 * ¿Qué me quieres demostrar?
 * Que el servicio valida disponibilidad, delega correctamente a los modelos y retorna Result.
 * ¿Qué va a fallar en este test si se cambia el código?
 * Cualquier cambio en las condiciones de disponibilidad, orden de operaciones o estructura de Result.
 */
```

**setUp() con stubs:**

```php
protected function setUp(): void
{
    parent::setUp();

    $this->db          = $this->createStub(\PDO::class);
    $this->reservation = $this->createStub(\App\Models\Reservation::class);
    $this->timeSlot    = $this->createStub(\App\Models\TimeSlot::class);
    $this->waitlist    = $this->createStub(\App\Models\Waitlist::class);

    $this->service = new ReservationTimeSlotService(
        $this->db,
        $this->reservation,
        $this->timeSlot,
        $this->waitlist,
    );
}
```

**Tests a implementar (mínimo 5):**

- [ ] `testCreateReservationWithSlotFailsWhenSlotNotFound` — `TimeSlot` stub retorna null para el slot → `Result::fail()`
- [ ] `testCreateReservationWithSlotFailsWhenNoCapacity` — slot sin capacidad disponible → `Result::fail()` con mensaje apropiado
- [ ] `testCreateReservationWithSlotSuccessReturnsOkResult` — flujo feliz completo → `Result::ok()` con datos de reserva
- [ ] `testCreateReservationWithSlotRollsBackOnReservationFailure` — fallo al crear reserva → transacción revertida, `Result::fail()`
- [ ] `testCreateReservationWithSlotDecrementsCapacityOnSuccess` — el slot se decrementa exactamente una vez tras crear la reserva con éxito

- [ ] Ejecutar `vendor/bin/phpunit tests/Unit/Services/ReservationTimeSlotServiceTest.php --testdox` — confirmar RED (fallo esperado por razone correctas, no errores de sintaxis)
- [ ] Verificar que los tests fallan porque el comportamiento no está siendo ejercitado, no por errores de configuración
- [ ] (Si el código ya existe y está correcto, los tests pasarán directamente — documentar si alguno revela un bug real)

---

### A3: Confirmar GREEN y commit

- [ ] Ejecutar `make test-unit` — suite completa en verde
- [ ] Commit: `test: añadir ReservationTimeSlotServiceTest con cobertura de métodos de dominio`

---

## Grupo B — Recuperar tests comentados en `AuthServiceTest`

### B1: Prerequisito — Plan 1 completado

**Condición:** Este grupo SOLO se ejecuta DESPUÉS de completar Plan 1 (constructor de `AuthService` recibe `RateLimitingServiceInterface` como parámetro requerido, no `?? new`).

- [ ] Verificar que `app/Services/AuthService.php` ya NO tiene `?? new RateLimitingService(...)` en el constructor
- [ ] Verificar que `tests/Unit/Services/AuthServiceTest.php` `setUp()` ya inyecta un stub de `RateLimitingServiceInterface`

---

### B2: Descomentar y completar los 2 tests pendientes

- [ ] Leer `tests/Unit/Services/AuthServiceTest.php` completo — localizar los 2 tests comentados y el setUp() actual

**`testLoginWithWrongPasswordFails`:**

- Configurar stub de `UserRepository::findByEmail()` para que retorne un array de usuario con `password` hasheado con `password_hash('correct_password', PASSWORD_ARGON2ID)`
- Llamar `$this->service->login(['email' => 'test@example.com', 'password' => 'wrong_password'])`
- Afirmar `$result->ok === false` y `$result->getMessage()` contiene el mensaje de credenciales incorrectas

**`testRegisterWithValidDataSuccess`:**

- Configurar stub de `UserRepository::findByEmail()` para que retorne `null` (email libre)
- Configurar stub de `UserRepository::create()` para que retorne `['id' => 1, 'email' => 'new@example.com', 'name' => 'Test User']`
- Llamar `$this->service->register(['name' => 'Test User', 'email' => 'new@example.com', 'password' => 'SecurePass123!', 'password_confirmation' => 'SecurePass123!'])`
- Afirmar `$result->ok === true` y `$result->data['id'] === 1`

- [ ] Descomentar `testLoginWithWrongPasswordFails` — actualizar configuración de stub si es necesario
- [ ] Descomentar `testRegisterWithValidDataSuccess` — completar implementación si faltaba código
- [ ] Ejecutar `vendor/bin/phpunit tests/Unit/Services/AuthServiceTest.php --testdox` — 16 tests en verde
- [ ] Commit: `test: recuperar tests comentados en AuthServiceTest tras fix DI de Plan 1`

---

## Grupo C — Expandir `AdminServiceTest` con métodos de dominio

### C1: Auditar qué métodos de `AdminService` quedan sin cobertura

- [ ] Leer `app/Services/AdminService.php` completo — listar todos los métodos públicos (constructor, parámetros, return types)
- [ ] Cruzar con `tests/Unit/Services/AdminServiceTest.php` — confirmar qué métodos quedan sin test
- [ ] Decisión de arquitectura: ¿`AdminService` acepta PDO inyectado o usa `Database::getConnection()` estático?

---

### C2: Añadir tests según la arquitectura del servicio

**Si `AdminService` acepta PDO inyectado (testeable como unidad):**

```php
protected function setUp(): void
{
    parent::setUp();
    $this->pdo     = $this->createStub(\PDO::class);
    $this->service = new AdminService($this->pdo);
}
```

Añadir mínimo 5 tests que cubran métodos con lógica de transformación, validación o cálculo.

**Si `AdminService` usa `Database::getConnection()` estático (no inyectable):**

- Crear `tests/Integration/Services/AdminServiceIntegrationTest.php` en lugar de extender el unit test
- Los tests de integración conectan a la BD de test real via `make test-integration`

**Tests a implementar (mínimo 5, según los métodos hallados en C1):**

- [ ] Test para cada método que tenga lógica condicional (if/else) o cálculos no triviales
- [ ] Evitar testear que PDO ejecuta queries — eso es responsabilidad del repositorio
- [ ] Priorizar métodos que retornen `Result` (se pueden testear caminos ok y fail)

- [ ] Ejecutar `make test-unit` (o `make test-integration` si es el caso)
- [ ] Commit: `test: ampliar AdminServiceTest con cobertura de métodos de dominio`

---

## Comandos de verificación

```bash
# Tests individuales de los servicios afectados
docker compose exec app vendor/bin/phpunit tests/Unit/Services/ReservationTimeSlotServiceTest.php --testdox
docker compose exec app vendor/bin/phpunit tests/Unit/Services/AuthServiceTest.php --testdox
docker compose exec app vendor/bin/phpunit tests/Unit/Services/AdminServiceTest.php --testdox

# Suite completa
make test-unit

# Cobertura HTML (opcional)
make test-coverage
# Ver: tests/reports/coverage/index.html → columna Services
```

---

## Commits sugeridos

```
test: añadir ReservationTimeSlotServiceTest con cobertura de métodos de dominio
test: recuperar tests comentados en AuthServiceTest tras fix DI de Plan 1
test: ampliar AdminServiceTest con cobertura de métodos de dominio
```

---

## Siguiente plan

**Fase 2 — PSR-7 Migration:** `UserController` y `AnimalController` son los dos controladores que no siguen el contrato PSR-7 (`?ResponseInterface` retorno, `ServerRequestInterface` parámetro). Son el desbloqueador para Fase 3 y 4.
