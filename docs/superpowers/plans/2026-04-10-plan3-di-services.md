# Plan 3 — DI Services: Eliminar `?? new` en la capa de servicios

**Goal:** Eliminar las 27 instancias del patrón `?? new` en `app/Services/` creando ServiceProviders por módulo y forzando inyección de dependencias real.
**Architecture:** Constructor con parámetros requeridos (sin `?`), dependencias resueltas por el container. `ReservationService` excluido (cubre Plan 4).
**Tech Stack:** PHP 8.4, DI Container singleton, ServiceProvider abstract class, `Database::getConnection()` para PDO.

---

## Estado confirmado (pre-implementación)

| Elemento | Estado |
|---|---|
| `ReservationService ?? new` | Excluir → Plan 4 |
| `UserRepositoryInterface` registrado en container | ✅ |
| `ApiTokenServiceInterface` registrado en container | ✅ |
| `StaffServiceProvider` como referencia correcta | ✅ |
| `KeeperServiceProvider` existente | ✅ (sin `AnimalCareService`) |
| `ReservationServiceProvider` existente | ✅ (sin `WaitlistService`) |
| `AuthServiceProvider` | ❌ no existe |
| `SharedServiceProvider` | ❌ no existe |
| `CatalogServiceProvider` | ❌ no existe |

---

## Mapa de archivos afectados

| Archivo | `?? new` count | Grupo |
|---|---|---|
| `app/Services/AuthService.php` | 6 | A |
| `app/Services/ReviewService.php` | 4 | B |
| `app/Services/CafeService.php` | 1 | B |
| `app/Services/UserService.php` | 2 | B |
| `app/Services/MenuService.php` | 1 | C |
| `app/Services/ProductService.php` | 1 | C |
| `app/Services/AnimalCareService.php` | 1 | C |
| `app/Services/WaitlistService.php` | 2 | C |
| `app/Services/CartService.php` | 1 | D (especial) |
| `app/Providers/AuthServiceProvider.php` | CREAR | A |
| `app/Providers/SharedServiceProvider.php` | CREAR | B |
| `app/Providers/CatalogServiceProvider.php` | CREAR | C |
| `app/Providers/KeeperServiceProvider.php` | ACTUALIZAR | C |
| `app/Providers/ReservationServiceProvider.php` | ACTUALIZAR | C |
| `bootstrap/container.php` | ACTUALIZAR | E |

---

## Grupo A — AuthService + AuthServiceProvider

### Tarea A.1 — Limpiar `AuthService` constructor

- [ ] Eliminar los 6 parámetros `?ClaseX $x = null` con fallback `?? new`
- [ ] Cambiar firma a parámetros requeridos (sin `?` ni `null`)
- [ ] Dependencias requeridas:
  - `UserRepositoryInterface`
  - `User` (modelo — sin interfaz, inyectar directamente)
  - `AuthTokenService`
  - `SessionManagementService`
  - `RateLimitingServiceInterface` (prerequisito: Plan 1 debe estar completo)
  - `EmailServiceInterface`

### Tarea A.2 — Crear `app/Providers/AuthServiceProvider.php`

```php
declare(strict_types=1);

namespace App\Providers;

use App\Core\Container;
use App\Core\ServiceProvider;
use App\Repositories\Interfaces\UserRepositoryInterface;
use App\Services\AuthService;
use App\Services\AuthTokenService;
use App\Services\SessionManagementService;
use App\Services\Interfaces\RateLimitingServiceInterface;
use App\Services\Interfaces\EmailServiceInterface;
use App\Models\User;

final class AuthServiceProvider extends ServiceProvider
{
    #[\Override] public function register(): void
    {
        Container::singleton(AuthTokenService::class, fn() =>
            new AuthTokenService(Container::make(\PDO::class))
        );

        Container::singleton(SessionManagementService::class, fn() =>
            new SessionManagementService()
        );

        Container::singleton(AuthService::class, fn() =>
            new AuthService(
                Container::make(UserRepositoryInterface::class),
                new User(),
                Container::make(AuthTokenService::class),
                Container::make(SessionManagementService::class),
                Container::make(RateLimitingServiceInterface::class),
                Container::make(EmailServiceInterface::class),
            )
        );
    }

    #[\Override] public function boot(): void {}
}
```

> **Prerequisito grupo A:** Plan 1 completado (`RateLimitingServiceInterface` y `EmailServiceInterface` bindings presentes).

---

## Grupo B — ReviewService, CafeService, UserService + SharedServiceProvider

### Tarea B.1 — Limpiar constructores

- [ ] `CafeService`: eliminar `?? new CafeRepository()`, requerir `CafeRepositoryInterface`
- [ ] `UserService`: eliminar `?? new UserRepository()` y `?? new User()`, requerir `UserRepositoryInterface + User`
- [ ] `ReviewService`: eliminar 4 `?? new`, requerir `Review, User, ReviewRepositoryInterface, CafeRepositoryInterface, EventDispatcherInterface`

