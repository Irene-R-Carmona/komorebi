# Fix: Bugs de diseño en Controllers — Komorebi Café

> **For agentic workers:** Steps use checkbox (`- [ ]`) syntax for tracking. Ejecutar en orden; verificar tras cada fase.

**Estado:** ✅ Completo — auditados 13 controllers, todos ya implementaban el patrón correcto. Verificación final: PHPUnit 856/856 ✅, PHPStan 0 ✅, CS 0 ✅ (20/04/2026)

**Goal:** Eliminar side-effects en constructores y dependencias no-inyectables en 13 controllers. Sin tests nuevos, sin refactoring de lógica de negocio.

**Patrón correcto** (referencia: AuthController, DashboardController):

```php
public function __construct(?ServiceInterface $service = null, ?ResponseFactory $response = null) {
    $this->service = $service ?? Container::make(ServiceInterface::class);
    $this->response = $response ?? new ResponseFactory();
}
```

Para modelos (son final, necesitan PDO):

```php
$this->cafeModel = $cafeModel ?? new Cafe(Container::make(PDO::class));
```

---

## FASE 1 — Bug crítico: Middleware::auth() en constructores (2 archivos)

**Regla violada**: Los constructores solo deben asignar propiedades. El middleware ya está aplicado en routes.php en sus respectivos grupos (confirmado en auditoría).

### 1.1 ReceptionController

**Archivo**: `app/Http/Controllers/Reception/ReceptionController.php`

- [ ] Eliminar la línea `Middleware::auth();` del constructor
- [ ] Verificar en `app/routes.php` que el grupo `/ops/reception` tiene `$mw->auth()` y `$mw->role(...)` — si no, añadirlos
- [ ] Cambiar `$service ?? new ReceptionService()` → `$service ?? Container::make(ReceptionServiceInterface::class)` (si existe la interface; si no, `Container::make(ReceptionService::class)`)

### 1.2 KitchenController

**Archivo**: `app/Http/Controllers/Kitchen/KitchenController.php`

- [ ] Eliminar la línea `Middleware::auth();` del constructor
- [ ] Verificar en `app/routes.php` que el grupo `/ops/kitchen` tiene `$mw->auth()` y `$mw->role(...)` — si no, añadirlos
- [ ] Cambiar `$service ?? new KitchenService()` → `$service ?? Container::make(KitchenService::class)` o su interfaz

**Verificación fase 1**: `make test-unit` — todos los tests existentes siguen pasando. Acceder manualmente a `/ops/reception` y `/ops/kitchen` sin sesión → debe redirigir (el middleware de ruta funciona).

---

## FASE 2 — Constructor sin parámetros inyectables (1 archivo)

**El más crítico para testabilidad**: constructor vacío que instancia todo internamente, no hay forma de inyectar mocks.

### 2.1 AdminProductController

**Archivo**: `app/Http/Controllers/Admin/ProductController.php`

Constructor actual (sin ningún parámetro):

```php
public function __construct() {
    $this->productModel = new Product();
    $this->allergenModel = new Allergen();
    $this->productRepo = new ProductRepository();
    $this->response = new ResponseFactory();
    $this->productTransformer = new ProductTransformer();
}
```

- [ ] Añadir parámetros nullable al constructor:

  ```php
  public function __construct(
      ?Product $productModel = null,
      ?Allergen $allergenModel = null,
      ?ProductRepositoryInterface $productRepo = null,
      ?ResponseFactory $response = null,
      ?ProductTransformer $productTransformer = null,
  )
  ```

- [ ] Usar null-coalescing con Container::make(PDO::class) para modelos:

  ```
  $this->productModel = $productModel ?? new Product(Container::make(PDO::class));
  $this->allergenModel = $allergenModel ?? new Allergen(Container::make(PDO::class));
  $this->productRepo = $productRepo ?? Container::make(ProductRepositoryInterface::class);
  $this->response = $response ?? new ResponseFactory();
  $this->productTransformer = $productTransformer ?? new ProductTransformer();
  ```

