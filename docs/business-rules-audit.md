# Auditoría de Reglas de Negocio, Validaciones y Excepciones

## Komorebi Café — Fase de Investigación

> **Fecha:** 2026-04-16
> **Propósito:** Mapa exhaustivo de reglas de negocio implementadas, brechas, inconsistencias de patrón
> y decisiones pendientes. Documento previo a cualquier corrección. No modifica código.
>
> **Leyenda de prioridades:**
> | Prioridad | Criterio |
> |-----------|----------|
> | **P1** | Bug en producción confirmado o vector de abuso real en el código existente |
> | **P2** | Inconsistencia de patrón que degrada mantenibilidad y testabilidad |
> | **P3** | Mejora defensiva deseable — no urgente |
>
> **Columnas:** Regla / Descripción | Implementación actual | Brecha o problema | Archivo (línea) | Prioridad

---

## 1. Dominio: Identidad y Acceso

### 1.1 Autenticación (`AuthService`)

| #    | Regla                               | Implementación actual                                                             | Brecha / Problema                                                                                                                                                                                        | Archivo (L)           | P  |
|------|-------------------------------------|-----------------------------------------------------------------------------------|----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|-----------------------|----|
| A-01 | Política de contraseña en registro  | Mínimo 8 chars (`mb_strlen`)                                                      | Sin restricción de complejidad (mayúscula, número, símbolo). Contraseñas tipo `aaaaaaaa` son válidas.                                                                                                    | `AuthService:122`     | P3 |
| A-02 | Trim de contraseña antes de validar | No se hace trim                                                                   | `" pass"` y `"pass"` son contraseñas distintas, pero el usuario puede no notarlo. Crear cuenta con espacios y luego no poder entrar.                                                                     | `AuthService:108-128` | P2 |
| A-03 | Auto-login tras registro            | Sesión creada inmediatamente al registrar                                         | No hay verificación de email antes del primer acceso. Si el proyecto requiere double opt-in (ya existe en Newsletter), el flujo es inconsistente. Decisión pendiente: ¿email verificado antes de acceso? | `AuthService:149-152` | P2 |
| A-04 | Logs sensibles en createSession     | `Logger::error` con user array y roles array condicionado a `APP_ENV === 'local'` | Nivel incorrecto (`error` vs `debug`). Si alguien cambia la condición accidentalmente, datos personales van a stdout en producción.                                                                      | `AuthService:202,218` | P1 |
| A-05 | Rate limiting de login              | Por IP y por email, excepto en `cli` y `testing`                                  | El bypass de CLI podría usarse si la app se expone vía CLI wrapper. Correcto para tests, pero documentar explícitamente.                                                                                 | `AuthService:344-348` | P3 |
| A-06 | Mensaje de error genérico en login  | `'Credenciales incorrectas.'` cuando usuario no existe                            | ✅ Correcto — no revela si el email existe. Documentar como decisión intencional.                                                                                                                         | `AuthService:386`     | —  |
| A-07 | Cuenta bloqueada temporalmente      | `isLocked()` + `lockoutMinutesRemaining()`                                        | ✅ Correcto. Verificar que el lockout se aplica también a intentos por email, no solo IP.                                                                                                                 | `AuthService:392-397` | —  |

### 1.2 Cuenta de Usuario (`UserAccountService`)

| #    | Regla                                 | Implementación actual                                                   | Brecha / Problema                                                                                                                                                            | Archivo (L)             | P  |
|------|---------------------------------------|-------------------------------------------------------------------------|------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|-------------------------|----|
| B-01 | Cambio de contraseña — lookup de hash | `$user['password_hash'] ?? $user['password'] ?? null`                   | Doble fallback indica dos columnas distintas en BD o modelo legacy no unificado. Si ambas existen y tienen valores distintos, el comportamiento es impredecible.             | `UserAccountService:53` | P1 |
| B-02 | Cambio de contraseña — política       | Mínimo 8 chars                                                          | Igual que A-01: sin complejidad. Inconsistente si algún día se añade complejidad solo en registro.                                                                           | `UserAccountService:42` | P3 |
| B-03 | Instancia de modelo legacy            | `$this->userModel->findById()` + fallback `$this->userRepo->findById()` | Viola DIP. Dos fuentes de verdad para el mismo dato. Si el modelo devuelve un array con columnas distintas al repositorio, los campos `password_hash` / `password` difieren. | `UserAccountService:47` | P2 |

### 1.3 Eliminación de Cuenta — GDPR (`AccountDeletionService`)

| #    | Regla                               | Implementación actual                                                                                                  | Brecha / Problema                                                                                                                                                                                             | Archivo (L)                    | P  |
|------|-------------------------------------|------------------------------------------------------------------------------------------------------------------------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|--------------------------------|----|
| C-01 | Soft delete + anonimización atómica | `deleted_at = NOW()`, `is_active = 0`, nombre → `'Usuario eliminado'`, email → `deleted_N@deleted.local`, phone → NULL | ✅ Atómica (transacción). Pero no destruye: sesiones activas en BD, tokens API (tabla `api_tokens`), entradas de waitlist pendientes, reservas futuras confirmadas, reseñas publicadas con contenido personal. | `AccountDeletionService:41-54` | P1 |
| C-02 | Verificación previa de existencia   | No verifica si el usuario existe antes del `UPDATE`                                                                    | Si `userId` no existe, el UPDATE afecta 0 filas pero retorna `Result::ok(true)` — éxito falso.                                                                                                                | `AccountDeletionService:41-44` | P1 |
| C-03 | Verificación de ya-eliminado        | No verifica si `deleted_at IS NOT NULL`                                                                                | Puede llamarse dos veces y responder `ok` en ambas. Idempotencia no garantizada.                                                                                                                              | `AccountDeletionService:41-44` | P2 |
| C-04 | Raw SQL en servicio                 | `$this->db->prepare(...)` directamente en el servicio                                                                  | Viola arquitectura de capas. Debería delegarse a `UserRepository`.                                                                                                                                            | `AccountDeletionService:41-54` | P2 |

### 1.4 Tokens API (`ApiTokenService`)

| #    | Regla                                   | Implementación actual                                                            | Brecha / Problema                                                                                                                             | Archivo (L)             | P  |
|------|-----------------------------------------|----------------------------------------------------------------------------------|-----------------------------------------------------------------------------------------------------------------------------------------------|-------------------------|----|
| D-01 | Validación de token — lookup de usuario | `SELECT id, name, email, is_active FROM users WHERE id = ?` directamente via PDO | Raw SQL en servicio. Viola DIP. Si las columnas de usuario cambian, hay que actualizar el servicio. Debería ser `UserRepository::findById()`. | `ApiTokenService:59-62` | P2 |
| D-02 | Validación de token — lookup de roles   | `SELECT r.code FROM user_roles ur JOIN roles r...` directamente via PDO          | Segunda raw query en el mismo método. Debería ser `UserModel::getRoles()` o similar.                                                          | `ApiTokenService:77-82` | P2 |
| D-03 | Token plain only once                   | Comentario `⚠ El plain token solo se devuelve aquí`                              | ✅ Correcto — SHA-256 en BD, plain solo en memoria. Bien documentado.                                                                          | `ApiTokenService:31-39` | —  |
| D-04 | Entropía del token                      | `random_bytes(32)` → 256 bits                                                    | ✅ Suficiente.                                                                                                                                 | `ApiTokenService:34`    | —  |

---

## 2. Dominio: Reservas y Disponibilidad

### 2.1 Creación de Reservas (`ReservationService`)

