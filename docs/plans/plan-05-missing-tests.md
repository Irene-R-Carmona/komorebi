# Plan 5: Missing Service Tests (12 services)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development.

**Goal:** Cubrir los 12 servicios sin tests. Prioridad: AccountDeletionService (GDPR), SettingsService, UserManagementService (HIGH), luego HolidayService, InvoicePDFService, AnimalCareService (MEDIUM), resto (LOW).

**Architecture:** TDD estricto por iteración — leer el servicio, identificar contratos de entrada/salida, escribir test que describe el comportamiento, luego verificar. Usar `createStub()` para dependencias. Seguir el patrón de docblock obligatorio.

**Tech Stack:** PHPUnit, Result pattern, createStub()

---

### Task 1: AccountDeletionService — GDPR compliance

**Files:**

- Read first: `app/Services/AccountDeletionService.php`
- Create: `tests/Unit/Services/AccountDeletionServiceTest.php`

- [ ] **Step 1: Leer el servicio**

```bash
docker compose exec app cat app/Services/AccountDeletionService.php
```

- [ ] **Step 2: Escribir tests**

```php
// tests/Unit/Services/AccountDeletionServiceTest.php
<?php
/**
 * ¿Qué pruebas aquí?
 * Verifica que AccountDeletionService anonimiza datos del usuario bajo GDPR.
 *
 * ¿Qué me quieres demostrar?
 * Que delete() retorna Result::ok en happy path,
 * y Result::fail cuando la contraseña es incorrecta o el usuario no existe.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se elimina la verificación de contraseña antes de anonimizar,
 * o si el método deja de retornar Result.
 */
declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Core\Result;
use App\Services\AccountDeletionService;
use PHPUnit\Framework\TestCase;

final class AccountDeletionServiceTest extends TestCase
{
    // Leer el servicio primero y ajustar los stubs según las dependencias reales del constructor.

    public function test_delete_returns_fail_when_user_not_found(): void
    {
        // Stub repositorio que retorna null para any findById
        // Instanciar AccountDeletionService con el stub
        // Llamar delete($userId, $password)
        // Assertar !$result->ok con mensaje sobre usuario no encontrado
        $this->markTestIncomplete('Implementar tras leer el servicio en Step 1');
    }

    public function test_delete_returns_fail_when_password_is_wrong(): void
    {
        $this->markTestIncomplete('Implementar tras leer el servicio en Step 1');
    }

    public function test_delete_anonymizes_user_data_on_success(): void
    {
        $this->markTestIncomplete('Implementar tras leer el servicio en Step 1');
    }

    public function test_delete_returns_ok_result_on_success(): void
    {
        $this->markTestIncomplete('Implementar tras leer el servicio en Step 1');
    }
}
```

**Instrucción para el implementador:** Leer el servicio en Step 1, eliminar los `markTestIncomplete()` y escribir los tests concretos con stubs reales.

- [ ] **Step 3: Ejecutar para verificar estado inicial**

```bash
docker compose exec app vendor/bin/phpunit tests/Unit/Services/AccountDeletionServiceTest.php --colors=always
```

- [ ] **Step 4: Implementar tests completos y ejecutar**

```bash
docker compose exec app vendor/bin/phpunit tests/Unit/Services/AccountDeletionServiceTest.php --colors=always
```

Esperado: todos PASS, sin markTestIncomplete.

- [ ] **Step 5: Commit**

```bash
git add tests/Unit/Services/AccountDeletionServiceTest.php
git commit -m "test: add AccountDeletionService tests (GDPR compliance)"
```

---

### Task 2: SettingsService

**Files:**

- Read first: `app/Services/SettingsService.php`
- Create: `tests/Unit/Services/SettingsServiceTest.php`

- [ ] **Step 1: Leer el servicio**

```bash
docker compose exec app cat app/Services/SettingsService.php
```

- [ ] **Step 2: Escribir tests según lo que se lea**

```php
// tests/Unit/Services/SettingsServiceTest.php
<?php
/**
 * ¿Qué pruebas aquí?
 * Verifica que SettingsService lee y escribe configuraciones del sistema.
 *
 * ¿Qué me quieres demostrar?
 * Que get() retorna el valor correcto y set() persiste el cambio.
 * Que valores inexistentes retornan el default o Result::fail.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se cambia la clave de la tabla de settings o el tipo de retorno.
 */
declare(strict_types=1);

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;

final class SettingsServiceTest extends TestCase
{
    // Implementar tras leer el servicio
}
```

Pattern de test para servicios de CRUD de configuración:

- `test_get_returns_default_when_key_not_found()`
- `test_get_returns_value_when_key_exists()`
- `test_set_returns_ok_result_on_success()`
- `test_set_returns_fail_on_repository_error()`

- [ ] **Step 3: Ejecutar**

