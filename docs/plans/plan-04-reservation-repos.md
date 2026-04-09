# Plan 4: ReservationService Catalog Methods → Repositories

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development.

**Goal:** Mover los métodos de ReservationService que usan `$this->db` directamente (`getAvailableCafesForReservation`, `getAvailablePassesForReservation`, `validateCafeExists`, `validatePassExists`, `enrichCartItems`) a sus repositorios correspondientes.

**Architecture:** CafeRepository recibe los métodos de cafés, ProductRepository los de productos. ReservationService delega. TDD: tests nuevos sobre repositorios, tests de regresión en ReservationService.

**Tech Stack:** PHP 8.4, PDO, AbstractRepository pattern

---

### Task 1: Mover métodos de cafés a CafeRepository

**Files:**

- Modify: `app/Repositories/CafeRepository.php` — añadir `findAvailableForReservation()`, `findById()` ya existe
- Modify: `app/Repositories/Contracts/CafeRepositoryInterface.php` — añadir métodos nuevos
- Create: `tests/Unit/Repositories/CafeRepositoryTest.php` si no existe

- [ ] **Step 1: Leer CafeRepository actual**

```bash
docker compose exec app cat app/Repositories/CafeRepository.php
docker compose exec app cat app/Repositories/Contracts/CafeRepositoryInterface.php
```

- [ ] **Step 2: Escribir tests para el nuevo método**

```php
// tests/Unit/Repositories/CafeRepositoryTest.php (añadir método si ya existe)
<?php
/**
 * ¿Qué pruebas aquí?
 * Verifica que CafeRepository::findAvailableForReservation() retorna solo cafés activos con reservas.
 *
 * ¿Qué me quieres demostrar?
 * Que el método aplica los filtros has_reservations=1 AND is_active=1.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se cambia la query para incluir cafés inactivos o sin reservas.
 */
declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Repositories\CafeRepository;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

final class CafeRepositoryTest extends TestCase
{
    public function test_find_available_for_reservation_executes_correct_query(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('fetchAll')->willReturn([
            ['id' => 1, 'name' => 'Café Neko', 'slug' => 'neko', 'is_active' => 1, 'has_reservations' => 1],
        ]);

        $db = $this->createMock(PDO::class);
        $db->expects($this->once())
           ->method('query')
           ->with($this->stringContains('has_reservations = 1'))
           ->willReturn($stmt);

        $repo = new CafeRepository($db);
        $result = $repo->findAvailableForReservation();

        $this->assertCount(1, $result);
        $this->assertSame('Café Neko', $result[0]['name']);
    }

    public function test_validate_exists_and_active_returns_true_when_found(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('fetch')->willReturn(['id' => 1]);

        $db = $this->createMock(PDO::class);
        $db->method('prepare')->willReturn($stmt);
        $stmt->method('execute')->willReturn(true);

        $repo = new CafeRepository($db);
        $this->assertTrue($repo->existsAndActive(1));
    }
}
```

- [ ] **Step 3: Añadir métodos a CafeRepository**

```php
// app/Repositories/CafeRepository.php

/**
 * Retorna cafés disponibles para reserva.
 * Replica la query que estaba en ReservationService::getAvailableCafesForReservation().
 */
public function findAvailableForReservation(): array
{
    $stmt = $this->db->query(
        'SELECT id, name, slug, location, category, animal_type, price_per_hour,
                opening_time, closing_time, capacity_max, image_url,
                latitude, longitude, timezone
         FROM cafes WHERE has_reservations = 1 AND is_active = 1'
    );

    return $stmt->fetchAll(\PDO::FETCH_ASSOC);
}

/**
 * Retorna cafés indexados por ID para acceso O(1).
 * @return array<int, array>
 */
public function findAvailableForReservationById(): array
{
    $cafes = $this->findAvailableForReservation();
    $indexed = [];
    foreach ($cafes as $cafe) {
        $indexed[(int) $cafe['id']] = $cafe;
    }
    return $indexed;
}

/**
 * Verifica si un café existe y está activo.
 */
public function existsAndActive(int $cafeId): bool
{
    $stmt = $this->db->prepare('SELECT id FROM cafes WHERE id = :id AND is_active = 1');
    $stmt->execute(['id' => $cafeId]);
    return $stmt->fetch() !== false;
}
```