| #    | Regla                                            | Implementación actual                                                                                         | Brecha / Problema                                                                                                                                                                                             | Archivo (L)                       | P  |
|------|--------------------------------------------------|---------------------------------------------------------------------------------------------------------------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|-----------------------------------|----|
| E-01 | `cancel()` retorna `bool`                        | `public function cancel(...): bool`                                                                           | Viola el `Result` pattern. El `Shared\ReservationController` y `Api\ReservationController` ignoran el valor de retorno según el propio NOTE en el código. Cancelaciones fallidas pasan silenciosas.           | `ReservationService:213`          | P1 |
| E-02 | Campo `guests` — doble restricción no coordinada | `validateFormats`: hardcoded `$guests < 1 \|\| $guests > 10`; `validatePassCompatibility`: `$pass['max_pax']` | Si un pase tiene `max_pax = 6`, el límite es 6. Pero si `max_pax = 15`, el servicio bloquea a 10 sin mensaje claro de por qué. Las dos restricciones deberían ser conscientes la una de la otra.              | `ReservationService:306, 373-381` | P2 |
| E-03 | Fecha pasada — race condition                    | `strtotime("$date $time:00") < time()`                                                                        | Si el usuario envía una reserva exactamente en el límite del segundo actual, puede pasar la validación y quedar con una reserva en el pasado. Debería añadir margen de seguridad (+5 min mínimo desde ahora). | `ReservationService:311-313`      | P2 |
| E-04 | `validateRequired` acepta `0` como válido        | `$data[$field] === ''` — solo bloquea strings vacíos                                                          | `user_id = 0`, `cafe_id = 0`, `guests = 0` pasan la validación de "requerido" y llegan a los casteos. `(int) 0` es técnicamente falsy pero válido para `isset`.                                               | `ReservationService:282`          | P1 |
| E-05 | Debug log de pase en producción                  | `Logger::debug(...)` con `pass_name`, `target_cafe_types`, `target_animal_types`, `is_active`                 | Sin guard de entorno. En producción con driver de log verbose, filtra datos de configuración del pase a stdout.                                                                                               | `ReservationService:108-115`      | P2 |
| E-06 | Email/PDF de confirmación falla silenciosamente  | Try/catch amplio que logea pero no falla la reserva                                                           | ✅ Correcto por diseño (la reserva no debe fallar por un error de email). Documentar explícitamente como decisión de negocio.                                                                                  | `ReservationService:515-554`      | —  |
| E-07 | Reserva duplicada                                | `existsForUserAndDateTime` verifica user+cafe+fecha+hora                                                      | ✅ Correcto. Pero solo bloquea mismo usuario en mismo café/fecha/hora. Si el café tiene capacidad 1, dos usuarios distintos podrían crear conflicto — resuelto por `hasAvailableCapacity`.                     | `ReservationService:129`          | —  |

### 2.2 Disponibilidad (`AvailabilityService`)

| #    | Regla                                                 | Implementación actual                                                | Brecha / Problema                                                                                                                        | Archivo (L)                                               | P  |
|------|-------------------------------------------------------|----------------------------------------------------------------------|------------------------------------------------------------------------------------------------------------------------------------------|-----------------------------------------------------------|----|
| F-01 | Validación de pax duplicada                           | `AvailabilityService` valida `guests < min_pax` y `guests > max_pax` | La misma lógica existe en `ReservationService::validatePassCompatibility`. Si cambian las reglas de pax, hay que actualizar dos lugares. | `AvailabilityService:72-79`, `ReservationService:375-381` | P2 |
| F-02 | Rango de fechas futuras — `maxDaysAhead` configurable | Constructor acepta `int $maxDaysAhead = 30`                          | ✅ Bien parametrizado. Verificar que el valor en producción esté en env y no hardcoded en el `ServiceProvider`.                           | `AvailabilityService:21`                                  | P3 |

### 2.3 Waitlist (`WaitlistService`)

| #    | Regla                                                            | Implementación actual                                                     | Brecha / Problema                                                                                                                                       | Archivo (L)               | P  |
|------|------------------------------------------------------------------|---------------------------------------------------------------------------|---------------------------------------------------------------------------------------------------------------------------------------------------------|---------------------------|----|
| G-01 | `guest_count` sin validación de rango                            | `$data['guest_count'] ?? 1` — acepta cualquier valor                      | `guest_count = 0`, `-5` o `999` son válidos. No hay guardia de rango mínimo/máximo.                                                                     | `WaitlistService:97`      | P1 |
| G-02 | Gestión manual de transacciones dentro de `TransactionalService` | `if (!$this->db->inTransaction()) { $this->db->beginTransaction(); }`     | `WaitlistService` extiende `TransactionalService` pero bypassea su helper `transact()`. Patrón inconsistente y propenso a dejar transacciones abiertas. | `WaitlistService:156-159` | P2 |
| G-03 | Email de notificación falla silenciosamente                      | Try/catch en envío de email                                               | ✅ Correcto por diseño (unirse a la waitlist no debe fallar por email). Consistente con E-06.                                                            | `WaitlistService:130-133` | —  |
| G-04 | Token de waitlist — entropía                                     | `bin2hex(random_bytes(16))` → 128 bits                                    | Suficiente para token de confirmación de 15 min. Aceptable.                                                                                             | `WaitlistService:87`      | —  |
| G-05 | Timeout de respuesta configurable                                | `$data['response_timeout_minutes'] ?? Waitlist::DEFAULT_RESPONSE_TIMEOUT` | ✅ Correcto. Verificar que `DEFAULT_RESPONSE_TIMEOUT` esté documentado en la constante.                                                                  | `WaitlistService:88`      | —  |

### 2.4 Carrito (`CartService`)

| #    | Regla                                        | Implementación actual                                                                             | Brecha / Problema                                                                                                      | Archivo (L)           | P  |
|------|----------------------------------------------|---------------------------------------------------------------------------------------------------|------------------------------------------------------------------------------------------------------------------------|-----------------------|----|
| H-01 | Producto no encontrado — silencio total      | Si `!$product` o `product_type !== 'item'`, retorna el carrito sin cambios y sin indicar el error | El caller no sabe si la operación tuvo éxito o falló. Un usuario que añade un producto desactivado no recibe feedback. | `CartService:191-196` | P1 |
| H-02 | `CartService` no implementa `Result` pattern | Todos los métodos retornan `array`                                                                | Inconsistente con el resto de servicios. No hay forma de distinguir "carrito vacío" de "operación fallida".            | `CartService:37-340`  | P2 |
| H-03 | Límites del carrito documentados             | `MAX_QTY_PER_ITEM = 99`, `MAX_UNIQUE_ITEMS = 50`                                                  | ✅ Límites razonables y como constantes. Verificar que se comunican al usuario cuando se alcanzan (ver H-01).           | `CartService:21-22`   | —  |
| H-04 | Solo `product_type = 'item'` en carrito      | Validación en `updateItem`                                                                        | ✅ Los pases no pueden añadirse al carrito. Correcto.                                                                   | `CartService:192`     | —  |

---

## 3. Dominio: Contenido y Social

### 3.1 Reseñas (`ReviewService`)

| #    | Regla                                  | Implementación actual                                                       | Brecha / Problema                                                                                                                                                                                                                                      | Archivo (L)                   | P  |
|------|----------------------------------------|-----------------------------------------------------------------------------|--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|-------------------------------|----|
| I-01 | Longitud de título/body con `strlen`   | `strlen(trim($title)) < 3 \|\| strlen(trim($title)) > 100`                  | **Bug real**: `strlen` cuenta bytes, no caracteres. Un título japonés de 3 kanji (`東京カフェ`) mide 9 bytes — pasa el mínimo. Pero `strlen("é") = 2` — "éé" (2 chars) = 4 bytes, pasa el mínimo de 3 bytes pero tiene solo 2 caracteres. Usar `mb_strlen`. | `ReviewService:71,75,149,153` | P1 |
| I-02 | `htmlspecialchars` antes de persistir  | `$title = htmlspecialchars($title, ENT_QUOTES, 'UTF-8')` antes del `INSERT` | **Bug real**: la BD almacena HTML escapado (`&amp;`, `&lt;`). Al renderizar la vista, el motor de plantillas vuelve a escapar → doble escape visible al usuario. La sanitización debe hacerse en la capa de presentación, no en persistencia.          | `ReviewService:80-81,158-159` | P1 |
| I-03 | Sin límite de reseñas por usuario/café | No hay guardia                                                              | Un usuario puede crear N reseñas para el mismo café. Sin regla documentada de "una reseña por usuario por café", es abusable.                                                                                                                          | `ReviewService:46-107`        | P2 |
| I-04 | Edición fuerza re-moderación           | `status = 'pending'` en cada edición                                        | ✅ Correcto — cualquier edición vuelve a revisión. Documentar como política intencional.                                                                                                                                                                | `ReviewService:166`           | —  |
| I-05 | Propiedad verificada en edición        | `(int) $review['user_id'] !== $userId`                                      | ✅ Correcto. Pero el mensaje `'No puedes editar esta reseña'` no distingue "no existe" de "no es tuya" (por seguridad). Correcto.                                                                                                                       | `ReviewService:140-142`       | —  |

### 3.2 Newsletter (`NewsletterService`)

