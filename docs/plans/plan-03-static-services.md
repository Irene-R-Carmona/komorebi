# Plan 3: Static Services to Injectable — ContextService + NavigationService

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development to implement this plan task-by-task.

**Goal:** Convertir ContextService (14 métodos estáticos, $cafeCache estático) y NavigationService (9+ métodos estáticos) en clases inyectables vía Container. Eliminar estado global mutable. Mantener backward-compat 100% durante la transición con aliases estáticos deprecados.

**Architecture:**

1. Crear versión inyectable (instanciable) de cada servicio.
2. Registrar en Container como singleton.
3. Actualizar todos los callers a usar Container::make() o DI constructor.
4. Eliminar los estáticos originales.

El refactor de ContextService es **cascada**: afecta todos los controllers de backoffice que llaman `ContextService::getCafeId()`, `::getViewData()`, etc. Hacer en fases para no romper todo.

**Tech Stack:** PHP 8.4, DI Container (PHP-DI), CafeRepository, Session

---

### Task 1: ContextService — Crear interfaz y versión inyectable

**Files:**

- Create: `app/Services/Contracts/ContextServiceInterface.php`
- Create: `app/Services/ContextServiceInstance.php` (versión inyectable)
- Keep: `app/Services/ContextService.php` (temporalmente, como thin wrapper)

- [ ] **Step 1: Escribir tests para ContextServiceInstance**

```php
// tests/Unit/Services/ContextServiceInstanceTest.php
<?php
/**
 * ¿Qué pruebas aquí?
 * Verifica el comportamiento de ContextService como clase inyectable.
 *
 * ¿Qué me quieres demostrar?
 * Que getCafeId() retorna el cafe_id del usuario para staff,
 * el seleccionado en sesión para admin, o null para vista global.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se cambia la lógica de resolución de café por rol.
 */
declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Core\Middleware;
use App\Repositories\CafeRepository;
use App\Services\ContextServiceInstance;
use PHPUnit\Framework\TestCase;

final class ContextServiceInstanceTest extends TestCase
{
    private function makeService(
        string $role,
        ?int $userCafeId,
        ?int $sessionSelectedCafeId,
        ?array $cafeData = null
    ): ContextServiceInstance {
        $cafeRepo = $this->createStub(CafeRepository::class);
        if ($cafeData !== null) {
            $cafeRepo->method('findById')->willReturn($cafeData);
        }

        // Simular sesión via array pasado al constructor
        return new ContextServiceInstance($cafeRepo, $role, $userCafeId, $sessionSelectedCafeId);
    }

    public function test_staff_gets_their_assigned_cafe_id(): void
    {
        $service = $this->makeService(Middleware::ROLE_KEEPER, 3, null);
        $this->assertSame(3, $service->getCafeId());
    }

    public function test_admin_with_no_selection_gets_null(): void
    {
        $service = $this->makeService(Middleware::ROLE_ADMIN, null, null);
        $this->assertNull($service->getCafeId());
    }

    public function test_admin_with_selection_gets_selected_cafe(): void
    {
        $service = $this->makeService(Middleware::ROLE_ADMIN, null, 7);
        $this->assertSame(7, $service->getCafeId());
    }

    public function test_is_global_view_true_for_admin_without_selection(): void
    {
        $service = $this->makeService(Middleware::ROLE_ADMIN, null, null);
        $this->assertTrue($service->isGlobalView());
    }

    public function test_is_global_view_false_for_staff(): void
    {
        $service = $this->makeService(Middleware::ROLE_KEEPER, 3, null);
        $this->assertFalse($service->isGlobalView());
    }

    public function test_can_access_cafe_true_for_admin(): void
    {
        $service = $this->makeService(Middleware::ROLE_ADMIN, null, null);
        $this->assertTrue($service->canAccessCafe(99));
    }

    public function test_can_access_cafe_only_their_own_for_staff(): void
    {
        $service = $this->makeService(Middleware::ROLE_KEEPER, 3, null);
        $this->assertTrue($service->canAccessCafe(3));
        $this->assertFalse($service->canAccessCafe(5));
    }

    public function test_get_view_data_returns_expected_keys(): void
    {
        $service = $this->makeService(Middleware::ROLE_MANAGER, 2, null, ['id' => 2, 'name' => 'Café Luna', 'slug' => 'luna']);
        $data = $service->getViewData();

        $this->assertArrayHasKey('cafe_id', $data);
        $this->assertArrayHasKey('cafe_name', $data);
        $this->assertArrayHasKey('is_global', $data);
        $this->assertArrayHasKey('can_switch', $data);
        $this->assertSame(2, $data['cafe_id']);
        $this->assertSame('Café Luna', $data['cafe_name']);
    }
}
```

- [ ] **Step 2: Ejecutar para verificar fallo**

```bash
docker compose exec app vendor/bin/phpunit tests/Unit/Services/ContextServiceInstanceTest.php --colors=always
```

Esperado: FAIL — clase no existe aún.

- [ ] **Step 3: Crear `ContextServiceInstance`**

