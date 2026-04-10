# Plan 4 — ReservationService: Eliminar ?PDO y patrón `?? new`

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps marked ✅ son confirmaciones de estado — NO re-implementar lo que ya está hecho.

**Goal:** Erradicar el patrón `?Dep = null` + `$this->dep = $dep ?? new ConcreteClass()` de `ReservationService`. El constructor acepta 9 parámetros todos nullable con fallbacks inline, haciendo que el DI container nunca inyecte nada en producción y los tests tienen que sortear concreciones en lugar de recibir interfaces limpias. El plan también registra las interfaces en el container y actualiza `ReservationServiceProvider`.

**Architecture:** `ReservationService` ya tiene las propiedades tipadas como interfaces (`ReservationRepositoryInterface`, `CafeRepositoryInterface`, `ProductRepositoryInterface`, `AnimalRepositoryInterface`, `TimeSlotRepositoryInterface`, `InvoicePDFServiceInterface`, `EmailServiceInterface`). El código real está correcto — el problema es solo el constructor y la ausencia de bindings en el container. Las implementaciones concretas para cada interface ya existen y están completas.

**Tech Stack:** PHP 8.4, DI Container singleton, PSR-14 EventDispatcher, PHPUnit 12 `createStub()`.

---

## Estado confirmado por auditoría (NO re-implementar)

| Item | Estado | Evidencia |
|---|---|---|
| `CafeRepository` con 13 métodos | ✅ | `app/Repositories/CafeRepository.php` — `findAvailableForReservation()`, `existsAndActive()`, `getAvailableCapacity()` presentes |
| `ProductRepository` con 11+ métodos | ✅ | `app/Repositories/ProductRepository.php` — `findAvailablePasses()`, `findItemsByIds()`, `existsAndActivePass()`, `hasStock()` presentes |
| `CafeRepositoryInterface` 14 métodos | ✅ | `app/Repositories/Contracts/CafeRepositoryInterface.php` |
| `ProductRepositoryInterface` 8 métodos | ✅ | `app/Repositories/Contracts/ProductRepositoryInterface.php` |
| `ReservationRepositoryInterface` | ✅ | `app/Repositories/Contracts/ReservationRepositoryInterface.php` |
| `AnimalRepositoryInterface` | ✅ | `app/Repositories/Contracts/AnimalRepositoryInterface.php` |
| `TimeSlotRepositoryInterface` | ✅ | `app/Repositories/Contracts/TimeSlotRepositoryInterface.php` |
| `InvoicePDFServiceInterface` | ✅ | `app/Services/Contracts/InvoicePDFServiceInterface.php` |
| `EmailServiceInterface` | ✅ | `app/Services/Contracts/EmailServiceInterface.php` |
| `CafeRepositoryTest` 10 tests | ✅ | `tests/Unit/Repositories/CafeRepositoryTest.php` — 300 líneas |
| `ProductRepositoryTest` 5 tests | ✅ | `tests/Unit/Repositories/ProductRepositoryTest.php` — 145 líneas |
| `ReservationService` sin SQL directo | ✅ | grep `$this->db->prepare` en `ReservationService.php` → 0 resultados |

---

## Mapa de archivos afectados

| Grupo | Archivo | Acción |
|---|---|---|
| A | `app/Services/ReservationService.php` | Eliminar `?PDO` + todos los `?? new` en constructor |
| B | `app/Providers/ReservationServiceProvider.php` | Añadir bindings de todas las interfaces de repos y servicios |
| B | `bootstrap/container.php` | Verificar que `CafeRepositoryInterface` ya tiene binding (línea ~118) |
| C | `tests/Unit/Services/ReservationServiceTest.php` | Actualizar `setUp()` para pasar stubs en lugar de `new ReservationService()` |

---

## Grupo A — Limpiar constructor de `ReservationService`

### A1: Leer el constructor completo y documentar el antes

**Problema actual (L51–L75):**

```php
// ANTES — patrón roto:
public function __construct(
    ?PDO $db = null,
    ?ReservationRepositoryInterface $reservationRepo = null,
    ?CafeRepositoryInterface $cafeRepo = null,
    ?ProductRepositoryInterface $productRepo = null,
    ?AnimalRepositoryInterface $animalRepo = null,
    ?TimeSlotRepositoryInterface $timeSlotRepo = null,
    ?InvoicePDFServiceInterface $invoiceService = null,
    ?EmailServiceInterface $emailService = null,
    ?EventDispatcherInterface $eventDispatcher = null
) {
    $this->db = $db ?? Database::getConnection();
    $this->reservationRepo = $reservationRepo ?? new ReservationRepository($this->db);
    $this->cafeRepo = $cafeRepo ?? new CafeRepository($this->db);
    $this->productRepo = $productRepo ?? new ProductRepository($this->db);
    $this->animalRepo = $animalRepo ?? new AnimalRepository($this->db);
    $this->timeSlotRepo = $timeSlotRepo ?? new TimeSlotRepository($this->db);
    $this->invoiceService = $invoiceService ?? new InvoicePDFService();
    $this->emailService = $emailService ?? new EmailService();
    $this->eventDispatcher = $eventDispatcher;
}
```