| #    | Regla                                | Implementación actual                                                                  | Brecha / Problema                                                                                                    | Archivo (L)               | P  |
|------|--------------------------------------|----------------------------------------------------------------------------------------|----------------------------------------------------------------------------------------------------------------------|---------------------------|----|
| J-01 | Retorna `array` en lugar de `Result` | `public function subscribe(string $email): array`                                      | Único servicio que no implementa el `Result` pattern. Rompe el contrato uniforme de la capa de servicios.            | `NewsletterService:37`    | P2 |
| J-02 | Token de confirmación sin expiración | `INSERT INTO newsletter_subscriptions (email, token) VALUES (?, ?)` — sin `expires_at` | Un token de confirmación puede usarse días después de ser generado. Sin TTL, los tokens son válidos indefinidamente. | `NewsletterService:68`    | P2 |
| J-03 | Sin rate limiting en suscripción     | No hay guardia contra llamadas masivas                                                 | Un bot puede enviar miles de emails de confirmación a direcciones ajenas. Añadir rate limit por IP o por email.      | `NewsletterService:37-80` | P3 |
| J-04 | Double opt-in implementado           | Token enviado por email antes de confirmar                                             | ✅ Correcto y GDPR-compliant para suscripción. Inconsistente con el flujo de registro de usuarios (A-03).             | `NewsletterService:50-80` | —  |
| J-05 | Reactivación de suscripción          | Si `unsubscribed_at` → nuevo token y `unsubscribed_at = NULL`                          | ✅ Correcto. El usuario debe re-confirmar.                                                                            | `NewsletterService:56-58` | —  |

### 3.3 Productos (`ProductService`)

| #    | Regla                  | Implementación actual                                    | Brecha / Problema                                                                                                                             | Archivo (L)            | P  |
|------|------------------------|----------------------------------------------------------|-----------------------------------------------------------------------------------------------------------------------------------------------|------------------------|----|
| K-01 | Raw SQL en `getAll()`  | `$this->db->query('SELECT p.*, c.name...')` directamente | Viola arquitectura de capas. La lógica de query pertenece a `ProductRepository`.                                                              | `ProductService:52-59` | P2 |
| K-02 | Cache invalidation     | `Cache::set($cacheKey, $products, 3600)` — 1 hora        | Clave `products:all` se invalida por tiempo, no por evento. Si se crea/edita un producto, la cache puede servir datos obsoletos hasta 1 hora. | `ProductService:61-62` | P2 |
| K-03 | Paginación con límites | `$perPage = min(100, max(1, $perPage))`                  | ✅ Límites correctos. Protege contra `perPage=99999`.                                                                                          | `ProductService:79`    | —  |

---

## 4. Dominio: Fidelización y Gamificación

### 4.1 Sellos y Tiers (`LoyaltyService`)

| #    | Regla                                                      | Implementación actual                                                                        | Brecha / Problema                                                                                                                                                                                                                                               | Archivo (L)                             | P  |
|------|------------------------------------------------------------|----------------------------------------------------------------------------------------------|-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|-----------------------------------------|----|
| L-01 | `addStamp` acepta `$stamps <= 0`                           | `public function addStamp(int $userId, int $stamps = 1, ...)` sin guardia de rango           | `addStamp($userId, 0)` o `addStamp($userId, -5)` no falla — aplica 0 o resta sellos silenciosamente si el modelo no lo protege.                                                                                                                                 | `LoyaltyService:68`                     | P1 |
| L-02 | Sin límite diario de sellos                                | No hay control de frecuencia por usuario/día                                                 | Un script puede llamar `addStamp` cientos de veces y acumular sellos ilimitados. Sin reservationId verificado como "ya usó sello", es un vector de abuso.                                                                                                       | `LoyaltyService:68-119`                 | P2 |
| L-03 | `redeemReward` sin protección anti-doble-canje concurrente | La transacción protege serialización, pero no hay `SELECT ... FOR UPDATE` en `consumeStamps` | Dos peticiones concurrentes del mismo usuario podrían pasar la verificación de sellos antes de que la primera consuma. Necesita lock de fila en `loyalty_cards`.                                                                                                | `LoyaltyService:155-229`                | P2 |
| L-04 | `$tierOrder` hardcoded en dos lugares distintos            | `['bronze'=>1,'silver'=>2,'gold'=>3,'platinum'=>4]` inline                                   | Si se añade un nuevo tier, hay que actualizar dos o más lugares. Debe ser una constante de clase o enum compartido.                                                                                                                                             | `LoyaltyService:180, calculateTier:131` | P2 |
| L-05 | Tiers definidos en docblock, no en código                  | Comentario: `Bronze (0-9), Silver (10-29), Gold (30-49), Platinum (50+)`                     | Los umbrales están en `calculateTier` como literales `10`, `30`, `50` — no como constantes. Si se cambia un umbral, hay que buscar los números mágicos.                                                                                                         | `LoyaltyService:131-142`                | P2 |
| L-06 | Lazy init de modelos via nullable props                    | `private ?LoyaltyCard $loyaltyCardModel = null` con getter `??=`                             | Anti-patrón de inyección. Los modelos deberían inyectarse en el constructor. Dificulta el testing (no se pueden mockear).                                                                                                                                       | `LoyaltyService:29-57`                  | P2 |
| L-07 | Recompensa con `validity_days = 0`                         | `$expiresAt = date('Y-m-d H:i:s', strtotime("+0 days"))`                                     | Si el catálogo tiene `validity_days = 0` por error, la recompensa expira en el momento del canje. Sin guardia mínima de días.                                                                                                                                   | `LoyaltyService:208-209`                | P2 |
| L-08 | Tier update tras addStamp usa `visits_count` no `stamps`   | `$newVisitsCount = (int) $card['visits_count'] + $stamps`                                    | El tier se calcula sobre visitas, los sellos sobre `stamps`. Si se añaden sellos sin reserva (bonus admin), `visits_count` no aumenta pero los sellos sí. Los tiers se calcularían sobre visitas reales — correcto, pero la desincronía debe estar documentada. | `LoyaltyService:84-88`                  | P3 |

---

## 5. Dominio: Operaciones y Staff

### 5.1 Turnos de Staff (`StaffShiftService`)

| #    | Regla                                  | Implementación actual                                             | Brecha / Problema                                                                                                          | Archivo (L)                  | P  |
|------|----------------------------------------|-------------------------------------------------------------------|----------------------------------------------------------------------------------------------------------------------------|------------------------------|----|
| M-01 | Sin validación `$start < $end`         | `assignShift` solo llama `hasOverlap` en el repo                  | Si `$start = '18:00'` y `$end = '08:00'`, se crea un turno inválido (o que cruza medianoche sin advertencia).              | `StaffShiftService:53-78`    | P1 |
| M-02 | Sin validación de formato de hora      | `$start` y `$end` son strings libres                              | Un caller puede pasar `'8am'`, `'noon'`, `''`. El repositorio probablemente falla silenciosamente o con error de BD opaco. | `StaffShiftService:53`       | P1 |
| M-03 | Sin validación de fecha pasada         | No verifica que `$date >= today`                                  | Se pueden crear turnos en el pasado, contaminando el historial y el calendario.                                            | `StaffShiftService:53`       | P2 |
| M-04 | Sin `#[\Override]` en métodos públicos | `getWeekShifts`, `getStaffHistory`, `assignShift` sin el atributo | Viola el patrón obligatorio del proyecto para todo método que implementa interfaz.                                         | `StaffShiftService:27,40,53` | P2 |

### 5.2 Animales (`AnimalCareService`)

| #    | Regla                               | Implementación actual                                       | Brecha / Problema                                                                                                                                            | Archivo (L)               | P  |
|------|-------------------------------------|-------------------------------------------------------------|--------------------------------------------------------------------------------------------------------------------------------------------------------------|---------------------------|----|
| N-01 | `age_years` sin validación de rango | `$data['age_years'] ?? null` — sin guardia                  | Una edad negativa o irreal (ej. `age = -3`, `age = 500`) pasa sin error.                                                                                     | `AnimalCareService:78`    | P2 |
| N-02 | `cafe_id` puede ser null            | `$data['cafe_id'] ?? null` insertado en BD                  | Si `cafe_id` tiene constraint `NOT NULL` en la migración, produce un error de BD opaco. Si es nullable, un animal sin café es un estado inválido de negocio. | `AnimalCareService:74`    | P2 |
| N-03 | Raw SQL en servicio                 | `$this->db->prepare('INSERT INTO animals...')` directamente | Viola arquitectura de capas. Debería delegarse a `AnimalRepository`.                                                                                         | `AnimalCareService:69-80` | P2 |

### 5.3 Gestión de Usuarios Admin (`UserManagementService`)