```bash
docker compose exec app vendor/bin/phpunit tests/Unit/Services/SettingsServiceTest.php --colors=always
```

- [ ] **Step 4: Commit**

```bash
git add tests/Unit/Services/SettingsServiceTest.php
git commit -m "test: add SettingsService unit tests"
```

---

### Task 3: UserManagementService

**Files:**

- Read first: `app/Services/UserManagementService.php`
- Create: `tests/Unit/Services/UserManagementServiceTest.php`

- [ ] **Step 1: Leer el servicio**

```bash
docker compose exec app cat app/Services/UserManagementService.php
```

Test patterns esperados:

- `test_create_user_returns_ok_with_valid_data()`
- `test_create_user_returns_fail_when_email_already_exists()`
- `test_assign_role_returns_ok_on_success()`
- `test_toggle_status_returns_ok_with_valid_user_id()`

- [ ] **Step 2: Escribir, ejecutar, commit**

```bash
git commit -m "test: add UserManagementService unit tests"
```

---

### Task 4: HolidayService

**Files:**

- Read first: `app/Services/HolidayService.php`
- Create: `tests/Unit/Services/HolidayServiceTest.php`

HolidayService hace HTTP requests (curl). Tests deben mockear el HTTP layer o el método que hace la llamada.

- [ ] **Step 1: Leer el servicio y buscar cómo hace las HTTP calls**

```bash
docker compose exec app cat app/Services/HolidayService.php
```

- [ ] **Step 2: Identificar si hay una interfaz HTTP o httpClient inyectable**

Si usa `curl_exec()` directamente sin abstracción, los tests necesitarán usar un stub del método privado o extraer la llamada HTTP a un método protegido para poder sobreescribir en test.

Pattern con partial mock si no hay HttpClient:

```php
// Alternativa: test del método de parseo sin llamada HTTP
public function test_parse_holiday_response_extracts_correct_dates(): void
{
    $rawResponse = '{"status":1,"holidays":{"2026-01-01":{"name":"Año Nuevo"}}}';
    // Si hay método parseHolidayResponse() público o protegido, llamarlo directamente
    // Si no, usar reflexión o crear un subclass stub
}
```

- [ ] **Step 3: Escribir tests del parsing y cache, no de la HTTP call**

```bash
git commit -m "test: add HolidayService unit tests (parsing + cache logic)"
```

---

### Task 5: InvoicePDFService

**Files:**

- Read first: `app/Services/InvoicePDFService.php`
- Create: `tests/Unit/Services/InvoicePDFServiceTest.php`

InvoicePDFService genera PDFs. Tests deben verificar la lógica de datos, no el PDF binario.

- [ ] **Step 1: Leer el servicio**

```bash
docker compose exec app cat app/Services/InvoicePDFService.php
```

Test patterns:

- `test_build_invoice_data_returns_expected_structure()` — test del array de datos, no del PDF
- `test_generate_returns_fail_when_reservation_not_found()`
- `test_generate_returns_ok_with_valid_reservation()`

- [ ] **Step 2: Escribir y ejecutar**

```bash
git commit -m "test: add InvoicePDFService unit tests"
```

---

### Task 6: AnimalCareService

**Files:**

- Read first: `app/Services/AnimalCareService.php`
- Create: `tests/Unit/Services/AnimalCareServiceTest.php`

- [ ] **Step 1: Leer el servicio**

```bash
docker compose exec app cat app/Services/AnimalCareService.php
```

Test patterns:

- `test_create_care_log_returns_ok_with_valid_data()`
- `test_create_care_log_returns_fail_when_animal_not_found()`
- `test_update_health_returns_fail_for_invalid_status()`
- `test_get_dashboard_data_returns_array_with_expected_keys()`

- [ ] **Step 2: Escribir y ejecutar**

```bash
git commit -m "test: add AnimalCareService unit tests"
```

---

### Task 7: AdminService

**Files:**

- Read first: `app/Services/AdminService.php`
- Create: `tests/Unit/Services/AdminServiceTest.php`

- [ ] **Step 1: Leer el servicio**

```bash
docker compose exec app cat app/Services/AdminService.php
```

- [ ] **Step 2: Escribir tests del contrato principal**

```bash
git commit -m "test: add AdminService unit tests"
```

---

### Task 8: AllergenService, NavigationService (low priority)

Para cada uno:

1. `docker compose exec app cat app/Services/{ServiceName}.php`
2. Escribir tests mínimos del happy path y fail path
3. `git commit -m "test: add {ServiceName} unit tests"`

---

**Verification final del Plan 5:**

```bash
docker compose exec app vendor/bin/phpunit tests/Unit/Services/ --testdox --colors=always
```

Meta: 42/42 services con al menos 1 test file.

```bash
make test-coverage
# Abrir logs/coverage/index.html y verificar >= 80% en Service layer
```