```php
// app/Services/ContextServiceInstance.php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Middleware;
use App\Repositories\CafeRepository;

/**
 * Versión inyectable de ContextService.
 * Estado por request — no static, no mutable global state.
 */
final class ContextServiceInstance
{
    private ?array $cafeCache = null;

    public function __construct(
        private readonly CafeRepository $cafeRepo,
        private readonly string $role,
        private readonly ?int $userCafeId,
        private readonly ?int $adminSelectedCafeId
    ) {}

    public function getCafeId(): ?int
    {
        if ($this->role !== Middleware::ROLE_ADMIN && $this->userCafeId !== null) {
            return $this->userCafeId;
        }

        if ($this->role === Middleware::ROLE_ADMIN) {
            return $this->adminSelectedCafeId;
        }

        return $this->userCafeId;
    }

    public function hasCafeContext(): bool
    {
        return $this->getCafeId() !== null;
    }

    public function isGlobalView(): bool
    {
        return $this->role === Middleware::ROLE_ADMIN && $this->getCafeId() === null;
    }

    public function getCafe(): ?array
    {
        $cafeId = $this->getCafeId();
        if ($cafeId === null) {
            return null;
        }

        if ($this->cafeCache !== null && $this->cafeCache['id'] === $cafeId) {
            return $this->cafeCache;
        }

        $this->cafeCache = $this->cafeRepo->findById($cafeId);
        return $this->cafeCache;
    }

    public function getCafeName(): string
    {
        return $this->getCafe()['name'] ?? 'Vista Global';
    }

    public function getCafeSlug(): ?string
    {
        return $this->getCafe()['slug'] ?? null;
    }

    public function canAccessCafe(int $cafeId): bool
    {
        if ($this->role === Middleware::ROLE_ADMIN) {
            return true;
        }
        return $this->userCafeId === $cafeId;
    }

    public function requireCafeContext(): int
    {
        $cafeId = $this->getCafeId();
        if ($cafeId === null) {
            throw new \RuntimeException('Se requiere seleccionar un café para esta operación.');
        }
        return $cafeId;
    }

    public function getViewData(): array
    {
        return [
            'cafe_id'    => $this->getCafeId(),
            'cafe_name'  => $this->getCafeName(),
            'cafe'       => $this->getCafe(),
            'is_global'  => $this->isGlobalView(),
            'can_switch' => $this->role === Middleware::ROLE_ADMIN,
        ];
    }
}
```

- [ ] **Step 4: Ejecutar tests**

```bash
docker compose exec app vendor/bin/phpunit tests/Unit/Services/ContextServiceInstanceTest.php --colors=always
```

Esperado: PASS (8 assertions)

- [ ] **Step 5: Crear factory en Container para construir ContextServiceInstance por request**

En `bootstrap/container.php` o en un `ContextServiceProvider.php`:

```php
// El problema: ContextServiceInstance necesita el role/userCafeId/adminSelectedCafeId
// del usuario ACTUAL que llega en el request.
// La solución: registrar como NO-singleton, construir por request.

// En bootstrap/container.php:
\App\Core\Container::bind(\App\Services\ContextServiceInstance::class, function () {
    return new \App\Services\ContextServiceInstance(
        new \App\Repositories\CafeRepository(\App\Core\Database::getConnection()),
        \App\Core\Session::role() ?? '',
        \App\Core\Session::userCafeId(),
        (function () {
            $v = \App\Core\Session::get('admin_selected_cafe_id');
            return $v !== null ? (int) $v : null;
        })()
    );
});
```

- [ ] **Step 6: Commit parcial — la nueva clase y sus tests**

```bash
git add app/Services/ContextServiceInstance.php tests/Unit/Services/ContextServiceInstanceTest.php bootstrap/container.php
git commit -m "feat: add injectable ContextServiceInstance (non-static, testable)"
```

---

### Task 2: Actualizar todos los callers de ContextService (migración)

**Contexto:** Hay controllers de backoffice que llaman `ContextService::getCafeId()`, `ContextService::getViewData()`, etc. Necesitan recibir `ContextServiceInstance` vía constructor injection.

- [ ] **Step 1: Listar todos los callers**

```bash
docker compose exec app grep -rn "ContextService::" app/Http/Controllers/ --include='*.php' | awk -F: '{print $1}' | sort -u
```

- [ ] **Step 2: Para CADA controller que llame ContextService::, añadir constructor injection**

Patrón de migración (ejemplo para Admin/DashboardController):

```php
// ANTES:
use App\Services\ContextService;
// En método: $cafeId = ContextService::getCafeId();

// DESPUÉS:
use App\Services\ContextServiceInstance;

final class DashboardController
{
    public function __construct(
        private readonly ContextServiceInstance $context,
        // ... otros servicios
    ) {}

    public function index(ServerRequestInterface $request): ?ResponseInterface
    {
        $cafeId = $this->context->getCafeId();
        $viewData = $this->context->getViewData();
        // ...
    }
}
```

Aplicar este patrón a **cada controller** identificado en Step 1.

- [ ] **Step 3: Verificar que ContextService:: estático ya no se llama en controllers**