| #    | Regla                                        | Implementación actual                                        | Brecha / Problema                                                                                                                                                             | Archivo (L)                     | P  |
|------|----------------------------------------------|--------------------------------------------------------------|-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|---------------------------------|----|
| O-01 | `strlen` en lugar de `mb_strlen` para nombre | `strlen($data['name']) < 2 \|\| strlen($data['name']) > 100` | Bug con nombres multibyte. `"日本語"` = 3 chars pero 9 bytes — pasa `strlen > 2` pero si fuera el límite inferior sería incorrecto. Usar `mb_strlen`.                            | `UserManagementService:88`      | P1 |
| O-02 | Instancia directa de `User` model            | `$this->userModel = new User($db)` en constructor            | Viola DIP. El modelo no puede ser mockeado en tests unitarios.                                                                                                                | `UserManagementService:32`      | P2 |
| O-03 | Raw SQL en `getUsersWithRoles`               | `$this->db->query("SELECT u.id, ...")` directamente          | Viola arquitectura de capas.                                                                                                                                                  | `UserManagementService:44-60`   | P2 |
| O-04 | Raw SQL en `createUser`                      | `$this->db->prepare('INSERT INTO users...')` directamente    | Viola arquitectura de capas.                                                                                                                                                  | `UserManagementService:137-148` | P2 |
| O-05 | Errores de validación en `Result::fail` data | `Result::fail('...', 'validation', ['errors' => $errors])`   | Inconsistente: otros servicios usan `ValidationException`. Los errores por campo quedan en `$result->data['errors']` — los controllers deben saber este contrato no estándar. | `UserManagementService:108`     | P2 |

---

## 6. Inconsistencias Transversales de Contratos

### 6.1 Result Pattern — Uso mezclado

| #    | Inconsistencia                                               | Patrón canónico                                                         | Violaciones encontradas                                                                                                                                                                      | P  |
|------|--------------------------------------------------------------|-------------------------------------------------------------------------|----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|----|
| T-01 | `$result->ok` vs `$result->isOk()` / `$result->isFail()`     | El patrón del proyecto usa `$result->ok` (propiedad pública)            | `UserManagementService:125` usa `isFail()`; `WaitlistService:65` usa `isOk()`; el resto usa `->ok` o la propiedad directamente. Inconsistencia sistémica.                                    | P2 |
| T-02 | `Result::fail` 4° parámetro `$data` con semánticas distintas | Firma: `fail(string $error, string $code, mixed $data, array $context)` | `UserManagementService:108` lo usa para pasar errores de campo `['errors' => $errors]`; en otros lugares `$data` es null siempre. El parámetro es confuso y su contrato no está documentado. | P2 |
| T-03 | `cancel()` retorna `bool` en lugar de `Result`               | Todos los métodos de servicio retornan `Result`                         | `ReservationService::cancel(): bool` — único método de mutación que rompe el patrón. El NOTE en el código confirma que los controllers ignoran el retorno.                                   | P1 |
| T-04 | `NewsletterService::subscribe` retorna `array`               | Todos los servicios retornan `Result`                                   | El método retorna `['success' => bool, 'message' => string]` — la única excepción al patrón de servicios.                                                                                    | P2 |
| T-05 | `ReviewService::updateReview` retorna `Result::ok(string)`   | `Result::ok` debe contener datos estructurados (array con ID o similar) | `Result::ok('Reseña actualizada exitosamente')` pasa un string como data; `createReview` retorna `Result::ok(['id' => $reviewId])`. Tipos de data inconsistentes entre create y update.      | P2 |

### 6.2 Sanitización vs Escapado

| #    | Inconsistencia                        | Correcto                                                                        | Actual                                                                                                           | Archivo                       | P  |
|------|---------------------------------------|---------------------------------------------------------------------------------|------------------------------------------------------------------------------------------------------------------|-------------------------------|----|
| S-01 | `htmlspecialchars` antes de persistir | El escapado debe ocurrir en la capa de presentación (vista), no en persistencia | `ReviewService` escapa título y body antes del INSERT. La BD contiene `&amp;` en lugar de `&`.                   | `ReviewService:80-81,158-159` | P1 |
| S-02 | `mb_strlen` para strings multibyte    | Usar siempre `mb_strlen` para validar longitud de strings de usuario            | `ReviewService:71,75,149,153`, `UserManagementService:88`, `AnimalCareService` — usan `strlen` (bytes, no chars) | Múltiples                     | P1 |

### 6.3 Arquitectura de capas — Raw SQL fuera de repositorios

| #    | Servicio                 | Método                                                | Debería delegarse a                                           |
|------|--------------------------|-------------------------------------------------------|---------------------------------------------------------------|
| R-01 | `ApiTokenService`        | `validate()` — 2 queries                              | `UserRepository::findById()`, `UserModel::getRoles()`         |
| R-02 | `AccountDeletionService` | `deleteAndAnonymize()` — 2 queries                    | `UserRepository::softDelete()`, `UserRepository::anonymize()` |
| R-03 | `ProductService`         | `getAll()`                                            | `ProductRepository::findAll()`                                |
| R-04 | `UserManagementService`  | `getUsersWithRoles()`, `createUser()`, `updateUser()` | `UserRepository`                                              |
| R-05 | `AnimalCareService`      | `createAnimal()`                                      | `AnimalRepository`                                            |
| R-06 | `AuthService`            | `createSession()` — `UPDATE users SET updated_at`     | `UserRepository::touchUpdatedAt()`                            |

> **Impacto:** Las raw queries en servicios impiden mockear la capa de datos en tests unitarios, duplican
> lógica que ya existe (o debería existir) en repositorios, y rompen el contrato de la arquitectura MVC
> documentada en `AGENTS.md`.

---

## 7. Decisiones de Negocio Pendientes

Las siguientes reglas **no tienen implementación** ni documentación — requieren una decisión explícita antes de poder
corregirse:

| #    | Pregunta                                                                                    | Dominio        | Impacto si no se decide                                                                                 | P  |
|------|---------------------------------------------------------------------------------------------|----------------|---------------------------------------------------------------------------------------------------------|----|
| Q-01 | ¿Un usuario puede escribir más de una reseña por café?                                      | Reviews        | Si no, añadir `UNIQUE(user_id, cafe_id)` en BD y guardia en servicio                                    | P2 |
| Q-02 | ¿El registro requiere verificación de email antes del primer acceso?                        | Auth           | Si sí, implementar email-verify flow (ya existe infraestructura en `EmailVerificationService`)          | P2 |
| Q-03 | ¿La política de contraseñas requiere complejidad (mayúscula + número + símbolo)?            | Auth           | Si sí, añadir regex en `AuthService` y `UserAccountService`                                             | P3 |
| Q-04 | ¿Cuál es el máximo de sellos diarios por usuario?                                           | Loyalty        | Sin este límite, `addStamp` es un vector de abuso por scripts                                           | P2 |
| Q-05 | ¿Una reserva cancelada o una cuenta borrada debe invalidar los sellos de loyalty asociados? | Loyalty + GDPR | Impacta el flujo de `AccountDeletionService` y `cancel()`                                               | P1 |
| Q-06 | ¿Los turnos de staff pueden cruzar medianoche (18:00 → 02:00)?                              | Staff          | Sin decidir, la validación `$start < $end` puede ser incorrecta para turnos nocturnos                   | P2 |
| Q-07 | ¿Un animal sin `cafe_id` es un estado válido (animal en cuarentena, de paso)?               | Animal Care    | Si sí, la columna debe ser nullable y documentado; si no, requiere validación obligatoria               | P2 |
| Q-08 | ¿Cuántos días de antelación máxima se permite reservar?                                     | Reservas       | `AvailabilityService` tiene `maxDaysAhead = 30` como default — ¿es política de negocio o valor técnico? | P3 |

---

## 8. Resumen Ejecutivo por Prioridad

### P1 — Bugs de producción o vectores de abuso (11 hallazgos)

| ID   | Descripción breve                                                                     | Servicio                 |
|------|---------------------------------------------------------------------------------------|--------------------------|
| A-04 | `Logger::error` con datos sensibles de usuario en createSession                       | `AuthService`            |
| B-01 | Doble fallback `password_hash`/`password` — comportamiento impredecible               | `UserAccountService`     |
| C-01 | GDPR: sesiones, tokens API, waitlist y reservas futuras no limpiadas al borrar cuenta | `AccountDeletionService` |
| C-02 | `deleteAndAnonymize` retorna `ok` aunque el usuario no exista                         | `AccountDeletionService` |
| E-01 | `cancel()` retorna `bool` ignorado — cancelaciones fallidas pasan silenciosas         | `ReservationService`     |
| E-04 | `user_id=0`, `cafe_id=0`, `guests=0` pasan validación de "requerido"                  | `ReservationService`     |
| G-01 | `guest_count` en waitlist sin validación de rango (acepta 0, -5, 999)                 | `WaitlistService`        |
| H-01 | `CartService::updateItem` silencia error sin feedback al caller                       | `CartService`            |
| I-01 | `strlen` en lugar de `mb_strlen` — bug real con texto japonés/emojis en reseñas       | `ReviewService`          |
| I-02 | `htmlspecialchars` antes de persistir → doble escape al renderizar                    | `ReviewService`          |
| M-01 | `assignShift` sin validar `$start < $end` — crea turnos inválidos                     | `StaffShiftService`      |
| M-02 | `assignShift` sin validar formato de hora — error opaco de BD                         | `StaffShiftService`      |
| L-01 | `addStamp` acepta `$stamps <= 0` — manipulación de saldo de sellos                    | `LoyaltyService`         |
| O-01 | `strlen` para nombre en admin — bug con nombres multibyte                             | `UserManagementService`  |