**Verificación fase 2**: `make test-unit` — sin regresiones.

---

## FASE 3 — Constructores con `?? new Concrete()` hardcoded (5 archivos)

Estos ya tienen parámetros nullable (testabilidad básica OK), pero el fallback de producción no usa Container → instancias sin PDO. Cambiar el fallback.

### 3.1 SharedReservationController

**Archivo**: `app/Http/Controllers/Shared/ReservationController.php`

Dependencias en constructor:

- `$cartService ?? new CartService()` → `?? Container::make(CartServiceInterface::class)` (o su interfaz)
- `$availabilityService ?? new AvailabilityService()` → `?? Container::make(AvailabilityServiceInterface::class)`
- `$reservationModel ?? new Reservation()` → `?? new Reservation(Container::make(PDO::class))`
- `$festivosService ?? new FestivosJaponesesService()` → `?? Container::make(FestivosJaponesesServiceInterface::class)`

- [ ] Actualizar los 4 null-coalescing del constructor

### 3.2 SharedReviewController

**Archivo**: `app/Http/Controllers/Shared/ReviewController.php`

- `$cafeModel ?? new Cafe()` → `?? new Cafe(Container::make(PDO::class))`

- [ ] Actualizar el null-coalescing del modelo Cafe

### 3.3 AdminReservationController

**Archivo**: `app/Http/Controllers/Admin/ReservationController.php`

- `$activityService ?? new AdminActivityService()` → `?? Container::make(AdminActivityServiceInterface::class)` (o su interfaz)

- [ ] Actualizar el null-coalescing

### 3.4 AnimalCareController (constructor)

**Archivo**: `app/Http/Controllers/Keeper/AnimalCareController.php`

- `$fileUploadService ?? new FileUploadService()` → `?? Container::make(FileUploadServiceInterface::class)`

- [ ] Actualizar el null-coalescing del constructor

### 3.5 HomeController *(verificar)*

**Archivo**: `app/Http/Controllers/Public/HomeController.php`

Según auditoría previa: instancia `new Cafe()` y `new Animal()` sin inyección.

- [ ] Leer el constructor completo para confirmar el patrón exacto
- [ ] Si tiene parámetros nullable: cambiar `?? new Cafe()` → `?? new Cafe(Container::make(PDO::class))` y lo mismo para Animal
- [ ] Si NO tiene parámetros: añadirlos (mismo patrón que AdminProductController, Fase 2)

### 3.6 PublicCafeController *(verificar)*

**Archivo**: `app/Http/Controllers/Public/CafeController.php`

Según auditoría previa: `new Cafe()` en la línea 47 (puede ser constructor o método).

- [ ] Leer para confirmar si es constructor o método
- [ ] Si es constructor: mismo fix que 3.5
- [ ] Si es método: mover a constructor como propiedad inyectable (ver Fase 4 para patrón)

**Verificación fase 3**: `make test-unit` — sin regresiones.

---

## FASE 4 — Instanciación en métodos (mover a constructor como propiedad) (4 archivos)

Los modelos instanciados dentro de métodos son imposibles de sustituir en tests. Moverlos al constructor como propiedades inyectables.

### 4.1 AdminMenuController

**Archivo**: `app/Http/Controllers/Admin/MenuController.php`

En método `index()`:

```php
$productModel = new Product();      // ← en método
$categoryModel = new MenuCategory();
```

- [ ] Añadir propiedades privadas: `private Product $productModel` y `private MenuCategory $categoryModel`
- [ ] Añadir parámetros nullable al constructor: `?Product $productModel = null, ?MenuCategory $categoryModel = null`
- [ ] Asignar en constructor: `$this->productModel = $productModel ?? new Product(Container::make(PDO::class))`
- [ ] Reemplazar variables locales en los métodos por `$this->productModel` y `$this->categoryModel`

