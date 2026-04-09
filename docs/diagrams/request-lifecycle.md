# Ciclo de Vida de una Petición HTTP

Traza el camino completo de una petición HTTP desde el navegador hasta la respuesta, pasando por el front controller, el router, el pipeline de middleware PSR-15, el controller, el service y el repository.

---

## Diagrama de Flujo

```mermaid
flowchart LR
    Browser(["Browser\nHTTP Request"])
    FrontCtrl["public/index.php\nFront Controller\nBootstrap DI Container"]
    Router["Router::dispatch()\nMatch ruta → Controller@method"]

    subgraph pipeline ["Middleware Pipeline — PSR-15 secuencial"]
        direction TB
        MW1["SecurityHeadersMiddleware\nCSP · HSTS · X-Frame-Options"]
        MW2["SessionMiddleware\nInicializa sesión PHP"]
        MW3["CsrfMiddleware\n(POST · PUT · PATCH · DELETE)"]
        MW4["RateLimitMiddleware\nLímite por IP/usuario"]
        MW5["AuthMiddleware\n(rutas protegidas)"]
        MW6["RoleMiddleware\nRBAC: verifica rol"]
        MW1 --> MW2 --> MW3 --> MW4 --> MW5 --> MW6
    end

    Controller["Controller\nValidar input\nLlamar al Service"]
    Service["Service\nLógica de negocio\nreturn Result"]
    Decision{"Result::ok?"}
    Repository["Repository\nConsulta PDO"]
    MySQL[("MySQL")]
    ResponseOk["ResponseFactory\njson() / redirect() /\nView::render()"]
    FlashError["Flash::error()\nRedirect de vuelta"]
    BrowserOk(["Browser\nRespuesta exitosa"])
    BrowserError(["Browser\nRedirect / error"])

    Browser -->|"GET / POST / ..."| FrontCtrl
    FrontCtrl --> Router
    Router --> pipeline
    MW6 --> Controller
    Controller --> Service
    Service --> Decision
    Decision -->|"ok = true"| Repository
    Repository --> MySQL
    MySQL --> ResponseOk
    ResponseOk --> BrowserOk
    Decision -->|"ok = false"| FlashError
    FlashError --> BrowserError
```

---

## Notas sobre el Flujo

- **Front Controller**: `public/index.php` es el único punto de entrada. Carga el autoloader de Composer y el contenedor de dependencias (`bootstrap/container.php`).
- **Router**: Compara la ruta con las definiciones en `app/routes.php`. Si no hay coincidencia devuelve 404; si el método HTTP no coincide devuelve 405.
- **Middleware Pipeline**: Se ejecuta de forma secuencial (PSR-15). Cualquier middleware puede cortocircuitar el pipeline devolviendo una respuesta temprana —por ejemplo, un redirect a `/login` si no hay sesión activa.
- **Result pattern**: Todos los services devuelven `Result::ok($data)` o `Result::fail('mensaje', 'error_code')`. Nunca lanzan excepciones para fallos esperados de dominio.
- **ResponseFactory**: Inyectado en todos los controllers vía constructor. Métodos principales: `redirect()`, `json()`, `html()`.
- **View::render()**: Hace echo directo y devuelve `null`; el controller retorna `null` implícitamente. Para datos dinámicos usa `Raw::json()` o `Raw::html()` para saltarse el auto-escape.