### P2 — Inconsistencias de patrón (23 hallazgos)

> Ver secciones 1-6 arriba. Incluyen: raw SQL en servicios (6), `Result` pattern mezclado (5), DIP violado (4),
> validaciones ausentes (4), patrón transaccional inconsistente (2), `#[\Override]` ausente (1), cache invalidation (1).

### P3 — Mejoras defensivas (6 hallazgos)

> A-01, A-05, B-02, F-02, J-03, Q-03, Q-08 — ninguno es urgente.

---

## 9. Próximos Pasos

Con este documento como mapa, el siguiente plan de trabajo debe:

1. **Resolver primero las Q-0x pendientes** (sección 7) — algunas bloquean si un P1 debe corregirse de una forma u otra.
2. **Crear plan de fixes ordenado**: P1 primero por dominio (Auth → Reservas → Reviews → Loyalty → Staff).
3. **Cada corrección de P1 acompañada de test** que cubra el caso de borde descubierto.
4. **Las correcciones de P2 de raw SQL** se agrupan en un sprint separado de refactoring de arquitectura.
5. **Las P3** se añaden como issues en el backlog, no al plan de fixes inmediatos.

---

## 10. Vulnerabilidades de Capa HTTP / Controller (nuevas)

> ⚠ **Estas son las que un QA agresivo o tribunal va a intentar primero.** No son brechas de patrón —
> son bugs de seguridad reales que producen comportamiento incorrecto o explotable desde el navegador.

### 10.1 IDOR y Autorización de Recursos

| #    | Vulnerabilidad                                         | Descripción técnica                                                                                                                                                                                                                                                                  | Cómo reproducirlo                                                                                 | Archivo (L)                              | P  |
|------|--------------------------------------------------------|--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|---------------------------------------------------------------------------------------------------|------------------------------------------|----|
| V-01 | **IDOR CRÍTICO: `user_id` desde el body en waitlist**  | `WaitlistController::join()` acepta `user_id` del cuerpo JSON del request, no de la sesión. El endpoint está en el grupo público `/api/v1` SIN middleware de autenticación. Cualquier persona puede unirse a la waitlist haciéndose pasar por otro usuario.                          | `POST /api/v1/waitlist/join` con `{"user_id": 42, "time_slot_id": 1}` sin estar autenticado       | `WaitlistController:59-100`, `routes:86` | P1 |
| V-02 | **IDOR: `revokeSession` sin verificar propiedad**      | `revokeSession(userId, sessionId)` pasa el `sessionId` de la URL como entero directamente al servicio. Si `revokeSessionForUser` filtra por `userId` en BD → correcto. Si no filtra → un usuario puede revocar la sesión de otro con un ID conocido. Verificar el WHERE en el query. | `POST /account/sessions/revoke/999` con una sesión activa                                         | `AccountController:88-104`               | P1 |
| V-03 | **`cancel()` bool ignorado → éxito falso garantizado** | `ReservationController::cancel()` llama `$this->reservationService->cancel(...)` pero **ignora el valor de retorno** `bool`. Siempre muestra `Flash::success('Reserva cancelada correctamente')` aunque la cancelación fallara silenciosamente.                                      | Cancelar una reserva que no existe o ya fue cancelada → el sistema dice "cancelada correctamente" | `ReservationController:231-235`          | P1 |

### 10.2 Bypass de Autenticación por `requireAuth()` Ignorado

| #    | Vulnerabilidad                                      | Descripción técnica                                                                                                                                                                                                                                                                                                                                  | Cómo reproducirlo                                                    | Archivo (L)                 | P  |
|------|-----------------------------------------------------|------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|----------------------------------------------------------------------|-----------------------------|----|
| V-04 | **`deleteAccount()` ignora retorno de requireAuth** | `AccountController::deleteAccount()` llama `$this->requireAuth()` pero no hace `return` del resultado. Si el usuario no está autenticado, `requireAuth()` retorna un redirect, pero el controller **continúa ejecutando** y llama a `deleteAndAnonymize(Session::user()['id'])` donde `Session::user()` es null → crash o comportamiento indefinido. | Llamar `POST /account/delete` con CSRF válido pero sin sesión activa | `AccountController:224-225` | P1 |
| V-05 | **`uploadAvatar()` ignora retorno de requireAuth**  | Mismo patrón: `$this->requireAuth()` sin `return`. Un usuario no autenticado puede intentar subir un avatar. `Session::user()` devuelve null → `(int) null['id']` → TypeError no controlado.                                                                                                                                                         | `POST /account/avatar/upload` sin sesión                             | `AccountController:252-254` | P1 |

### 10.3 Debug Logs en Controllers sin Guard de Entorno

| #    | Problema                                           | Descripción                                                                                                                                                                                                                                           | Archivo (L)                     | P  |
|------|----------------------------------------------------|-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|---------------------------------|----|
| V-06 | `Logger::debug` en `ReservationController::create` | `Logger::debug('Validaciones OK - Llamando a ReservationService::create()', [...])` con `cafe_id`, `pass_id`, `fecha`, `hora`, `personas`. Sin guard de entorno. En producción con driver verbose, estos datos van a stdout para cada reserva creada. | `ReservationController:155-161` | P2 |

### 10.4 Tipo de Autenticación en Grupos de Rutas

| #    | Problema                                             | Descripción                                                                                                                                                                                                                                                  | Archivo (L)     | P  |
|------|------------------------------------------------------|--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|-----------------|----|
| V-07 | Rutas `/api/v1/waitlist/*` en grupo público sin auth | `POST /api/v1/waitlist/join`, `GET /api/v1/waitlist/position/{token}` y `POST /api/v1/waitlist/confirm/{token}` están en el grupo con solo `$mw->cors()`. No requieren sesión ni token. Un usuario anónimo puede operar sobre la waitlist de cualquier otro. | `routes:75-94`  | P1 |
| V-08 | Cookie routes en grupo sin CSRF ni auth              | `POST /api/v1/cookies/accept`, `/reject`, `/update`, `/save-filters`, `/save-dietary` — no tienen CSRF ni auth. Cualquier script externo puede modificar las preferencias de cookie de un usuario si adivina su sesión.                                      | `routes:97-112` | P2 |

---

## 11. Análisis de Máquina de Estados — Transiciones Inválidas

> El tribunal intentará poner recursos en estados imposibles enviando peticiones en orden inesperado.

| # | Recurso | Estado inválido reproducible | Guardia actual | Brecha | Archivo (L)                     | P |
|-------|----------------|-------------------------------------------------------------------------------------------------------------|----------------------------------------------------------|------------------------------------------------------------------------------------------------------------------------------------------------------------------------|---------------------------------|
| SM-01 | Reserva | Cancelar una reserva ya cancelada o completada |
`$cancelableStates = ['pending', 'confirmed', 'active']` | ✅ Correcto — verifica estados válidos antes de cancelar. |
`ReservationController:225-228` | — |
| SM-02 | Reserva | Crear dos reservas idénticas simultáneamente (race condition entre `hasAvailableCapacity` check y
`INSERT`) | `existsForUserAndDateTime` antes del INSERT | Entre la verificación y el INSERT no hay lock de BD. Dos
peticiones concurrentes del mismo usuario pueden crear reservas duplicadas si llegan en el mismo ms. |
`ReservationService:124-162`    | P2 |
| SM-03 | Reseña | Crear una reseña para un café que el usuario nunca ha visitado | Ninguna | No hay verificación de si
el usuario tiene reservas completadas en ese café antes de permitir reseñar. Cualquier usuario registrado puede reseñar
cualquier café. | `ReviewService:46-107`          | P2 |
| SM-04 | Waitlist token | Confirmar un token de waitlist ya expirado o ya usado | Gestión en
`WaitlistService::confirmPromotion`           | Revisar si `confirmPromotion` verifica `expires_at` y que
`status != 'confirmed'`. Si no, el token es reutilizable indefinidamente. | `WaitlistController:159-180`    | P1 |
| SM-05 | Loyalty reward | Canjear una recompensa que ya fue usada (código `redemption_code` ya marcado como
`used`)                   | `LoyaltyService::validateRedemptionCode`                 | Revisar que `redeemReward` no
puede crear dos registros para la misma recompensa activa del usuario. El lock en `loyalty_cards` cubre sellos pero no
unicidad de tipo. | `LoyaltyService:152-229`        | P2 |
| SM-06 | Reserva | Check-in en una reserva futura (no para
hoy)                                                                |
`Reception\ReceptionController@checkIn`                  | Revisar si el endpoint de check-in valida que la reserva es
de hoy. Un recepcionista podría hacer check-in de reservas de mañana accidentalmente. |
`routes:363`                    | P2 |