**Tareas:**

- [ ] Leer `app/Services/ReservationService.php` L1–80 — confirmar imports y propiedades privadas
- [ ] Verificar que `private PDO $db` ya no se usa directamente después de la refactorización (si no se usa, se puede eliminar la propiedad también)
- [ ] Ejecutar `grep -n "\$this->db"` en `ReservationService.php` — listar todos los usos directos de PDO

---

### A2: Reescribir el constructor con DI explícito

**Condición:** Ejecutar tras confirmar que `$this->db` ya no se usa en métodos (solo en constructor para fallback de repos).

```php
// DESPUÉS — constructor limpio:
public function __construct(
    ReservationRepositoryInterface $reservationRepo,
    CafeRepositoryInterface $cafeRepo,
    ProductRepositoryInterface $productRepo,
    AnimalRepositoryInterface $animalRepo,
    TimeSlotRepositoryInterface $timeSlotRepo,
    InvoicePDFServiceInterface $invoiceService,
    EmailServiceInterface $emailService,
    ?EventDispatcherInterface $eventDispatcher = null
) {
    $this->reservationRepo = $reservationRepo;
    $this->cafeRepo = $cafeRepo;
    $this->productRepo = $productRepo;
    $this->animalRepo = $animalRepo;
    $this->timeSlotRepo = $timeSlotRepo;
    $this->invoiceService = $invoiceService;
    $this->emailService = $emailService;
    $this->eventDispatcher = $eventDispatcher;
}
```

**Tareas:**

- [ ] Editar `app/Services/ReservationService.php`:
  - Eliminar `private PDO $db;` de las propiedades (si ya no se usa en métodos)
  - Eliminar el import `use PDO;` (si ya no se usa en métodos)
  - Eliminar el import `use App\Core\Database;` (si ya no se usa en métodos)
  - Reemplazar el constructor entero con la versión limpia de arriba
- [ ] Ejecutar `make phpstan` — debe pasar sin nuevos errores
- [ ] Ejecutar `make test-unit` — los tests rotos son esperados hasta que se complete el Grupo C

---

## Grupo B — Registrar interfaces en `ReservationServiceProvider`

### B1: Auditar qué ya está registrado

- [ ] Leer `app/Providers/ReservationServiceProvider.php` completo — actualmente solo registra `Reservation`, `TimeSlot`, `Waitlist`, `ReservationTimeSlotService`
- [ ] Leer `bootstrap/container.php` L85–140 — verificar si `CafeRepositoryInterface` ya tiene binding (hay evidencia de que sí, en L118)
- [ ] Ejecutar `grep -n "CafeRepositoryInterface\|ProductRepositoryInterface\|AnimalRepositoryInterface\|TimeSlotRepositoryInterface\|ReservationRepositoryInterface" bootstrap/container.php` — listar bindings existentes

---

### B2: Añadir bindings faltantes en `ReservationServiceProvider`

**Añadir en `register()` las interfaces que no estén ya en `bootstrap/container.php`:**

```php
use App\Repositories\Contracts\ReservationRepositoryInterface;
use App\Repositories\Contracts\CafeRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\AnimalRepositoryInterface;
use App\Repositories\Contracts\TimeSlotRepositoryInterface;
use App\Repositories\Contracts\InvoicePDFServiceInterface;
use App\Repositories\Contracts\EmailServiceInterface;
use App\Repositories\ReservationRepository;
use App\Repositories\CafeRepository;
use App\Repositories\ProductRepository;
use App\Repositories\AnimalRepository;
use App\Repositories\TimeSlotRepository;
use App\Services\Contracts\InvoicePDFServiceInterface;
use App\Services\Contracts\EmailServiceInterface;
use App\Services\InvoicePDFService;
use App\Services\EmailService;
use App\Services\ReservationService;

// En register():
Container::singleton(ReservationRepositoryInterface::class,
    fn() => new ReservationRepository(Database::getConnection())
);
// Solo añadir CafeRepositoryInterface si NO existe ya en bootstrap/container.php
// Container::singleton(CafeRepositoryInterface::class,
//     fn() => new CafeRepository(Database::getConnection())
// );
Container::singleton(ProductRepositoryInterface::class,
    fn() => new ProductRepository(Database::getConnection())
);
Container::singleton(AnimalRepositoryInterface::class,
    fn() => new AnimalRepository(Database::getConnection())
);
Container::singleton(TimeSlotRepositoryInterface::class,
    fn() => new TimeSlotRepository(Database::getConnection())
);
Container::singleton(InvoicePDFServiceInterface::class,
    fn() => new InvoicePDFService()
);
Container::singleton(EmailServiceInterface::class,
    fn() => new EmailService(Container::make(PDO::class))
);
Container::singleton(ReservationService::class, fn() => new ReservationService(
    Container::make(ReservationRepositoryInterface::class),
    Container::make(CafeRepositoryInterface::class),
    Container::make(ProductRepositoryInterface::class),
    Container::make(AnimalRepositoryInterface::class),
    Container::make(TimeSlotRepositoryInterface::class),
    Container::make(InvoicePDFServiceInterface::class),
    Container::make(EmailServiceInterface::class),
    Container::make(EventDispatcherInterface::class)
));
```