### 4.2 AdminCafeController

**Archivo**: `app/Http/Controllers/Admin/CafeController.php`

En método `index()`: `$cafeModel = new Cafe();`

- [ ] Mismo patrón que 4.1 para `Cafe`

### 4.3 AdminRoleController

**Archivo**: `app/Http/Controllers/Admin/RoleController.php`

En método `index()`: `$roleModel = new Role()` y `$permissionModel = new Permission()`

- [ ] Mismo patrón para `Role` y `Permission`

### 4.4 AnimalCareController (método uploadPhoto)

**Archivo**: `app/Http/Controllers/Keeper/AnimalCareController.php`

En método `uploadPhoto()`: `$animalModel = new Animal(Database::getConnection())`

- [ ] Añadir propiedad `private Animal $animalModel`
- [ ] Añadir `?Animal $animalModel = null` al constructor
- [ ] Asignar: `$this->animalModel = $animalModel ?? new Animal(Container::make(PDO::class))`
- [ ] Reemplazar uso en método por `$this->animalModel`

### 4.5 PublicQuizController

**Archivo**: `app/Http/Controllers/Public/QuizController.php`

En método `resultado()`: `$cafeModel = new Cafe()`

- [ ] Mismo patrón para `Cafe`

**Verificación fase 4**: `make test-unit` — sin regresiones.

---

## FASE 5 — Verificación final integrada

- [ ] `make test-unit` → misma cantidad de tests que antes, 0 errores
- [ ] `make phpstan` → 0 errores nuevos (los constructores nuevos deben tener tipos correctos)
- [ ] Acceder manualmente a rutas protegidas sin sesión → redirige (middleware de rutas funciona)
- [ ] Acceder manualmente con sesión válida → controllers cargan correctamente

---

## Archivos a modificar (11 confirmados + 2 por verificar)

| Archivo | Bug | Cambio |
|---|---|---|
| `app/Http/Controllers/Reception/ReceptionController.php` | Bug 1 + Bug 2 | Eliminar Middleware::auth(); fix fallback service |
| `app/Http/Controllers/Kitchen/KitchenController.php` | Bug 1 + Bug 2 | Eliminar Middleware::auth(); fix fallback service |
| `app/Http/Controllers/Admin/ProductController.php` | Bug 1 (sin params) | Añadir constructor con 5 nullable params |
| `app/Http/Controllers/Shared/ReservationController.php` | Bug 1 | Fix 4 fallbacks |
| `app/Http/Controllers/Shared/ReviewController.php` | Bug 1 | Fix 1 fallback (Cafe model) |
| `app/Http/Controllers/Admin/ReservationController.php` | Bug 1 | Fix 1 fallback (AdminActivityService) |
| `app/Http/Controllers/Keeper/AnimalCareController.php` | Bug 1 (constructor + método) | Fix constructor + mover Animal a propiedad |
| `app/Http/Controllers/Admin/MenuController.php` | Bug 1 (método) | Mover Product y MenuCategory a constructor |
| `app/Http/Controllers/Admin/CafeController.php` | Bug 1 (método) | Mover Cafe a constructor |
| `app/Http/Controllers/Admin/RoleController.php` | Bug 1 (método) | Mover Role y Permission a constructor |
| `app/Http/Controllers/Public/QuizController.php` | Bug 1 (método) | Mover Cafe a constructor |
| `app/Http/Controllers/Public/HomeController.php` | Bug 1 (por verificar) | Fix según patrón encontrado |
| `app/Http/Controllers/Public/CafeController.php` | Bug 1 (por verificar) | Fix según patrón encontrado |
| `app/routes.php` | Verificar | Confirmar middleware en grupos Reception y Kitchen |

---

## Lo que NO se toca

- Lógica de negocio de los métodos de los controllers
- Estructura de rutas (excepto verificar que middleware ya existe)
- Servicios o modelos referenciados
- Tests existentes (no se escriben tests nuevos en este plan)