---

## 12. Vectores de Abuso de Negocio (no exploits técnicos)

> El tribunal puede intentar hacer cosas "estúpidas pero válidas" que el sistema no debería permitir.

| #     | Vector                                                           | Escenario de abuso                                                                                                                                | Guardia actual                                  | Brecha                                                                                                                                                                | P  |
|-------|------------------------------------------------------------------|---------------------------------------------------------------------------------------------------------------------------------------------------|-------------------------------------------------|-----------------------------------------------------------------------------------------------------------------------------------------------------------------------|----|
| AB-01 | Reservar con el mismo pase para múltiples horarios del mismo día | Un usuario crea reservas para las 10:00, 12:00, 14:00, 16:00 en el mismo café el mismo día usando el mismo pase                                   | Solo evita mismo usuario+café+fecha+hora exacta | Si el pase tiene duración de 2h, la reserva de las 10:00 aún no terminó a las 11:00. No hay validación de solapamiento de reservas del mismo usuario.                 | P2 |
| AB-02 | Crear N cuentas para monopolizar la waitlist                     | Un atacante registra 50 cuentas (sin email verificado) y ocupa las 50 primeras posiciones de la waitlist de todos los slots                       | Ninguna                                         | Sin verificación de email y sin límite de cuentas por IP/email-dominio, la waitlist es monopolizable.                                                                 | P2 |
| AB-03 | Reseñas en masa con cuentas recién creadas                       | Crear 100 cuentas y dejar 100 reseñas de 5 estrellas (o 1 estrella) para un café para manipular la valoración media                               | Ninguna                                         | Sin verificación de visita y sin límite por cuenta, el sistema de reseñas es manipulable.                                                                             | P2 |
| AB-04 | Sellos de fidelización sin visita real                           | Un admin o recepcionista con acceso a `addStamp` puede acreditar sellos ilimitados a su propia cuenta o a la de un amigo                          | Solo verifica que el usuario/tarjeta existe     | Sin audit trail de quién llamó `addStamp` ni restricción de autoasignación, es un vector de fraude interno.                                                           | P2 |
| AB-05 | Exportar todos los reportes como admin                           | `GET /admin/reports/export` — sin límite de filas ni parámetros de rango de fechas en la URL                                                      | Desconocido                                     | Si exporta TODO sin filtro, una exportación puede generar un CSV con 100.000 filas y tirar el servidor.                                                               | P3 |
| AB-06 | Borrar cuenta y volver a registrar con el mismo email            | Usuario borra su cuenta (email anonimizado), luego intenta registrarse con el mismo email                                                         | Email → `deleted_N@deleted.local`               | ✅ El email original queda libre. El usuario puede re-registrarse. Esto puede ser intencional pero debería documentarse explícitamente como política.                  | —  |
| AB-07 | Loyalty: canjear recompensa con sellos negativos                 | Si `consumeStamps` no valida el saldo antes de decrementar, un canje con sellos suficientes podría ejecutarse parcialmente dejando saldo negativo | Verificación en `redeemReward` L169             | ✅ Verificación existe. Pero no hay `SELECT FOR UPDATE` — en concurrencia, dos canjes simultáneos pueden pasar la verificación antes de que el primero consuma (L-03). | P2 |

---

## 13. Validación de Entrada — Casos de Borde Extremos no Cubiertos

| #     | Input malicioso                          | Campo / endpoint                                           | Comportamiento esperado                | Comportamiento actual desconocido                                                                                                                                                     | P  |
|-------|------------------------------------------|------------------------------------------------------------|----------------------------------------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|----|
| CE-01 | `cafe_id = 999999999` (no existe)        | `POST /reservas/crear`                                     | 422 con mensaje claro                  | ✅ `getCafeOrFail` lanza `NotFoundException` → `Result::fail('not_found')`. Correcto.                                                                                                  | —  |
| CE-02 | `date = "2020-02-30"` (fecha imposible)  | `POST /reservas/crear`, `POST /api/v1/reservations/create` | 422 con mensaje de fecha inválida      | `validateFormats` solo valida formato regex `\d{4}-\d{2}-\d{2}`. Acepta `2020-02-30` como válida. `strtotime` la convierte en `2020-03-01`. La reserva se crearía para el 1 de marzo. | P1 |
| CE-03 | `time = "25:70"`                         | `POST /reservas/crear`                                     | 422                                    | Regex `\d{2}:\d{2}` acepta `25:70`. `timeToMinutes` devolvería 1570 min. Puede crear reservas en horarios que no existen.                                                             | P1 |
| CE-04 | `guests = "2; DROP TABLE reservations;"` | `POST /reservas/crear`                                     | 422                                    | `filter_var($body['personas'], FILTER_VALIDATE_INT)` → devuelve `false`. El controller lanza `ValidationException`. ✅ Correcto.                                                       | —  |
| CE-05 | Nombre con solo emojis `"😂😂😂"`        | `POST /registro`, `POST /admin/users`                      | Aceptado o rechazado con mensaje claro | `mb_strlen("😂😂😂") = 3` → pasa validación de nombre (mínimo 2). Almacenado en BD. Puede romper PDFs de facturas o emails si el renderizador no soporta emoji.                       | P2 |
| CE-06 | Email `"a@b"` (sin TLD)                  | `POST /registro`                                           | 422                                    | `filter_var($email, FILTER_VALIDATE_EMAIL)` acepta `"a@b"` como válido en PHP. Email inservible pero pasa la validación.                                                              | P2 |
| CE-07 | `reward_type = "../../../etc/passwd"`    | `POST /api/v1/loyalty/redeem`                              | 422 con reward no encontrada           | `getCatalogModel()->findByType($rewardType)` — si usa un query parametrizado, es seguro. Si usa string interpolación, es SQLi. Revisar.                                               | P1 |
| CE-08 | `date = "' OR 1=1 --"`                   | `GET /api/v1/time-slots/available`                         | 422                                    | Regex `\d{4}-\d{2}-\d{2}` en `AvailabilityService` bloquea esto. ✅ Correcto.                                                                                                          | —  |
| CE-09 | Token vacío `""` en waitlist confirm     | `POST /api/v1/waitlist/confirm/`                           | 404 o 422                              | Depende del router — si la ruta `/{token}` requiere al menos 1 char, es 404. Si acepta vacío, llega al controller con `""` y el servicio lo maneja. Revisar.                          | P2 |
| CE-10 | `special_requests` con 10.000 caracteres | `POST /reservas/crear`                                     | 422 o truncado                         | `$comentarios = trim(strip_tags(...))` — sin límite de longitud. Se inserta en BD truncado por la columna (`TEXT`), o si es `VARCHAR(500)` falla con PDO error opaco.                 | P2 |

---

## 14. Superficie de Subida de Archivos

