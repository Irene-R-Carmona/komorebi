# Arquitectura — Komorebi Café

## Resumen

Komorebi Café utiliza un MVC personalizado en PHP 8.4 sin la capa de aplicación de Laravel ni Symfony. Esto elimina la
sobrecarga de un framework completo manteniendo convenciones claras y una estructura predecible. El proyecto sigue los
principios de la metodología [12-Factor App](https://12factor.net/).

## Capas de la aplicación

### 1. Capa HTTP

**Archivos:** `public/index.php`, `app/Core/Router.php`, `app/Middleware/`

Front controller único (`public/index.php`). Las peticiones HTTP se modelan como objetos PSR-7 (`nyholm/psr7`) y se
procesan a través de un pipeline PSR-15.

**Orden del pipeline de middleware:**

```text
security headers → session → CSRF → auth → role
```

El router resuelve la ruta y delega al controlador correspondiente. Todas las rutas se definen en `app/routes.php`.

### 2. Capa de Aplicación

**Archivos:** `app/Http/Controllers/`

Controladores delgados. No contienen lógica de negocio. Sus únicas responsabilidades son:

- Validar y extraer la entrada del usuario
- Llamar al servicio correspondiente
- Interpretar el `Result` devuelto
- Retornar una `ResponseInterface` (redirección, JSON o renderizado de vista)

Los controladores están agrupados por rol: `Admin/`, `Auth/`, `Manager/`, `Reception/`, `Kitchen/`, `Keeper/`,
`Public/`, `Shared/`, `Api/`.

### 3. Capa de Dominio

**Archivos:** `app/Services/`, `app/Events/`, `app/Listeners/`, `app/Jobs/`

Contiene toda la lógica de negocio. Todos los métodos de servicio devuelven un objeto `Result` y nunca lanzan
excepciones para fallos esperados.

Los efectos secundarios asíncronos se canalizan mediante:

- **Eventos PSR-14** — para acciones internas desacopladas (emails de bienvenida, notificaciones, auditoría)
- **Cola Redis** — para trabajos diferidos que requieren procesamiento en background

### 4. Capa de Infraestructura

**Archivos:** `app/Repositories/`, `app/Models/`, `app/Core/Database.php`, `app/Core/Cache.php`, `app/Core/Queue.php`

Acceso a datos. Los repositorios extienden `AbstractRepository`, que proporciona operaciones CRUD base. Los modelos
encapsulan SQL complejo. Todas las consultas usan sentencias preparadas PDO — nunca SQL crudo en controladores.

## Patrones clave

### Result pattern

Todos los métodos de servicio devuelven `Result::ok($data)` o `Result::fail('mensaje', 'codigo')`. Los controladores
comprueban `$result->ok`. No cruzan excepciones las fronteras de servicio para fallos esperados.

```php
// En el servicio:
return Result::ok($reservation);
return Result::fail('Aforo completo', 'capacity_exceeded');

// En el controlador:
if (!$result->ok) {
    Flash::error($result->getMessage());
    return $this->response->redirect('/reservations');
}
$reservation = $result->data;
```

### Repository pattern

`AbstractRepository` proporciona `findById`, `findAll`, `create`, `update`, `delete`. Las consultas personalizadas se
añaden en las subclases. Nunca hay SQL crudo en los controladores.

### Event / Listener

EventDispatcher PSR-14 (Symfony). Disparar y olvidar para efectos secundarios (emails, notificaciones, logs). Los
listeners se registran en `EventServiceProvider`.

```php
$dispatcher->dispatch(new UserRegisteredEvent($id, $email, $name, new DateTimeImmutable()));
```

### Async Queue

Cola respaldada por Redis mediante `Queue::push(JobClass::class, $payload)`.

| Worker  | Cola      | Binario                |
|---------|-----------|------------------------|
| General | `default` | `bin/worker.php`       |
| Emails  | `emails`  | `bin/email-worker.php` |

### Dependency Injection

`App\Core\Container` (patrón service locator). Los servicios se registran como singletons en `bootstrap/container.php`
mediante `ServiceProvider`s con ciclo de vida `register → boot`.

## Dependencias externas

| Componente    | Tecnología               | Versión    |
|---------------|--------------------------|------------|
| Servidor HTTP | FrankenPHP + Caddy       | 1.11.1     |
| Base de datos | MySQL                    | 8.4 LTS    |
| Cache / Colas | Redis                    | 8 (Alpine) |
| HTTP PSR-7    | nyholm/psr7              | ^2         |
| Eventos       | symfony/event-dispatcher | ^7         |
| Cache L2      | symfony/cache            | ^7         |
| Logging       | monolog/monolog          | ^3         |
| Email         | phpmailer/phpmailer      | ^6         |

## RBAC — Roles de usuario

El sistema define 7 roles con acceso jerárquico definido en `app/Core/Middleware.php`:

| Rol          | Descripción                    |
|--------------|--------------------------------|
| `admin`      | Acceso total                   |
| `manager`    | Gestión operativa              |
| `supervisor` | Supervisión de turnos          |
| `reception`  | Gestión de reservas y clientes |
| `kitchen`    | Vista de pedidos (KDS)         |
| `keeper`     | Gestión de animales            |
| `user`       | Cliente registrado             |

## Diagramas

Los diagramas de referencia se encuentran en `docs/diagrams/`:

| Archivo                | Contenido                            |
|------------------------|--------------------------------------|
| `architecture.md`      | Vista C4 (contexto + contenedores)   |
| `request-lifecycle.md` | Ciclo de vida de una petición HTTP   |
| `reservation-flow.md`  | Flujo de creación de reserva         |
| `auth-flow.md`         | Flujo de autenticación y RBAC        |
| `er.puml`              | Diagrama entidad-relación (PlantUML) |

---

## Decisiones Arquitectónicas — v2 (Abril 2026)

### Capa de Dominio

Introducida en Fase 0 (streams 1–2). Los DTOs viven en `app/Domain/`:

```text
app/Domain/
  DTO/             → Datos de transferencia inmutables (readonly classes)
  Reservation/     → State machine de reservas
```

**Nota:** `app/Domain/ValueObjects/` fue eliminado (abril 2026) — los Value Objects con uso activo
residen en `app/Core/ValueObjects/` (`Email`, `Slug`, `Password`, `GuestCount`, etc.).

**Reglas obligatorias:**

- DTOs son `final readonly` con constructor promocionado y métodos `fromArray(array): static` + `toViewArray(): array`.
- Los Value Objects son `final readonly`, encapsulan validación, nunca retornan arrays crudos a la vista.
- Ni DTOs ni VOs contienen lógica de negocio.
- **Los DTOs no se pasan directamente a `View::render()`.** Deben llamar a `toViewArray()` primero.

### Contratos de Capa (Service Interfaces)

Ubicados en `app/Services/Contracts/`. Cualquier servicio con casos de uso testables debe tener una interfaz aquí.
Los controladores inyectan la interfaz, no la implementación concreta.

```text
app/Services/Contracts/
  ProductServiceInterface.php
  ReservationServiceInterface.php
  ...
```

### Contrato de Repositorios (`getSelectFields`)

`AbstractRepository::getSelectFields()` define exactamente qué columnas expone el repositorio a las capas superiores.
**Campos internos** (credenciales, flags de borrado lógico, columnas de auditoría interna) **no deben listarse aquí.**

Ejemplo de contrato correcto:

```php
protected function getSelectFields(): array
{
    return ['id', 'name', 'email', 'role', 'created_at']; // SIN: password_hash, deleted_at
}
```

### Versionado de API

Todos los endpoints públicos y privados de la API REST se sirven bajo `/api/v1/`.

- Prefijo de URL: `/api/v1/`
- Namespace PHP: `App\Http\Controllers\Api\V1\`
- Documentación: `docs/openapi.yaml` (servidor base `url: /api/v1`)

No añadir rutas `/api/` sin versión. Futuras versiones usarán `/api/v2/`, etc.

### Frontera de Escape XSS

`View::render()` escapa automáticamente todos los valores de `$data`.
Para datos que no deben escaparse (HTML sanitizado, JSON para Alpine.js), usar:

```php
'jsonData' => Raw::json($array),   // JSON seguro para x-data de Alpine
'htmlSnippet' => Raw::html($safe), // HTML pre-sanitizado
```

No pasar objetos en `$data`. Los DTOs deben llamar a `toViewArray()`.

### Nomenclatura CQRS

Los métodos de servicio siguen la convención:

| Acción         | Prefijo                      | Ejemplo                      |
|----------------|------------------------------|------------------------------|
| Consulta       | `get`, `find`, `list`        | `getById()`, `findByEmail()` |
| Mutación       | `create`, `update`, `delete` | `createReservation()`        |
| Validación     | `validate`, `check`          | `validateRedemptionCode()`   |
| Operación sync | verbo directo                | `redeem()`, `use()`          |

### Límite de Error

- Los servicios retornan `Result::ok($data)` / `Result::fail($msg, $code)`. **No lanzan excepciones** para fallos
  esperados.
- Los controladores traducen `Result` a respuestas HTTP (`$this->unprocessable()`, `$this->success()`, etc.).
- Las excepciones (`\Throwable`) son para errores inesperados de infraestructura — el middleware de error las convierte
  en HTTP 500.

---

## Capa de Presentación: HDA + Progressive Enhancement

### Patrón adoptado

La capa de presentación sigue el patrón **HDA (Hypermedia-Driven Application)**. Ver decisión completa en
`docs/adr/001-hda-architecture.md`.

Principio rector: **PHP es la única fuente de verdad del estado de la aplicación**. El cliente (Alpine.js)
gestiona exclusivamente comportamiento de UI efímero.

### Rol de Alpine.js post-migración

Alpine.js permanece como capa de **comportamiento**, no de datos:

| Permitido | Prohibido |
| --------- | --------- |
| Modales y drawers | `fetch()` de colecciones de dominio en `init()` |
| Stepper de personas (±1) | Almacenar `cafes`, `passes`, `reservations` en estado Alpine |
| Validación inline (festivo bloqueado, pass incompatible) | `Alpine.store()` con datos de servidor |
| Feedback optimista de cancelación | Rutas que sólo existen para alimentar `init()` |
| Consultas reactivas a input del usuario (slots, clima) | |

**Patrón correcto de configuración:**

```php
// Controller:
$config = json_encode(['cafes' => $cafes, 'passes' => $passes], JSON_HEX_APOS | JSON_HEX_QUOT);
// View:
// <div x-data="reservaForm(<?= $config ?>)">
```

El componente Alpine recibe los datos como argumento de inicialización, nunca los obtiene por `fetch()`.

### Contrato REST API — cuándo usar AJAX

Los endpoints GET del API están permitidos exclusivamente cuando la consulta es **reactiva a input del
usuario que ocurre después de la carga de página**:

| Endpoint | Disparador | Justificación |
| -------- | ---------- | ------------- |
| `GET /api/v1/time-slots/available` | Usuario elige fecha | Disponibilidad cambia en tiempo real |
| `GET /api/v1/weather` | Usuario elige fecha | Dato externo, no cacheable por PHP |
| `GET /api/v1/holidays/{fecha}` | Usuario elige fecha | Reactivo a selección |
| `GET /api/v1/user/reservations` | Carga de historial | Asíncrono para no bloquear paso 1 |

Los endpoints POST/PATCH/DELETE son mutaciones de dominio y devuelven `{ok: bool, data?: ..., error?: ...}`.

### Wizard de reservas — patrón PRG

El wizard de reservas (3 pasos) implementa **Post/Redirect/Get** con estado en sesión PHP:

```text
POST /reservar/paso-1  →  Session::set('reservation_wizard', [...])  →  302 /reservar/paso-2
GET  /reservar/paso-2  →  PHP lee sesión, renderiza formulario de fecha/hora
POST /reservar/paso-2  →  Session::set('reservation_wizard', [...])  →  302 /reservar/paso-3
GET  /reservar/paso-3  →  PHP lee sesión, renderiza confirmación
POST /reservar         →  crea reserva, Session::remove('reservation_wizard')  →  302 /reservas/confirmacion/{id}
```

Cada paso es una URL independiente. Recargar devuelve el mismo estado. El botón "Volver" es un `<a href>`.

---

## Deuda Técnica Deliberada

### `app/Models/` — Active Record coexistiendo con Repository pattern

**Ubicación:** `app/Models/` (20 clases: `User`, `Cafe`, `Product`, `Reservation`, `Animal`, etc.)

**Situación:** El proyecto usa Repository pattern (`app/Repositories/`) como arquitectura objetivo,
pero 20 clases Active Record legacy en `app/Models/` siguen en uso activo en controllers y servicios.
Ambas capas conviven de forma provisional.

**Impacto:** Viola la separación de capas — la lógica de acceso a BD se mezcla con el dominio.
Dificulta los tests unitarios (los Models instancian PDO directamente y no son inyectables).

**Criterios de eliminación futura:**

- Cada Model debe tener un Repository equivalente que asuma sus queries.
- Los controllers que usen `App\Models\*` deben migrar a inyectar la interfaz del Repository.
- Eliminar el Model cuando todos sus usos hayan migrado al Repository.
- Prioridad de migración: `Cafe` (9 usos) → `User` (mayor complejidad) → resto.

**Seguimiento:** Crear plan `docs/plans/YYYY-MM-DD-migracion-models-a-repositories.md`
cuando se inicie la migración. Búsqueda de usos: `grep -r "App\\Models\\" app/`.