### Tarea B.2 — Crear `app/Providers/SharedServiceProvider.php`

```php
Container::singleton(CafeService::class, fn() =>
    new CafeService(Container::make(CafeRepositoryInterface::class))
);

Container::singleton(UserService::class, fn() =>
    new UserService(Container::make(UserRepositoryInterface::class), new User())
);

Container::singleton(ReviewService::class, fn() =>
    new ReviewService(
        new Review(),
        new User(),
        Container::make(ReviewRepositoryInterface::class),
        Container::make(CafeRepositoryInterface::class),
        Container::make(EventDispatcherInterface::class),
    )
);
```

> Crear también `CafeRepositoryInterface` y `ReviewRepositoryInterface` si no existen.

---

## Grupo C — MenuService, ProductService, AnimalCareService, WaitlistService

### Tarea C.1 — Limpiar constructores

- [ ] `MenuService`: requerir `MenuRepositoryInterface`
- [ ] `ProductService`: requerir `ProductRepositoryInterface`
- [ ] `AnimalCareService`: requerir `AnimalRepositoryInterface`
- [ ] `WaitlistService`: requerir `EmailServiceInterface, WaitlistRepositoryInterface`

### Tarea C.2 — Crear `app/Providers/CatalogServiceProvider.php`

```php
Container::singleton(MenuService::class, fn() =>
    new MenuService(Container::make(MenuRepositoryInterface::class))
);

Container::singleton(ProductService::class, fn() =>
    new ProductService(Container::make(ProductRepositoryInterface::class))
);
```

### Tarea C.3 — Actualizar `app/Providers/KeeperServiceProvider.php`

- [ ] Agregar binding para `AnimalCareService` usando `AnimalRepositoryInterface`

### Tarea C.4 — Actualizar `app/Providers/ReservationServiceProvider.php`

- [ ] Agregar binding para `WaitlistService` usando `EmailServiceInterface + WaitlistRepositoryInterface`

---

## Grupo D — CartService (caso especial)

### Tarea D.1 — Auditar `CartService`

- [ ] `CartService` instancia `Product` model (sin repositorio): documentar decisión explícita
- [ ] Si `Product` es solo un DTO/entidad: aceptable inyectar directamente
- [ ] Si hace queries: extraer a `ProductRepositoryInterface` (mover a Grupo C)
- [ ] Añadir comentario `// NOTE: Product es un modelo sin repositorio propio` si se mantiene

---

## Grupo E — Registrar providers en bootstrap

### Tarea E.1 — Actualizar `bootstrap/container.php`

- [ ] Agregar `AuthServiceProvider` a la lista `$providers` (después de Plan1Provider si existe)
- [ ] Agregar `SharedServiceProvider` a la lista
- [ ] Agregar `CatalogServiceProvider` a la lista
- [ ] Verificar que `KeeperServiceProvider` y `ReservationServiceProvider` ya estén en lista

---

## Tarea transversal — Crear interfaces de repositorio faltantes

| Repositorio | Interfaz | Estado |
|---|---|---|
| `CafeRepository` | `CafeRepositoryInterface` | Verificar / crear |
| `ReviewRepository` | `ReviewRepositoryInterface` | Verificar / crear |
| `MenuRepository` | `MenuRepositoryInterface` | Verificar / crear |
| `ProductRepository` | `ProductRepositoryInterface` | Verificar / crear |
| `AnimalRepository` | `AnimalRepositoryInterface` | Verificar / crear |
| `WaitlistRepository` | `WaitlistRepositoryInterface` | Verificar / crear |

Todas las interfaces en `app/Repositories/Interfaces/` siguiendo el patrón de `UserRepositoryInterface`.

---

## Verificación

```bash
# PHPStan no debe reportar errores nuevos
make phpstan

# Tests unitarios de servicios deben seguir pasando
make test-unit

# Verificar que no queden ?? new en Services
docker compose exec app grep -r "?? new" app/Services/ --include="*.php"
# Resultado esperado: vacío (0 líneas)
```

---

## Commits

```
refactor: limpiar AuthService constructor + crear AuthServiceProvider
refactor: limpiar ReviewService/CafeService/UserService + crear SharedServiceProvider
refactor: limpiar MenuService/ProductService + crear CatalogServiceProvider
refactor: limpiar AnimalCareService + actualizar KeeperServiceProvider
refactor: limpiar WaitlistService + actualizar ReservationServiceProvider
refactor: CartService — documentar inyección de Product model
chore: crear interfaces de repositorio faltantes (Cafe, Review, Menu, Product, Animal, Waitlist)
chore: registrar nuevos providers en bootstrap/container.php
```

---

## Siguiente plan

**Plan 6 — Controller DI**: Eliminar `?? new` en los 72 constructores de controladores. Cada módulo resolverá sus dependencias desde el container (sin instanciación directa). Afecta a Auth, Admin, Manager, Shared, Keeper, Kitchen, Reception y Supervisor.