| #    | Vector                                       | Descripción                                                                                                                               | Guardia actual                                                                                                                                                                          | Brecha                                                                                                                                                      | Archivo (L)                 | P  |
|------|----------------------------------------------|-------------------------------------------------------------------------------------------------------------------------------------------|-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|-------------------------------------------------------------------------------------------------------------------------------------------------------------|-----------------------------|----|
| F-01 | MIME type check con `finfo`                  | Usa `finfo(FILEINFO_MIME_TYPE)` sobre `tmp_name` — verifica el contenido real del archivo, no la extensión declarada                      | ✅ Correcto — no se puede bypassear con renombrar un `.php` a `.jpg`                                                                                                                     | —                                                                                                                                                           | `FileUploadService:200-205` | —  |
| F-02 | Extensión de archivo del nombre original     | `pathinfo($file['name'], PATHINFO_EXTENSION)` — usa el nombre provisto por el usuario para extraer la extensión                           | Lista blanca `['jpg','jpeg','png','webp']`                                                                                                                                              | ✅ Si la extensión no está en la lista blanca, falla. Correcto.                                                                                              | `FileUploadService:208-212` | —  |
| F-03 | Nombre generado para almacenamiento          | `user_{$userId}_{time}_{random}.{ext}` — extensión obtenida del nombre original pero se valida antes                                      | ✅ El nombre de almacenamiento es predecible para un `userId` conocido pero no adivinable por el `random` parte                                                                          | Un atacante que sepa el `userId` podría predecir el patrón de nombre pero no la parte aleatoria                                                             | `FileUploadService:74`      | P3 |
| F-04 | Directorio de uploads accesible públicamente | `storage/uploads/avatars/` y `storage/uploads/animals/` — ¿están dentro de `public/`?                                                     | Si están dentro de `public/`, cualquiera puede acceder a cualquier avatar con el nombre correcto                                                                                        | Si fuera del webroot (bajo `storage/`), necesita una ruta de servicio — verificar que exista                                                                | `FileUploadService:43-45`   | P1 |
| F-05 | `deleteOldUserAvatars` con glob pattern      | `glob("user_{$userId}_*.*")` — el `userId` es un int, no puede contener `..`, por lo que path traversal no aplica                         | ✅ Correcto.                                                                                                                                                                             | —                                                                                                                                                           | `FileUploadService:251`     | —  |
| F-06 | `getimagesize()` como validación extra       | Verifica que el archivo es una imagen PHP-procesable                                                                                      | ✅ Capa extra correcta. Algunos exploits de polyglot file (JPEG con PHP incrustado) pueden pasar `getimagesize` pero el MIME check de `finfo` los bloqua si el magic bytes no es imagen. | —                                                                                                                                                           | `FileUploadService:215-218` | —  |
| F-07 | Sin límite de uploads por usuario/tiempo     | Un usuario puede subir 1000 avatares en un loop (cada subida borra la anterior del disco, pero genera carga del procesador por el resize) | `deleteOldUserAvatars` antes del nuevo upload                                                                                                                                           | El delete es previo, no concurrent. En concurrencia: 100 requests paralelas crean 100 archivos antes de que ninguno borre los anteriores — disk exhaustion. | `FileUploadService:77-78`   | P3 |

---

## 15. RBAC — Verificación de Permisos por Rol

| #    | Problema                                                       | Descripción                                                                                                                                                                                                                                             | Archivo (L)      | P  |
|------|----------------------------------------------------------------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|------------------|----|
| R-01 | `str_ends_with($role, '_admin')` como bypass global            | `Middleware::role()` y `Middleware::can()` tienen: `if (str_ends_with($rStr, '_admin')) return;`. Cualquier rol con sufijo `_admin` tiene acceso total. Si existe el rol `kitchen_admin` en la BD (por typo), salta todo el RBAC.                       | `Middleware:115` | P1 |
| R-02 | `ownsCafe()` — verificar implementación                        | `$mw->ownsCafe()` se usa en rutas de manager/keeper. Si solo verifica `session.cafe_id == route.cafe_id` sin consulta BD, un manager puede cambiar su `cafe_id` en sesión y acceder a datos de otro café.                                               | `routes:292-312` | P1 |
| R-03 | Supervisor accede a `/admin/reservations/{id}` vía admin group | El grupo admin solo requiere rol `admin`. Pero `/supervisor/assignments` acepta `[admin, manager, supervisor]`. Si los endpoints de detalle de reservas en admin no verifican que el usuario sea `admin`, un supervisor con URL guessing puede acceder. | `routes:244-248` | P2 |
| R-04 | Manager accede a reviews de TODOS los cafés                    | `Manager\ReviewController` tiene `[$mw->auth(), $mw->role(['admin', 'manager'])]` sin `$mw->ownsCafe()`. Un manager puede ver y moderar reseñas de cafés que no son suyos.                                                                              | `routes:287-290` | P2 |
| R-05 | Keeper dashboard accesible a manager/supervisor sin `ownsCafe` | `GET /keeper/dashboard` tiene middleware `$mw->role(['admin', 'keeper', 'manager', 'supervisor'])` pero sin `$mw->ownsCafe()`. Cualquier manager ve el dashboard de animales de todos los cafés.                                                        | `routes:391-394` | P2 |

---

## 16. Resumen Ejecutivo Completo — Actualizado

### Nuevos P1 identificados en la capa HTTP/Controller (8 adicionales)

| ID    | Descripción                                                             | Vector                  |
|-------|-------------------------------------------------------------------------|-------------------------|
| V-01  | IDOR: `user_id` desde body en waitlist — endpoint público sin auth      | Suplantación de usuario |
| V-04  | `deleteAccount()` ignora retorno de `requireAuth()` — auth bypass       | Ejecución sin sesión    |
| V-05  | `uploadAvatar()` ignora retorno de `requireAuth()` — TypeError          | Crash / auth bypass     |
| V-07  | Rutas `/api/v1/waitlist/*` en grupo público sin ningún middleware       | Suplantación de usuario |
| SM-04 | Token de waitlist potencialmente reutilizable si no verifica estado     | Abuso de flujo          |
| CE-02 | `date = "2020-02-30"` aceptada — reserva creada en fecha incorrecta     | Datos corruptos en BD   |
| CE-03 | `time = "25:70"` aceptada — horario imposible validado como correcto    | Datos corruptos en BD   |
| CE-07 | `reward_type` sin sanitizar — posible SQLi si query no es parametrizada | Inyección               |
| R-01  | `str_ends_with($role, '_admin')` — bypass total de RBAC con rol typo    | Escalada de privilegios |
| R-02  | `ownsCafe()` — posible escalada horizontal si no consulta BD            | Acceso entre cafés      |
| F-04  | Directorio `storage/uploads/` potencialmente dentro de webroot          | Exposición de archivos  |

### Conteo total actualizado

| Prioridad | Hallazgos previos (secciones 1-9) | HTTP/controllers (10-15) | Auditoría profunda (18) | **Total** |
|-----------|-----------------------------------|--------------------------|-------------------------|-----------|
| **P1**    | 14                                | 11                       | 5                       | **30**    |
| **P2**    | 23                                | 14                       | 8                       | **45**    |
| **P3**    | 6                                 | 3                        | 3                       | **12**    |
| **Total** | **43**                            | **28**                   | **16**                  | **87**    |

---

## 17. Checklist de Verificación para la Defensa

> Lista de pruebas manuales que el tribunal puede ejecutar en < 5 minutos cada una.
> Usar para preparar o para demostrar que ya están corregidas.

```
□ V-01: curl -X POST /api/v1/waitlist/join con user_id=2 sin Cookie de sesión → debe devolver 401
□ V-04: POST /account/delete con CSRF válido pero sin sesión → no debe ejecutar deleteAndAnonymize
□ V-07: GET /api/v1/waitlist/position/{token_real} sin sesión → debe devolver 401
□ E-01: Cancelar reserva inexistente → no debe mostrar "cancelada correctamente"
□ CE-02: POST reserva con date="2023-02-30" → debe rechazar con mensaje de fecha inválida
□ CE-03: POST reserva con time="25:70" → debe rechazar con mensaje de hora inválida
□ I-02: Crear reseña con título "Café & <b>test</b>" → BD debe tener "Café & <b>test</b>", vista mostrar "Café & <b>test</b>" sin doble escape
□ I-01: Crear reseña con título "猫" (1 kanji, 3 bytes) → debe rechazar por mínimo 3 caracteres
□ R-01: Asignar rol "kitchen_admin" a un usuario → ese usuario no debe tener acceso admin
□ L-01: Llamar addStamp con stamps=-10 → debe rechazar o no modificar el saldo
□ C-02: Llamar deleteAndAnonymize con userId=999999 → debe retornar error, no ok(true)
□ G-01: POST /api/v1/waitlist/join con guest_count=-1 → debe rechazar
□ SM-04: Confirmar el mismo token de waitlist dos veces → segunda debe rechazar
□ AB-03: Crear 3 cuentas y dejar reseña en el mismo café desde cada una → verificar si el sistema lo permite
□ XR-01: POST a ruta con validación fallida → header "Referer: https://evil.com" → respuesta NO debe redirigir a evil.com
□ XR-02: GET /any + header "Referer: javascript:alert(1)" + llamada View::back() → no debe ejecutar JS
□ DEL-01: Sesión activa → admin desactiva el usuario → continuar navegando → sesión debe invalidarse (≤5 min TTL, pero si deleted_at hay que forzarlo inmediato)
□ ADM-02: POST /admin/usuarios/{id}/delete donde id = usuario admin propio → debe rechazar autodesactivación
□ NL-01: POST /newsletter/subscribe x50 rápido con emails únicos → debe activarse rate-limit
□ SESS-01: Verificar que `redirect_after_login` guardado en sesión no acepta URLs externas (https://evil.com)
```