**Tareas:**

- [ ] Editar `app/Providers/ReservationServiceProvider.php` — añadir solo las interfaces que no existan en `bootstrap/container.php`
- [ ] Añadir `ReservationService::class` singleton al provider
- [ ] No duplicar bindings de `CafeRepositoryInterface` si ya existe en `bootstrap/container.php`
- [ ] Ejecutar `make phpstan` — debe pasar sin nuevos errores

---

## Grupo C — Actualizar `ReservationServiceTest`

### C1: Auditar el `setUp()` actual

- [ ] Leer `tests/Unit/Services/ReservationServiceTest.php` completo — identificar cómo se instancia `ReservationService` actualmente
- [ ] Verificar si usa `new ReservationService()` sin argumentos (que funciona hoy por los fallbacks nullable)

---

### C2: Actualizar `setUp()` con stubs explícitos

```php
// setUp() DESPUÉS:
protected function setUp(): void
{
    parent::setUp();

    $this->reservationRepo = $this->createStub(ReservationRepositoryInterface::class);
    $this->cafeRepo        = $this->createStub(CafeRepositoryInterface::class);
    $this->productRepo     = $this->createStub(ProductRepositoryInterface::class);
    $this->animalRepo      = $this->createStub(AnimalRepositoryInterface::class);
    $this->timeSlotRepo    = $this->createStub(TimeSlotRepositoryInterface::class);
    $this->invoiceService  = $this->createStub(InvoicePDFServiceInterface::class);
    $this->emailService    = $this->createStub(EmailServiceInterface::class);

    $this->service = new ReservationService(
        $this->reservationRepo,
        $this->cafeRepo,
        $this->productRepo,
        $this->animalRepo,
        $this->timeSlotRepo,
        $this->invoiceService,
        $this->emailService,
    );
}
```

**Tareas:**

- [ ] Editar `tests/Unit/Services/ReservationServiceTest.php` — reemplazar `setUp()` con la versión de arriba
- [ ] Añadir como propiedades privadas del test los stubs necesarios para mockear en tests individuales
- [ ] Ejecutar `make test-unit` — debe pasar en verde
- [ ] Ejecutar `make phpstan` — debe pasar sin nuevos errores

---

## Comandos de verificación

```bash
# Confirmar que $this->db ya no se usa en métodos de ReservationService
grep -n "\$this->db" app/Services/ReservationService.php

# Confirmar que los bindings de interfaces están en el container
grep -n "RepositoryInterface\|ServiceInterface" app/Providers/ReservationServiceProvider.php

# Confirmar que no hay ?? new en ReservationService
grep -n "?? new" app/Services/ReservationService.php

# Análisis estático
docker compose exec app vendor/bin/phpstan analyse app/Services/ReservationService.php --level=5

# Tests unitarios
docker compose exec app vendor/bin/phpunit tests/Unit/Services/ReservationServiceTest.php --testdox

# Suite completa
make test-unit
make phpstan
```

---

## Commits sugeridos

```
fix: eliminar patrón ??new en ReservationService constructor
feat: registrar interfaces de repositorios en ReservationServiceProvider
test: actualizar ReservationServiceTest setUp con stubs explícitos
```

---

## Siguiente plan

**Plan 5 — Cobertura de tests en servicios**: Servicios con cobertura baja o tests ausentes:

- `WaitlistService` (~30%), `ReviewService` (~42%), `AuthService` (~52%), `ReservationService` (~62%)
- Test files ausentes: `AccountDeletionServiceTest`, `SettingsServiceTest`, `UserManagementServiceTest`, `HolidayServiceTest`, `InvoicePDFServiceTest`, `AllergenServiceTest`, `AdminServiceTest`
