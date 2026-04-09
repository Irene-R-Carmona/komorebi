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

```
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
