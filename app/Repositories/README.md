# Capa Repository - Komorebi Café

## 📚 Índice

- [¿Qué es el patrón Repository?](#qué-es-el-patrón-repository)
- [Beneficios](#beneficios)
- [Estructura](#estructura)
- [Uso básico](#uso-básico)
- [Testing](#testing)
- [Migración desde modelos](#migración-desde-modelos)
- [Buenas prácticas](#buenas-prácticas)

---

## ¿Qué es el patrón Repository?

El **patrón Repository** actúa como una capa de abstracción entre la lógica de negocio (servicios) y la capa de acceso a datos (base de datos). Encapsula las operaciones CRUD y queries complejas, proporcionando una API limpia y expresiva.

### Antes (sin Repository)

```php
// En el servicio: Acceso directo al modelo o PDO
final class ReservationService
{
    private Reservation $model;

    public function getActiveReservations(int $userId): array
    {
        // Lógica SQL mezclada con lógica de negocio
        return $this->model->findByUser($userId, 'active');
    }
}
```

### Ahora (con Repository)

```php
// En el servicio: Usa repositorio con métodos expresivos
final class ReservationService
{
    private ReservationRepository $repo;

    public function getActiveReservations(int $userId): array
    {
        // Método expresivo y testeables
        return $this->repo->findActiveByUser($userId);
    }
}
```

---

## Beneficios

### 1. **Separación de Responsabilidades**

- Los servicios se enfocan en lógica de negocio
- Los repositorios manejan queries y persistencia
- Los modelos se simplifican (DTOs o Active Records simples)

### 2. **Testabilidad**

```php
// Test usando mocks (sin BD real)
$repoMock = $this->createMock(ReservationRepository::class);
$repoMock->method('findActiveByUser')->willReturn([/* datos fake */]);

$service = new ReservationService(repo: $repoMock);
$result = $service->getActiveReservations(1);
```

### 3. **Queries Expresivas y Reutilizables**

```php
// Métodos con nombres claros
$repo->findActiveByUser($userId);
$repo->findByCafeAndDate($cafeId, $date);
$repo->isSlotAvailable($cafeId, $date, $time);
$repo->findTopRated(5);
```

### 4. **DRY (Don't Repeat Yourself)**

- Queries complejas en un solo lugar
- Evita duplicación de SQL
- Cambios en un punto afectan toda la app

### 5. **Migración de DB más fácil**

- Si cambias MySQL → PostgreSQL, solo modificas repositorios
- Los servicios no necesitan cambios

---

## Estructura

```
app/
└── Repositories/
    ├── RepositoryInterface.php          # Contrato base
    ├── AbstractRepository.php           # CRUD genérico
    ├── ReservationRepository.php        # Reservas
    ├── UserRepository.php               # Usuarios
    ├── CafeRepository.php               # Cafés
    └── ProductRepository.php            # Productos/Pases
```

### Jerarquía

```
RepositoryInterface (interface)
    ↓ implementa
AbstractRepository (abstract class)
    ↓ extiende
ReservationRepository (concrete class)
```

---

## Uso Básico

### 1. Inyectar en Servicios

```php
<?php

namespace App\Services;

use App\Repositories\ReservationRepository;
use App\Repositories\CafeRepository;

final class ReservationService
{
    public function __construct(
        private readonly ReservationRepository $reservationRepo,
        private readonly CafeRepository $cafeRepo,
    ) {}

    public function getAvailableSlots(int $cafeId, string $date): array
    {
        // Verificar que el café existe
        $cafe = $this->cafeRepo->findById($cafeId);

        if (!$cafe || !$cafe['is_active']) {
            return [];
        }

        // Obtener reservas del día
        $reservations = $this->reservationRepo->findByCafeAndDate($cafeId, $date);

        // Procesar disponibilidad (lógica de negocio)
        return $this->calculateAvailableSlots($cafe, $reservations);
    }
}
```

### 2. Usar en Controladores

```php
<?php

namespace App\Http\Controllers;

use App\Repositories\CafeRepository;

final class CafeController
{
    public function __construct(
        private readonly CafeRepository $cafeRepo
    ) {}

    public function index(): void
    {
        // Obtener cafés activos
        $cafes = $this->cafeRepo->findActive();

        // Obtener top 5 mejor valorados
        $topRated = $this->cafeRepo->findTopRated(5);

        View::render('cafes/index', compact('cafes', 'topRated'));
    }
}
```

---

## Testing

### Test Unitario (con Mocks)

```php
<?php

use App\Repositories\ReservationRepository;
use PHPUnit\Framework\TestCase;

final class ReservationServiceTest extends TestCase
{
    public function testCancelReservationSuccess(): void
    {
        // Arrange: Mock del repositorio
        $repoMock = $this->createMock(ReservationRepository::class);
        $repoMock
            ->expects($this->once())
            ->method('cancel')
            ->with(1, 5)
            ->willReturn(true);

        $service = new ReservationService($repoMock);

        // Act
        $result = $service->cancel(1, 5);

        // Assert
        $this->assertTrue($result);
    }
}
```

### Test de Integración (con BD real)

```php
<?php

use App\Repositories\ReservationRepository;
use PHPUnit\Framework\TestCase;

final class ReservationRepositoryIntegrationTest extends TestCase
{
    private ReservationRepository $repo;

    protected function setUp(): void
    {
        // Conexión a BD de test
        $pdo = new PDO('mysql:host=127.0.0.1;dbname=komorebi_test', 'root', 'root');
        $pdo->beginTransaction(); // Para rollback después

        $this->repo = new ReservationRepository($pdo);
    }

    protected function tearDown(): void
    {
        // Rollback de cambios
        $this->repo->getPDO()->rollBack();
    }

    public function testFindByIdReturnsRealData(): void
    {
        $reservation = $this->repo->findById(1);

        $this->assertIsArray($reservation);
        $this->assertArrayHasKey('id', $reservation);
    }
}
```

---

## Migración desde Modelos

### Estrategia de Migración Gradual

1. **Crear repositorios** (✅ Ya hecho)
2. **Inyectar en servicios junto con modelos** (migración híbrida)
3. **Reemplazar llamadas modelo → repositorio una a una**
4. **Eliminar modelos legacy cuando todo esté migrado**

### Ejemplo de Migración Híbrida

```php
final class ReservationService
{
    private ReservationRepository $repo;
    private Reservation $model; // Mantener temporalmente

    public function __construct(
        ReservationRepository $repo,
        Reservation $model
    ) {
        $this->repo = $repo;
        $this->model = $model; // Legacy
    }

    // NUEVO: Ya migrado
    public function cancel(int $id, int $userId): bool
    {
        return $this->repo->cancel($id, $userId);
    }

    // LEGACY: Todavía usa modelo (TODO: migrar)
    public function getUpcoming(int $userId): array
    {
        return $this->model->findUpcomingByUser($userId);
    }
}
```

---

## Buenas Prácticas

### ✅ DO (Hacer)

1. **Métodos expresivos**

   ```php
   findActiveByUser(int $userId)
   findByCafeAndDate(int $cafeId, string $date)
   isSlotAvailable(int $cafeId, string $date, string $time)
   ```

2. **Evitar SELECT ***

   ```php
   protected function getSelectFields(): array
   {
       return ['id', 'name', 'email', 'created_at']; // Explícito
   }
   ```

3. **Prepared statements siempre**

   ```php
   $stmt = $this->db->prepare('SELECT * FROM users WHERE id = :id');
   $stmt->execute(['id' => $id]);
   ```

4. **Type hints estrictos**

   ```php
   public function findById(int $id): ?array
   public function findAll(): array
   public function exists(int $id): bool
   ```

5. **Documentación**

   ```php
   /**
    * Buscar reservas activas de un usuario.
    *
    * @param int $userId ID del usuario
    * @return array Lista de reservas con status pending|confirmed|active
    */
   public function findActiveByUser(int $userId): array
   ```

### ❌ DON'T (No hacer)

1. **Lógica de negocio en repositorios**

   ```php
   // ❌ MAL: Lógica de negocio mezclada
   public function cancelIfNotPaid(int $id): bool
   {
       $reservation = $this->findById($id);
       if ($reservation['payment_status'] === 'unpaid') {
           // Enviar email, actualizar estado, logs...
           // Esto es responsabilidad del SERVICIO
       }
   }

   // ✅ BIEN: Solo acceso a datos
   public function cancel(int $id, int $userId): bool
   {
       // Solo query y persistencia
       return $this->update($id, ['status' => 'cancelled']);
   }
   ```

2. **Retornar entidades de frameworks**

   ```php
   // ❌ MAL: Acopla a Eloquent/Doctrine
   public function findById(int $id): ?User

   // ✅ BIEN: Arrays o DTOs simples
   public function findById(int $id): ?array
   ```

3. **Queries duplicadas**

   ```php
   // ❌ MAL: Query repetida en múltiples métodos
   public function countActive(): int
   {
       $stmt = $this->db->query('SELECT COUNT(*) FROM users WHERE is_active = 1');
       return $stmt->fetchColumn();
   }

   public function findActive(): array
   {
       $stmt = $this->db->query('SELECT * FROM users WHERE is_active = 1');
       return $stmt->fetchAll();
   }

   // ✅ BIEN: Extraer query base
   private function getActiveCondition(): string
   {
       return 'is_active = 1 AND deleted_at IS NULL';
   }
   ```

---

## Próximos Pasos

1. **Migrar servicios restantes**: `AuthService`, `MenuService`, etc.
2. **Crear más tests** para repositorios existentes
3. **Deprecar modelos legacy** gradualmente
4. **Añadir DTOs** para type safety (Prioridad #3)
5. **Documentar queries complejas** con ejemplos

---

## Recursos

- [Martin Fowler - Repository Pattern](https://martinfowler.com/eaaCatalog/repository.html)
- [Domain-Driven Design](https://leanpub.com/ddd-in-php)
- [Testing Strategies](https://phpunit.readthedocs.io/)

---

**Actualizado**: 5 de febrero de 2026
**Versión**: 1.0