---

## 18. Auditoría Profunda Final — Hallazgos Adicionales

> Segunda pasada sobre capas no completamente cubiertas: framework core, sesión, redirects, colas, newsletter, permisos
> de admin, y CSRF en la capa de excepción.

### 18.1 Open Redirect vía HTTP_REFERER (P1)

| #     | Regla                               | Implementación actual                                                                                            | Brecha / Problema                                                                                                                                                                                                                                                       | Archivo (L)            | P  |
|-------|-------------------------------------|------------------------------------------------------------------------------------------------------------------|-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|------------------------|----|
| XR-01 | Redirect seguro tras error 422      | `header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/'))` tras ValidationException                              | `HTTP_REFERER` es un header controlado por el atacante. Si el atacante envía `Referer: https://phishing.com` y provoca un error de validación (ej. CSRF inválido), la víctima es redirigida a ese sitio externo.                                                        | `ExceptionHandler:115` | P1 |
| XR-02 | `View::back()` — referer sanitizado | `$referer = $_SERVER['HTTP_REFERER'] ?? '/'; self::redirect($referer);`                                          | Mismo vector. `Referer: javascript:alert(1)` o `//evil.com` llevan al usuario fuera de la app sin ninguna comprobación. Todos los controllers que llamen `View::back()` heredan este bug.                                                                               | `View:226-228`         | P1 |
| XR-03 | `redirect_after_login` — validación | `Session::set('redirect_after_login', $_SERVER['REQUEST_URI'] ?? '/')` en ExceptionHandler::handleAuthentication | El valor guardado en sesión se usa después del login para redirigir. Si un atacante forja la URL (`/auth/login?redirect=https://evil.com`) o manipula `REQUEST_URI`, puede secuestrar el redirect post-login. Verificar si AuthController valida que sea path relativo. | `ExceptionHandler:164` | P1 |

### 18.2 Sesión de Usuario Eliminado Nunca Invalidada (P1)

| #      | Regla                                         | Implementación actual                                                                                                              | Brecha / Problema                                                                                                                                                                                                                                                                              | Archivo (L)        | P  |
|--------|-----------------------------------------------|------------------------------------------------------------------------------------------------------------------------------------|------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|--------------------|----|
| DEL-02 | Eliminación de usuario invalida sesión activa | `fetchUserFromDb` retorna `null` si el usuario no existe → condición `if (is_array($user) && !($user['is_active']))` no se ejecuta | Si un admin elimina un usuario de la BD (hard delete o borra `users.id`), la sesión de ese usuario sobrevive indefinidamente. El check TTL de 5 min solo invalida si `is_active=0`, no si la fila no existe. Un usuario borrado puede seguir navegando el tiempo que dure su cookie de sesión. | `Middleware:88-98` | P1 |

### 18.3 Admin puede Desactivarse a sí Mismo (P2)

| #      | Regla                                     | Implementación actual                                                                                      | Brecha / Problema                                                                                                                                                                                                                     | Archivo (L)                    | P  |
|--------|-------------------------------------------|------------------------------------------------------------------------------------------------------------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|--------------------------------|----|
| ADM-01 | Admin no puede autodesactivarse           | `UserController::delete(int $userId)` no valida el `userId` contra el `Session::userId()` del admin actual | Un admin puede enviar `POST /admin/usuarios/{su_propio_id}/delete` y desactivar su propia cuenta. No hay guard. El siguiente check de TTL de 5 min lo sacará del sistema. Si es el único admin, el sistema queda sin administradores. | `Admin/UserController:202-222` | P2 |
| ADM-03 | `role_id` en creación de usuario validado | `$data['role_id'] = isset($body['role_id']) ? (int) $body['role_id'] : 2;`                                 | Ningún check de que `role_id` exista en la tabla `roles` antes de pasar al servicio. La FK de BD fallará, pero con un error genérico de BD en lugar de una ValidationException limpia.                                                | `Admin/UserController:117`     | P2 |

### 18.4 Newsletter — Ausencia de Rate Limit y Cupón Hardcoded (P2/P3)

| #     | Regla                                   | Implementación actual                                              | Brecha / Problema                                                                                                                                                                                                                                                                                                | Archivo (L)                 | P  |
|-------|-----------------------------------------|--------------------------------------------------------------------|------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|-----------------------------|----|
| NL-01 | Rate limit en suscripción de newsletter | Sin rate limit en `subscribe()` ni en la ruta asociada             | Un atacante puede registrar decenas de miles de emails distintos (reales o ficticios) disparando un email por cada uno. Agota cuota del servidor de email (Mailpit/SMTP) y puede generar spam backscatter.                                                                                                       | `NewsletterService:37-112`  | P2 |
| NL-02 | Cupón de bienvenida hardcoded           | `$couponCode = 'WELCOME5'` en el template de email de bienvenida   | El código `WELCOME5` se envía a todos los suscriptores pero no existe ninguna tabla de cupones ni validación en el codebase. El usuario recibe un descuento prometido que no puede canjear — o si alguien implementa los cupones luego, `WELCOME5` ya está comprometido con toda la base de suscriptores actual. | `NewsletterService:272`     | P3 |
| NL-03 | `getConfirmedEmails()` sin paginación   | `SELECT email FROM newsletter_subscriptions WHERE ...` sin `LIMIT` | Devuelve TODOS los emails en un único array. Con 100.000 suscriptores → OOM o timeout. Ningún check de tamaño antes de usarlo en envío masivo.                                                                                                                                                                   | `NewsletterService:375-383` | P2 |

### 18.5 Cola de Jobs — Clase de Job No Validada (P3)

| #    | Regla                                | Implementación actual                                                                                        | Brecha / Problema                                                                                                                                                                                              | Archivo (L)     | P  |
|------|--------------------------------------|--------------------------------------------------------------------------------------------------------------|----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|-----------------|----|
| Q-01 | `Queue::push` — validar clase de job | `$jobClass` se acepta como string sin verificar que implemente `JobInterface` ni que sea una clase existente | Si en el futuro algún endpoint expone `Queue::push` con input de usuario (ej. admin), se puede encolar cualquier clase del namespace. Hoy no hay vector directo, pero la capa de cola no tiene defensa propia. | `Queue:79-131`  | P3 |
| Q-02 | Jobs fallidos — visibilidad          | Jobs fallidos van a `queue:failed` Redis key sin interfaz de visualización ni alertas                        | Los jobs en la dead-letter queue pueden acumular silenciosamente (emails no enviados, stamps de fidelidad no aplicados). Sin monitorización, el negocio no sabe que operaciones críticas fallaron.             | `Queue:287-311` | P2 |

### 18.6 Middleware — Permisos en Caché No Invalidados tras Cambio de Rol (P2)

| #      | Regla                                     | Implementación actual                                                                                               | Brecha / Problema                                                                                                                                                                                                                                                                                                                      | Archivo (L)          | P  |
|--------|-------------------------------------------|---------------------------------------------------------------------------------------------------------------------|----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|----------------------|----|
| PRM-01 | Revocación de permisos — efecto inmediato | `loadUserRolesInSession` tiene Guard 1: si `user_roles` Y `user_permissions` existen en sesión, NO recarga desde BD | Si un admin revoca el rol de un usuario (o cambia su rol de `admin` a `user`), los permisos en sesión no se actualizan hasta que expiran ambas claves. El TTL de `_user_verified_at` (5 min) solo refresca la comprobación de `is_active`, no los permisos. Un downgrade de rol puede tardar la vida entera de la sesión en aplicarse. | `Middleware:313-352` | P2 |

### 18.7 CSRF en `Csrf::abort419()` — `exit` Sin Respuesta PSR-7 (P2)

| #     | Regla                              | Implementación actual                                                         | Brecha / Problema                                                                                                                                                                                                                   | Archivo (L)    | P  |
|-------|------------------------------------|-------------------------------------------------------------------------------|-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|----------------|----|
| CS-01 | Respuesta CSRF coherente con PSR-7 | `Csrf::abort419()` llama `exit` directamente, sin pasar por el PSR-7 pipeline | Rompe el flujo PSR-15: cualquier middleware posterior al CSRF que necesite cerrar recursos (transacciones, locks) no se ejecuta. Los tests que esperan un `ResponseInterface` obtienen una excepción inesperada en lugar de un 419. | `Csrf:275-296` | P2 |

### 18.8 `View::back()` y `ExceptionHandler` — Redirect sin Sanitizar el Path (ya cubierto en XR-01/02 como P1)

> Ver sección 18.1.

---

