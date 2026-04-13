# Plan: Seguridad de Datos — Filtrado y Contratos de Capas (FASE 1)

**Fecha:** 13 de abril de 2026
**Estado:** Listo para implementar
**Dependencias:** FASE 0 completada (principios arquitectónicos decididos)
**Rama sugerida:** `feature/data-security-repositories`

---

## Contexto

`ReservationRepository` y `ProductRepository` ya tienen métodos de acceso diferenciado
(`findWithOperationalData()` y `findWithRecipe()` respectivamente), pero los tests de
integración solo verifican que `getSelectFields()` NO expone los campos sensibles.
Falta la prueba positiva: verificar que los métodos de acceso operativo SÍ retornan
los campos que deben retornar.

**`UserRepository`** ya está correctamente cubierto con tests positivos y negativos.

---

## Tareas

### TASK 1 — Test positivo: `ReservationRepository::findWithOperationalData()`

**Archivo:** `tests/Integration/Repositories/ReservationRepositorySecurityTest.php`

**Por qué falta:** El test `testFindWithOperationalDataMethodExists()` solo confirma que
el método existe, no que devuelve los campos operativos correctos. Si alguien cambia el
SELECT de `findWithOperationalData()` y elimina `tracker_id` o `payment_notes`, el test
no detecta la regresión.

**Qué añadir — constante y helper de seed:**

```php
private const int TEST_RESERVATION_ID = 79001;

private function seedTestReservation(): void
{
    self::$db->exec('SET FOREIGN_KEY_CHECKS = 0');
    self::$db->exec('
        INSERT INTO reservations (
            id, uuid, user_id, cafe_id, pass_product_id, pass_name, pass_unit_price,
            pass_duration_minutes, tracker_id, current_zone_id,
            reservation_date, reservation_time, guest_count, status,
            protocol_hygiene, protocol_briefing, protocol_shoes,
            payment_notes, final_amount, payment_status, payment_method,
            notes, created_at, updated_at
        ) VALUES (
            ' . self::TEST_RESERVATION_ID . ',
            UUID(),
            1, 1, NULL, "Pase test seguridad", 3000.00,
            90, "TRACKER-SEC-TEST-001", "zona-espera",
            CURDATE(), "10:00:00", 2, "confirmed",
            1, 0, 1,
            "Nota de pago de prueba de seguridad", 3000.00, "pending", "cash",
            NULL, NOW(), NOW()
        )
    ');
    self::$db->exec('SET FOREIGN_KEY_CHECKS = 1');
}
```

**Qué añadir — test positivo:**

```php
public function testFindWithOperationalDataReturnsOperationalFields(): void
{
    $this->seedTestReservation();

    $result = $this->repo->findWithOperationalData(self::TEST_RESERVATION_ID);

    $this->assertIsArray($result, 'findWithOperationalData() debe retornar un array para un ID existente');

    // Campos operativos que DEBEN estar presentes
    $this->assertArrayHasKey('tracker_id', $result);
    $this->assertArrayHasKey('current_zone_id', $result);
    $this->assertArrayHasKey('protocol_hygiene', $result);
    $this->assertArrayHasKey('protocol_briefing', $result);
    $this->assertArrayHasKey('protocol_shoes', $result);
    $this->assertArrayHasKey('payment_notes', $result);

    // Verificar valores sembrados
    $this->assertSame('TRACKER-SEC-TEST-001', $result['tracker_id']);
    $this->assertSame('zona-espera', $result['current_zone_id']);
    $this->assertSame('Nota de pago de prueba de seguridad', $result['payment_notes']);
    $this->assertSame(1, (int) $result['protocol_hygiene']);
    $this->assertSame(1, (int) $result['protocol_shoes']);
}
```

**Verificación:**

```bash
docker compose exec app php vendor/bin/phpunit tests/Integration/Repositories/ReservationRepositorySecurityTest.php --testdox
```

Esperado: 3 tests, 3 assertions OK.

---

### TASK 2 — Test positivo: `ProductRepository::findWithRecipe()`

