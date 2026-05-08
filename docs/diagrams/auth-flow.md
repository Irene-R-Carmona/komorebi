# Flujo de Autenticación y RBAC

Diagrama de secuencia que muestra el proceso completo de login —validación CSRF, búsqueda de usuario, verificación de contraseña Argon2id y carga de roles— y la verificación posterior de autenticación y permisos RBAC en cada petición a una ruta protegida.

---

## Flujo de Login

```mermaid
sequenceDiagram
    autonumber

    actor Usuario
    participant AC as AuthController
    participant CSRF as CsrfMiddleware
    participant AS as AuthService
    participant UR as UserRepository
    participant S as Session

    Usuario->>AC: POST /login (email, password)
    AC->>CSRF: Verificar token CSRF
    CSRF-->>AC: Token válido

    AC->>+AS: login(email, password)
    AS->>+UR: findByEmail(email)

    alt Usuario encontrado y contraseña correcta
        UR-->>AS: User (con hash Argon2id)
        AS->>AS: password_verify(input, hash)
        AS->>UR: getRoles(userId)
        UR-->>-AS: roles[] + permissions[]
        AS-->>-AC: Result::ok(['user' => ..., 'roles' => ...])
        AC->>S: set('user_id', $id)
        AC->>S: set('roles', $roles)
        AC-->>Usuario: redirect /dashboard
    else Usuario no encontrado o contraseña incorrecta
        UR-->>-AS: null
        AS-->>-AC: Result::fail('Credenciales incorrectas', 'auth_failed')
        AC-->>Usuario: Flash::error() + redirect /login
    end
```

---

## Verificación RBAC en Peticiones Protegidas

```mermaid
sequenceDiagram
    autonumber

    actor Usuario
    participant AM as AuthMiddleware
    participant RM as RoleMiddleware
    participant Ctrl as Controller

    Usuario->>AM: Request a ruta protegida

    AM->>AM: Session::get('user_id')

    alt Sesión activa — user_id existe en sesión
        AM->>RM: Pasar al siguiente middleware
        RM->>RM: Comparar roles requeridos vs sesión

        alt El usuario tiene el rol necesario
            RM->>Ctrl: Pasar la petición al Controller
            Ctrl-->>Usuario: HTTP 200 + contenido de la ruta
        else Rol insuficiente
            RM-->>Usuario: HTTP 403 Forbidden
        end
    else Sin sesión activa o sesión expirada
        AM-->>Usuario: redirect /login
    end
```

---

## Autenticación API stateless — Bearer Token

```mermaid
sequenceDiagram
    autonumber

    actor ClienteAPI as Cliente API
    participant TC as TokenController
    participant ATS as ApiTokenService
    participant UR as UserRepository
    participant RD as Redis

    note over ClienteAPI,TC: ① Generación del token (POST /api/v1/tokens)

    ClienteAPI->>TC: POST /api/v1/tokens {name, email, password}
    TC->>+ATS: createToken(email, password, name)
    ATS->>+UR: findByEmail(email)
    UR-->>-ATS: User (con hash Argon2id)
    ATS->>ATS: password_verify(input, hash)

    alt Credenciales correctas
        ATS->>ATS: Generar token aleatorio (random_bytes 32)
        ATS->>ATS: hash SHA-256 → token_hash
        ATS->>UR: insertApiToken(user_id, name, token_hash, abilities, expires_at)
        UR-->>ATS: api_token row creado
        ATS-->>-TC: Result::ok(['plain_token' => '...'])
        TC-->>ClienteAPI: HTTP 201 {token: "plain_text"} — solo una vez
    else Credenciales incorrectas
        ATS-->>-TC: Result::fail('Credenciales inválidas', 'auth_failed')
        TC-->>ClienteAPI: HTTP 401
    end

    note over ClienteAPI,RD: ② Uso del token en peticiones posteriores

    ClienteAPI->>TC: GET /api/v1/reservas\nAuthorization: Bearer <plain_token>
    TC->>+ATS: verifyToken(plain_token)
    ATS->>ATS: hash SHA-256(plain_token) → token_hash
    ATS->>+UR: findByTokenHash(token_hash)
    UR-->>-ATS: api_token row (o null)

    alt Token válido — no revocado, no expirado
        ATS->>UR: updateLastUsedAt(token_id)
        ATS-->>-TC: Result::ok($user)
        TC-->>ClienteAPI: HTTP 200 + datos solicitados
    else Token inválido, revocado o expirado
        ATS-->>-TC: Result::fail('Token inválido', 'token_invalid')
        TC-->>ClienteAPI: HTTP 401
    end
```

---

## Notas de Seguridad

| Aspecto | Implementación |
|---|---|
| Hash de contraseña | `password_hash()` con `PASSWORD_ARGON2ID` |
| Protección CSRF | Token por sesión validado en `CsrfMiddleware` (POST · PUT · PATCH · DELETE) |
| Gestión de sesión | PHP sessions con Redis como backend de almacenamiento |
| RBAC | Roles: `admin`, `manager`, `supervisor`, `reception`, `kitchen`, `keeper`, `user` |
| Rate limiting | `RateLimitMiddleware` limita intentos de login por IP y usuario |
| Constantes de rol | `ROLE_ADMIN`, `ROLE_MANAGER`, etc. definidas en `App\Core\Middleware` |
| API token hash | `hash('sha256', $plainToken)` — el plain token nunca se almacena en BD |
| API token scope | Campo `abilities` JSON controla qué endpoints puede usar cada token |
| Revocación | `revoked_at` timestamp; `ApiAuthMiddleware` rechaza tokens revocados con HTTP 401 |

- **`password_verify`** ejecuta una comparación en tiempo constante para prevenir ataques de temporización (_timing attacks_).
- Si el token CSRF falla, `CsrfMiddleware` cortocircuita el pipeline inmediatamente y devuelve HTTP 419 sin llegar al controller.
- Las contraseñas **nunca** se loggean. El `Logger` registra solo el email y el resultado boolean del intento de autenticación.
- Los roles se almacenan en sesión para evitar una query a base de datos en cada petición; se invalidan forzando `Session::regenerate()` tras cambios de rol.
- El **plain token** se devuelve **solo una vez** en la respuesta de creación (`POST /api/v1/tokens`). Solo el hash SHA-256 (`token_hash`) se persiste en la tabla `api_tokens`. Si se pierde, hay que generar uno nuevo.
- `ApiAuthMiddleware` es exclusivo del pipeline `/api/v1`; las rutas web usan `AuthMiddleware` (sesión).