- [ ] **Step 4: Añadir a CafeRepositoryInterface**

```php
// app/Repositories/Contracts/CafeRepositoryInterface.php

public function findAvailableForReservation(): array;
public function findAvailableForReservationById(): array;
public function existsAndActive(int $cafeId): bool;
```

- [ ] **Step 5: Ejecutar tests**

```bash
docker compose exec app vendor/bin/phpunit tests/Unit/Repositories/CafeRepositoryTest.php --colors=always
```

- [ ] **Step 6: Commit**

```bash
git add app/Repositories/CafeRepository.php app/Repositories/Contracts/CafeRepositoryInterface.php tests/Unit/Repositories/CafeRepositoryTest.php
git commit -m "feat: add findAvailableForReservation() and existsAndActive() to CafeRepository"
```

---

### Task 2: Mover métodos de productos a ProductRepository

**Files:**

- Modify: `app/Repositories/ProductRepository.php` — añadir `findAvailablePasses()`, `findPassById()`, `findItemsByIds()`, `existsAndActivePass()`
- Modify: `app/Repositories/Contracts/ProductRepositoryInterface.php`

- [ ] **Step 1: Leer ProductRepository actual**

```bash
docker compose exec app cat app/Repositories/ProductRepository.php
```

- [ ] **Step 2: Escribir test**

```php
// tests/Unit/Repositories/ProductRepositoryTest.php (añadir)

public function test_find_available_passes_filters_by_product_type(): void
{
    $stmt = $this->createStub(\PDOStatement::class);
    $stmt->method('fetchAll')->willReturn([
        ['id' => 1, 'name' => 'Pase Básico', 'product_type' => 'pass', 'is_active' => 1],
    ]);

    $db = $this->createMock(\PDO::class);
    $db->method('query')
       ->with($this->stringContains("product_type = 'pass'"))
       ->willReturn($stmt);

    $repo = new \App\Repositories\ProductRepository($db);
    $result = $repo->findAvailablePasses();

    $this->assertCount(1, $result);
    $this->assertSame('Pase Básico', $result[0]['name']);
}

public function test_find_items_by_ids_uses_parameterized_query(): void
{
    $stmt = $this->createStub(\PDOStatement::class);
    $stmt->method('fetchAll')->willReturn([]);

    $db = $this->createMock(\PDO::class);
    $db->expects($this->once())
       ->method('prepare')
       ->with($this->stringContains('IN ('))
       ->willReturn($stmt);
    $stmt->method('execute')->willReturn(true);

    $repo = new \App\Repositories\ProductRepository($db);
    $result = $repo->findItemsByIds([1, 2, 3]);

    $this->assertSame([], $result);
}
```

- [ ] **Step 3: Añadir métodos a ProductRepository**

```php
// app/Repositories/ProductRepository.php

/**
 * Retorna todos los pases activos disponibles para reserva.
 */
public function findAvailablePasses(): array
{
    $stmt = $this->db->query(
        "SELECT id, name, japanese_name, description, price,
                duration_minutes, min_pax, max_pax,
                target_cafe_types, target_animal_types,
                attributes, image_url
         FROM products
         WHERE product_type = 'pass' AND is_active = 1
         ORDER BY price, duration_minutes"
    );
    return $stmt->fetchAll(\PDO::FETCH_ASSOC);
}

/**
 * Verifica si un pase existe y está activo.
 */
public function existsAndActivePass(int $productId): bool
{
    $stmt = $this->db->prepare(
        "SELECT id FROM products WHERE id = :id AND product_type = 'pass' AND is_active = 1"
    );
    $stmt->execute(['id' => $productId]);
    return $stmt->fetch() !== false;
}

/**
 * Obtiene items (no pases) por array de IDs. Usado para enriquecer carrito.
 * @param int[] $ids
 * @return array
 */
public function findItemsByIds(array $ids): array
{
    if (empty($ids)) {
        return [];
    }

    $ids         = \array_map('intval', $ids);
    $placeholders = \implode(',', \array_fill(0, \count($ids), '?'));

    $stmt = $this->db->prepare(
        "SELECT id, name, price FROM products
         WHERE id IN ($placeholders) AND product_type = 'item' AND is_active = 1"
    );
    $stmt->execute($ids);

    return $stmt->fetchAll(\PDO::FETCH_ASSOC);
}
```