**Archivo:** `tests/Integration/Repositories/ProductRepositorySecurityTest.php`

**Por qué falta:** `testFindWithRecipeMethodExists()` solo confirma que el método existe.
Si alguien elimina `recipe_steps` o `station` del SELECT de `findWithRecipe()`, el test
no lo detecta.

**Qué añadir — constante y helper de seed:**

```php
private const int TEST_PRODUCT_ID   = 79002;
private const int TEST_CATEGORY_ID  = 1;

private function seedTestProduct(): void
{
    self::$db->exec('SET FOREIGN_KEY_CHECKS = 0');
    self::$db->exec('
        INSERT INTO products (
            id, category_id, product_type, name, japanese_name, slug, description,
            price, station, prep_time, recipe_steps, ingredients_list, critical_check,
            is_active, sort_order, created_at, updated_at
        ) VALUES (
            ' . self::TEST_PRODUCT_ID . ',
            ' . self::TEST_CATEGORY_ID . ',
            "item",
            "Matcha Latte Test",
            "抹茶ラテ テスト",
            "matcha-latte-test-79002",
            "Versión de prueba para test de seguridad",
            750.00,
            "bar",
            5,
            "1. Tamizar matcha\\n2. Añadir agua caliente\\n3. Batir\\n4. Añadir leche vaporizada",
            \'[{"nombre":"matcha","gramos":3},{"nombre":"leche","ml":200}]\',
            "Temperatura agua: 80°C ±5°C (no hervir)",
            1, 99, NOW(), NOW()
        )
    ');
    self::$db->exec('SET FOREIGN_KEY_CHECKS = 1');
}
```

**Qué añadir — test positivo:**

```php
public function testFindWithRecipeReturnsKitchenFields(): void
{
    $this->seedTestProduct();

    $result = $this->repo->findWithRecipe(self::TEST_PRODUCT_ID);

    $this->assertIsArray($result, 'findWithRecipe() debe retornar un array para un ID existente');

    // Campos de cocina/KDS que DEBEN estar presentes
    $this->assertArrayHasKey('station', $result);
    $this->assertArrayHasKey('recipe_steps', $result);
    $this->assertArrayHasKey('ingredients_list', $result);
    $this->assertArrayHasKey('critical_check', $result);

    // Verificar valores sembrados
    $this->assertSame('bar', $result['station']);
    $this->assertStringContainsString('Tamizar matcha', $result['recipe_steps']);
    $this->assertSame('Temperatura agua: 80°C ±5°C (no hervir)', $result['critical_check']);
    // ingredients_list puede volver como JSON string o array según el driver
    $this->assertNotEmpty($result['ingredients_list']);
}
```

**Verificación:**

```bash
docker compose exec app php vendor/bin/phpunit tests/Integration/Repositories/ProductRepositorySecurityTest.php --testdox
```

Esperado: 3 tests, 3 assertions OK.

---

### TASK 3 — Verificación global de tests de seguridad de repositorios

**Comando:**

```bash
docker compose exec app php vendor/bin/phpunit tests/Integration/Repositories/ --testdox
```

**Resultado esperado:**

```
ReservationRepositorySecurityTest
 ✔ Get select fields excludes internal operational fields
 ✔ Find with operational data method exists
 ✔ Find with operational data returns operational fields

ProductRepositorySecurityTest
 ✔ Get select fields excludes kitchen operational fields
 ✔ Find with recipe method exists
 ✔ Find with recipe returns kitchen fields

UserRepositorySecurityTest
 ✔ ...
```

---

## Notas de implementación

- `SET FOREIGN_KEY_CHECKS = 0` es necesario porque las reservas tienen FK a `users` y `cafes`,
  y los productos tienen FK a `menu_categories`. En el DB de test, no se garantiza que existan
  los IDs de FK. La transacción `tearDown` hace rollback automático limpiando el seed.
- IDs de test: usar rangos altos (79001+) para evitar colisiones con seeds existentes.
- No modificar `BaseIntegrationTest.php` — la infraestructura de transacciones ya es correcta.