```bash
docker compose exec app grep -rn "ContextService::" app/Http/Controllers/ --include='*.php'
```

Esperado: sin output.

- [ ] **Step 4: Mantener el ContextService original como thin wrapper de deprecación**

```php
// app/Services/ContextService.php — añadir @deprecated a cada método
/** @deprecated Use ContextServiceInstance via DI */
public static function getCafeId(): ?int
{
    return \App\Core\Container::make(\App\Services\ContextServiceInstance::class)->getCafeId();
}
// ... idem para los demás métodos
```

Esto asegura backward-compat para cualquier caller que hayamos olvidado.

- [ ] **Step 5: Ejecutar suite completa**

```bash
make test-unit
make test-integration
```

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/ app/Services/ContextService.php
git commit -m "refactor: migrate backoffice controllers to use ContextServiceInstance via DI"
```

---

### Task 3: NavigationService — Convertir a clase inyectable

**Contexto:** NavigationService tiene solo métodos estáticos sin estado mutable (solo ICONS const y arrays de menú). El refactor es más simple que ContextService: no hay estado que migrar, solo cambiar de estático a instanciable.

- [ ] **Step 1: Escribir test**

```php
// tests/Unit/Services/NavigationServiceTest.php (O verificar si ya existe)
// Si ya existe, añadir:

public function test_get_menu_for_role_returns_array_for_admin(): void
{
    $service = new \App\Services\NavigationService();
    $menu = $service->getMenuForRole('admin');

    $this->assertIsArray($menu);
    $this->assertNotEmpty($menu);
}

public function test_is_active_matches_exact_url(): void
{
    $service = new \App\Services\NavigationService();
    $this->assertTrue($service->isActive('/admin/dashboard', '/admin/dashboard'));
    $this->assertFalse($service->isActive('/admin/users', '/admin/dashboard'));
}

public function test_is_active_matches_prefix(): void
{
    $service = new \App\Services\NavigationService();
    $this->assertTrue($service->isActive('/admin/users', '/admin/users/5/edit'));
}
```

- [ ] **Step 2: Convertir NavigationService de static a instanciable**

```php
// app/Services/NavigationService.php
// Cambiar todos los `public static function` a `public function`
// Cambiar todos los `private static function` a `private function`
// Cambiar todos los `self::item(...)` a `$this->item(...)`
// Cambiar ICONS de `private const array` a `private const array` (sin cambio)
// Eliminar `static` keyword de todos los métodos

// El cambio es mecánico: s/public static function/public function/g y s/self::/\$this->/g
// en métodos (no en match expressions que usen constantes)
```

Comando para hacer el reemplazo:

```bash
# NO ejecutar sin revisar — hacerlo manualmente para no romper constantes
docker compose exec app sed -n 'p' app/Services/NavigationService.php
# Revisar cada ocurrencia de "self::" — las que llamen métodos → $this->
# Las que usen constantes (ICONS) → self:: se mantiene (o también $this- para constantes)
```

**Nota:** En PHP, `self::ICONS` y `$this::ICONS` son equivalentes para constantes de clase. Cambiar los method calls de `self::item(...)`, `self::getAdminMenu()` etc. a `$this->item(...)`, `$this->getAdminMenu()` etc.

- [ ] **Step 3: Registrar en Container**

```php
// En bootstrap/container.php:
\App\Core\Container::singleton(\App\Services\NavigationService::class, fn() => new \App\Services\NavigationService());
```

- [ ] **Step 4: Añadir thin wrapper estático deprecado**

```php
// Mantener backward-compat: añadir static wrappers al final de la clase
/** @deprecated Inject NavigationService via constructor */
public static function getMenuForRoleStatic(string $role): array
{
    return (new self())->getMenuForRole($role);
}
```

O mantener los métodos estáticos pero que internamente llamen instancia:

```php
// Forma alternativa más limpia: dual API
public static function forRole(string $role): array
{
    return \App\Core\Container::make(self::class)->getMenuForRole($role);
}
```

- [ ] **Step 5: Actualizar callers en views (layouts)**

```bash
docker compose exec app grep -rn "NavigationService::" resources/views/ --include='*.php'
```

Para cada ocurrencia en vistas, cambiar a usar la variable `$nav` pasada desde el controller con `ContextServiceInstance::getViewData()`, o mantener el alias estático deprecated temporalmente.

- [ ] **Step 6: Ejecutar tests**

```bash
docker compose exec app vendor/bin/phpunit tests/Unit/Services/NavigationServiceTest.php --colors=always
make test-unit
```

- [ ] **Step 7: Commit**

```bash
git add app/Services/NavigationService.php tests/Unit/Services/NavigationServiceTest.php bootstrap/container.php
git commit -m "refactor: convert NavigationService from static to injectable singleton"
```

---

**Verification final del Plan 3:**

```bash
docker compose exec app grep -rn "ContextService::\|NavigationService::" app/Http/Controllers/ --include='*.php'
```

Esperado: 0 ocurrencias activas (solo deprecated wrappers en los propios Service files).

```bash
make ci
```