- [ ] **Step 4: Ejecutar tests**

```bash
docker compose exec app vendor/bin/phpunit tests/Unit/Repositories/ProductRepositoryTest.php --colors=always
```

- [ ] **Step 5: Commit**

```bash
git add app/Repositories/ProductRepository.php app/Repositories/Contracts/ProductRepositoryInterface.php tests/Unit/Repositories/ProductRepositoryTest.php
git commit -m "feat: add catalog query methods to ProductRepository"
```

---

### Task 3: Actualizar ReservationService para delegar a repos

**Files:**

- Modify: `app/Services/ReservationService.php` — reemplazar métodos con DB directo

- [ ] **Step 1: Escribir test de regresión en ReservationServiceTest**

```php
// tests/Unit/Services/ReservationServiceTest.php — buscar y añadir:

public function test_get_available_cafes_for_reservation_delegates_to_cafe_repo(): void
{
    $this->cafeRepo
        ->expects($this->once())
        ->method('findAvailableForReservation')
        ->willReturn([['id' => 1, 'name' => 'Neko']]);

    // cafeRepo es mock de CafeRepositoryInterface
    $cafes = $this->service->getAvailableCafesForReservation();

    $this->assertCount(1, $cafes);
}

public function test_enrich_cart_items_delegates_to_product_repo(): void
{
    $this->productRepo
        ->expects($this->once())
        ->method('findItemsByIds')
        ->with([1, 2])
        ->willReturn([['id' => 1, 'name' => 'Matcha', 'price' => 500]]);

    $result = $this->service->enrichCartItems([1 => 2, 2 => 1]);

    $this->assertCount(1, $result);
}
```

- [ ] **Step 2: Ejecutar para verificar fallo**

```bash
docker compose exec app vendor/bin/phpunit tests/Unit/Services/ReservationServiceTest.php --filter=test_get_available_cafes --colors=always
```

Esperado: FAIL — el método actualmente usa $this->db->query directamente.

- [ ] **Step 3: Reemplazar métodos en ReservationService**

```php
// app/Services/ReservationService.php

// ELIMINAR:
public function getAvailableCafesForReservation(): array
{
    $stmt = $this->db->query('SELECT id, name, slug, ...');
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// REEMPLAZAR CON:
public function getAvailableCafesForReservation(): array
{
    return $this->cafeRepo->findAvailableForReservation();
}

// REEMPLAZAR:
public function getAvailableCafesById(): array
{
    return $this->cafeRepo->findAvailableForReservationById();
}

// REEMPLAZAR:
public function getAvailablePassesForReservation(): array
{
    return $this->productRepo->findAvailablePasses();
}

// REEMPLAZAR:
public function validateCafeExists(int $cafeId): bool
{
    return $this->cafeRepo->existsAndActive($cafeId);
}

// REEMPLAZAR:
public function validatePassExists(int $passProductId): bool
{
    return $this->productRepo->existsAndActivePass($passProductId);
}

// REEMPLAZAR:
public function enrichCartItems(array $cartItems): array
{
    if (empty($cartItems)) {
        return [];
    }
    $ids = \array_map('intval', \array_keys($cartItems));
    return $this->productRepo->findItemsByIds($ids);
}
```

- [ ] **Step 4: Verificar que $this->db ya no se usa para queries directos en ReservationService**

```bash
docker compose exec app grep -n '\$this->db->' app/Services/ReservationService.php
```

Esperado: solo en el constructor (asignación), no en métodos de negocio.

- [ ] **Step 5: Ejecutar tests**

```bash
docker compose exec app vendor/bin/phpunit tests/Unit/Services/ReservationServiceTest.php --colors=always
make test-integration
```

- [ ] **Step 6: Commit**

```bash
git add app/Services/ReservationService.php
git commit -m "refactor: delegate catalog DB queries in ReservationService to repositories"
```

---

**Verification final del Plan 4:**

```bash
make ci
```
