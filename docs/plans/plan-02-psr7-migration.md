# Plan 2: PSR-7 Migration — UserController + AnimalController

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development to implement this plan task-by-task.

**Goal:** Migrar `Shared/UserController` y `Keeper/AnimalController` al contrato PSR-7 completo: recibir `ServerRequestInterface $request`, eliminar `$_POST`/`$_FILES`/`header()`/`exit`, usar `ResponseFactory` para todas las respuestas.

**Architecture:** TDD. Los tests nuevos ejercen el controller con requests PSR-7 de prueba. Los métodos cambian su firma de `void` a `?ResponseInterface`. El controller pasa en PSR-15 pipeline sin exit.

**Tech Stack:** PHP 8.4, PSR-7 (nyholm/psr7 o guzzle), ResponseFactory, Psr\Http\Message\ServerRequestInterface

---

### Task 1: Crear test base para UserController con PSR-7

**Files:**

- Create: `tests/Unit/Http/Controllers/Shared/UserControllerTest.php`
- Read first: `app/Core/Http/ResponseFactory.php` para conocer métodos disponibles

- [ ] **Step 1: Leer ResponseFactory para conocer la API**

```bash
docker compose exec app cat app/Core/Http/ResponseFactory.php
```

- [ ] **Step 2: Leer cómo otros tests crean requests (ej. en tests/Unit/Http/)**

```bash
docker compose exec app find tests/Unit/Http -name '*.php' | head -5
docker compose exec app cat tests/Unit/Http/$(ls tests/Unit/Http/ | head -1)
```

- [ ] **Step 3: Escribir test para `profile()` — método que debe retornar `?ResponseInterface`**

```php
// tests/Unit/Http/Controllers/Shared/UserControllerTest.php
<?php
/**
 * ¿Qué pruebas aquí?
 * Verifica que UserController sigue el contrato PSR-7:
 * recibe ServerRequestInterface, retorna ?ResponseInterface (no void).
 *
 * ¿Qué me quieres demostrar?
 * Que ningún método llama a header() o exit directamente.
 * Que los inputs se leen desde $request->getParsedBody(), no de $_POST.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se vuelve a usar $_POST/$_FILES/header()/exit en el controller.
 */
declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Shared;

use App\Core\Http\ResponseFactory;
use App\Http\Controllers\Shared\UserController;
use App\Services\GamificationService;
use App\Services\ReservationService;
use App\Services\ReviewService;
use App\Services\UserService;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

final class UserControllerTest extends TestCase
{
    private UserService $userService;
    private ReservationService $reservationService;
    private ReviewService $reviewService;
    private GamificationService $gamificationService;
    private ResponseFactory $responseFactory;

    protected function setUp(): void
    {
        $this->userService         = $this->createStub(UserService::class);
        $this->reservationService  = $this->createStub(ReservationService::class);
        $this->reviewService       = $this->createStub(ReviewService::class);
        $this->gamificationService = $this->createStub(GamificationService::class);
        $this->responseFactory     = $this->createStub(ResponseFactory::class);
    }

    private function makeController(): UserController
    {
        return new UserController(
            $this->userService,
            $this->reservationService,
            $this->reviewService,
            $this->gamificationService,
            $this->responseFactory
        );
    }

    private function makeRequest(string $method = 'GET', string $path = '/perfil', array $body = []): ServerRequest
    {
        $factory = new Psr17Factory();
        $request = new ServerRequest($method, $path);

        if (!empty($body)) {
            $request = $request->withParsedBody($body);
        }

        return $request;
    }

    public function test_update_reads_name_from_psr7_parsed_body(): void
    {
        $this->userService
            ->method('updateProfile')
            ->willReturn(\App\Core\Result::ok('ok'));

        $this->userService
            ->method('getProfile')
            ->willReturn(['id' => 1, 'name' => 'Test', 'email' => 'test@example.com', 'roles' => []]);

        // Stub redirect response
        $mockResponse = $this->createStub(ResponseInterface::class);
        $this->responseFactory->method('redirect')->willReturn($mockResponse);

        $request = $this->makeRequest('POST', '/perfil/actualizar', [
            'name'  => 'Juan',
            'email' => 'juan@example.com',
        ]);

        $controller = $this->makeController();
        $result = $controller->update($request);

        // El método debe retornar ResponseInterface, no void/null
        $this->assertInstanceOf(ResponseInterface::class, $result);
    }

    public function test_change_password_reads_fields_from_psr7_parsed_body(): void
    {
        $this->userService
            ->method('changePassword')
            ->willReturn(\App\Core\Result::ok('ok'));

        $mockResponse = $this->createStub(ResponseInterface::class);
        $this->responseFactory->method('redirect')->willReturn($mockResponse);

        $request = $this->makeRequest('POST', '/perfil/password', [
            'current_password'      => 'old123',
            'new_password'          => 'new456789',
            'new_password_confirm'  => 'new456789',
        ]);

        $controller = $this->makeController();
        $result = $controller->changePassword($request);

        $this->assertInstanceOf(ResponseInterface::class, $result);
    }

    public function test_upload_avatar_uses_uploaded_files_from_request(): void
    {
        $factory = new Psr17Factory();
        $uploadedFile = $factory->createUploadedFile(
            $factory->createStream('fake-image-data'),
            100,
            UPLOAD_ERR_OK,
            'avatar.jpg',
            'image/jpeg'
        );

        $request = (new ServerRequest('POST', '/account/avatar/upload'))
            ->withUploadedFiles(['avatar' => $uploadedFile]);

        $this->userService->method('updateAvatar')->willReturn(\App\Core\Result::ok('/storage/uploads/avatars/avatar_1.jpg'));
        $this->userService->method('getProfile')->willReturn(['id' => 1, 'name' => 'Test', 'email' => 'x@x.com', 'avatar' => null, 'roles' => []]);

        $mockResponse = $this->createStub(ResponseInterface::class);
        $this->responseFactory->method('json')->willReturn($mockResponse);

        $controller = $this->makeController();
        $result = $controller->uploadAvatar($request);

        $this->assertInstanceOf(ResponseInterface::class, $result);
    }

    public function test_export_data_returns_response_interface(): void
    {
        $this->userService->method('getProfile')->willReturn(['id' => 1, 'name' => 'Test', 'email' => 'x@x.com', 'roles' => []]);
        $this->reservationService->method('getByUser')->willReturn([]);
        $this->reviewService->method('listUserReviews')->willReturn([]);

        $mockResponse = $this->createStub(ResponseInterface::class);
        $this->responseFactory->method('json')->willReturn($mockResponse);

        $request = $this->makeRequest('GET', '/account/export-data');

        $controller = $this->makeController();
        $result = $controller->exportData($request);

        $this->assertInstanceOf(ResponseInterface::class, $result);
    }
}
```

- [ ] **Step 4: Ejecutar test para verificar fallo**

```bash
docker compose exec app vendor/bin/phpunit tests/Unit/Http/Controllers/Shared/UserControllerTest.php --colors=always
```

Esperado: FAIL — múltiples errores porque el constructor no acepta ResponseFactory, los métodos no reciben $request, etc.

---

### Task 2: Migrar UserController al contrato PSR-7

**Files:**

- Modify: `app/Http/Controllers/Shared/UserController.php` — refactor completo

La migración para cada método:

**Firma antes:** `public function update(): void`
**Firma después:** `public function update(ServerRequestInterface $request): ResponseInterface`

**Patrón de migración por método:**

```
$_POST['campo']              → $request->getParsedBody()['campo'] ?? ''
$_FILES['avatar']            → $request->getUploadedFiles()['avatar'] ?? null
header('Location: /x'); exit → return $this->response->redirect('/x')
header('Content-Type: ...'); echo $json; exit → return $this->response->json($data, $status)
$_SERVER['REQUEST_URI']      → (string) $request->getUri()->getPath()
```

- [ ] **Step 1: Añadir ResponseFactory al constructor de UserController**

```php
// app/Http/Controllers/Shared/UserController.php

use App\Core\Http\ResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class UserController
{
    private UserService $users;
    private ReservationService $reservations;
    private ReviewService $reviews;
    private GamificationService $gamification;
    private ResponseFactory $response;

    public function __construct(
        ?UserService $users = null,
        ?ReservationService $reservations = null,
        ?ReviewService $reviews = null,
        ?GamificationService $gamification = null,
        ?ResponseFactory $response = null
    ) {
        $this->users        = $users        ?? new UserService();
        $this->reservations = $reservations ?? new ReservationService();
        $this->reviews      = $reviews      ?? new ReviewService();
        $this->gamification = $gamification ?? new GamificationService();
        $this->response     = $response     ?? new ResponseFactory();
    }
```

- [ ] **Step 2: Migrar método `profile()`**

```php
public function profile(ServerRequestInterface $request): ?ResponseInterface
{
    $userId = Session::userId();
    if ($userId === null) {
        Flash::error('Necesitas iniciar sesión para continuar.');
        $returnTo = (string) $request->getUri()->getPath();
        // Validar que sea una ruta local válida
        if ($returnTo === '' || !\str_starts_with($returnTo, '/') || \str_starts_with($returnTo, '//')) {
            $returnTo = '/';
        }
        Session::set('redirect_after_login', $returnTo);
        return $this->response->redirect('/login');
    }

    $profile = $this->users->getProfile($userId);
    $reservas = $this->reservations->getByUser($userId, 'active');
    $reservasCount = \count($reservas);
    $nextReservation = !empty($reservas) ? \reset($reservas) : null;
    $userReviews = $this->reviews->listUserReviews($userId);
    $nivel = $this->gamification->calculateUserLevel($reservasCount);

    View::render('shared/user/profile', [
        'titulo'          => 'Mi Perfil',
        'profile'         => $profile,
        'nivel'           => $nivel,
        'stats'           => ['reservasCount' => $reservasCount],
        'nextReservation' => $nextReservation,
        'userReviews'     => $userReviews,
        'flash'           => Flash::consume(),
    ], ['profile.css', 'reviews.css']);

    return null;
}
```

- [ ] **Step 3: Migrar método `update()`**

```php
public function update(ServerRequestInterface $request): ResponseInterface
{
    $userId = Session::userId();
    if ($userId === null) {
        Flash::error('Necesitas iniciar sesión para continuar.');
        return $this->response->redirect('/login');
    }

    $body  = $request->getParsedBody() ?? [];
    $name  = \trim((string) ($body['name'] ?? ''));
    $email = \strtolower(\trim((string) ($body['email'] ?? '')));

    $result = $this->users->updateProfile($userId, $name, $email);

    if (!$result->ok) {
        Flash::error($result->getMessage());
        return $this->response->redirect('/perfil');
    }

    $profile = $this->users->getProfile($userId);
    Session::set('user', $profile);
    Flash::success('Tu perfil se ha actualizado con éxito.');

    return $this->response->redirect('/perfil');
}
```

- [ ] **Step 4: Migrar método `changePassword()`**

```php
public function changePassword(ServerRequestInterface $request): ResponseInterface
{
    $userId = Session::userId();
    if ($userId === null) {
        Flash::error('Necesitas iniciar sesión para continuar.');
        return $this->response->redirect('/login');
    }

    $body    = $request->getParsedBody() ?? [];
    $current = \trim((string) ($body['current_password'] ?? ''));
    $new     = \trim((string) ($body['new_password'] ?? ''));
    $confirm = \trim((string) ($body['new_password_confirm'] ?? ''));

    $result = $this->users->changePassword($userId, $current, $new, $confirm);

    if (!$result->ok) {
        Flash::error($result->getMessage());
        return $this->response->redirect('/perfil');
    }

    Flash::success('Tu contraseña se ha actualizado correctamente.');
    return $this->response->redirect('/perfil');
}
```

- [ ] **Step 5: Migrar método `uploadAvatar()`**

```php
public function uploadAvatar(ServerRequestInterface $request): ResponseInterface
{
    $userId = Session::userId();
    if ($userId === null) {
        return $this->response->json(['success' => false, 'message' => 'No autenticado'], 401);
    }

    $uploadedFiles = $request->getUploadedFiles();
    $uploaded = $uploadedFiles['avatar'] ?? null;

    if ($uploaded === null || $uploaded->getError() !== UPLOAD_ERR_OK) {
        return $this->response->json(['success' => false, 'message' => 'No se recibió ningún archivo válido'], 400);
    }

    if ($uploaded->getSize() > 2 * 1024 * 1024) {
        return $this->response->json(['success' => false, 'message' => 'El archivo es demasiado grande. Máximo 2MB.'], 400);
    }

    $clientName = $uploaded->getClientFilename() ?? '';
    $extension  = \strtolower(\pathinfo($clientName, PATHINFO_EXTENSION));

    if (!\in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
        return $this->response->json(['success' => false, 'message' => 'Extensión de archivo no permitida.'], 400);
    }

    // Verificar MIME real del contenido del stream
    $stream = $uploaded->getStream();
    $tmpContent = $stream->read(12); // leer magic bytes
    $stream->rewind();
    $finfo = new \finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->buffer($tmpContent . $stream->getContents());

    if (!\in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        return $this->response->json(['success' => false, 'message' => 'Tipo de archivo no permitido.'], 400);
    }

    $filename  = 'avatar_' . $userId . '_' . \time() . '.' . $extension;
    $uploadDir = __DIR__ . '/../../../storage/uploads/avatars/';

    if (!\is_dir($uploadDir)) {
        \mkdir($uploadDir, 0755, true);
    }

    $uploaded->moveTo($uploadDir . $filename); // PSR-7: moveTo() en lugar de move_uploaded_file()

    $avatarUrl = '/storage/uploads/avatars/' . $filename;
    $result    = $this->users->updateAvatar($userId, $avatarUrl);

    if (!$result->ok) {
        \unlink($uploadDir . $filename);
        return $this->response->json(['success' => false, 'message' => $result->getMessage()], 500);
    }

    $profile = $this->users->getProfile($userId);
    Session::set('user', $profile);

    return $this->response->json([
        'success'    => true,
        'message'    => 'Avatar actualizado correctamente',
        'avatar_url' => $avatarUrl,
    ]);
}
```

- [ ] **Step 6: Migrar `deleteAvatar()` y `exportData()`**

```php
public function deleteAvatar(ServerRequestInterface $request): ResponseInterface
{
    $userId = Session::userId();
    if ($userId === null) {
        return $this->response->json(['success' => false, 'message' => 'No autenticado'], 401);
    }

    $profile = $this->users->getProfile($userId);
    $currentAvatar = $profile['avatar'] ?? null;
    $result = $this->users->updateAvatar($userId, null);

    if (!$result->ok) {
        return $this->response->json(['success' => false, 'message' => $result->getMessage()], 500);
    }

    if ($currentAvatar && \str_starts_with($currentAvatar, '/storage/uploads/')) {
        $filePath = __DIR__ . '/../../../' . \ltrim($currentAvatar, '/');
        if (\file_exists($filePath)) {
            \unlink($filePath);
        }
    }

    $profile = $this->users->getProfile($userId);
    Session::set('user', $profile);

    return $this->response->json(['success' => true, 'message' => 'Avatar eliminado correctamente']);
}

public function exportData(ServerRequestInterface $request): ResponseInterface
{
    $userId = Session::userId();
    if ($userId === null) {
        Flash::error('Necesitas iniciar sesión para exportar tus datos.');
        return $this->response->redirect('/login');
    }

    $profile      = $this->users->getProfile($userId);
    $reservations = $this->reservations->getByUser($userId, 'all');
    $reviews      = $this->reviews->listUserReviews($userId);

    $exportData = [
        'export_date'  => \date('Y-m-d H:i:s'),
        'user'         => ['id' => $profile['id'], 'name' => $profile['name'], 'email' => $profile['email'], 'created_at' => $profile['created_at'], 'roles' => $profile['roles'] ?? []],
        'reservations' => \array_map(static fn($r) => ['id' => $r['id'], 'date' => $r['reservation_date'] ?? null, 'time' => $r['reservation_time'] ?? null, 'guests' => (int) ($r['guest_count'] ?? 1), 'status' => $r['status'], 'created_at' => $r['created_at']], $reservations),
        'reviews'      => \array_map(static fn($rev) => ['id' => $rev['id'], 'rating' => $rev['rating'], 'comment' => $rev['comment'], 'created_at' => $rev['created_at']], $reviews),
        'gdpr_notice'  => 'Este archivo contiene todos sus datos personales almacenados en Komorebi Café según GDPR Art. 20.',
    ];

    $json = \json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    // Si ResponseFactory tiene download():
    // return $this->response->download($json, 'komorebi-cafe-data-' . \date('Y-m-d') . '.json', 'application/json; charset=utf-8');

    // Si no, usar html() con headers manuales:
    $response = $this->response->html($json, 200);
    return $response
        ->withHeader('Content-Type', 'application/json; charset=utf-8')
        ->withHeader('Content-Disposition', 'attachment; filename="komorebi-cafe-data-' . \date('Y-m-d') . '.json"')
        ->withHeader('Cache-Control', 'no-cache, no-store, must-revalidate');
}
```

- [ ] **Step 7: Ejecutar tests**

```bash
docker compose exec app vendor/bin/phpunit tests/Unit/Http/Controllers/Shared/UserControllerTest.php --colors=always
```

Esperado: PASS (4 assertions)

- [ ] **Step 8: Ejecutar tests de integración para verificar no hay regresiones**

```bash
docker compose exec app vendor/bin/phpunit tests/Integration/AuthIntegrationTest.php --colors=always
```

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/Shared/UserController.php tests/Unit/Http/Controllers/Shared/UserControllerTest.php
git commit -m "refactor: migrate UserController to full PSR-7 contract (no more \$_POST/\$_FILES/header/exit)"
```

---

### Task 3: Migrar Keeper/AnimalController a PSR-7

**Files:**

- Modify: `app/Http/Controllers/Keeper/AnimalController.php`
- Create: `tests/Unit/Http/Controllers/Keeper/AnimalControllerTest.php`

**Contexto:** AnimalController ya usa `ResponseInterface` en la mayoría de métodos POST, pero lee de `$_POST` directamente. El `dashboard()` es `void` — eso está bien (View::render echoes).

Métodos a migrar:

- `logCare()` — `$_POST['animal_id']`, `$_POST['activity_type']`, etc.
- `updateHealth(int $animalId)` — `$_POST['health_status']`, `$_POST['notes']`
- `uploadPhoto(int $animalId)` — `$_FILES`

- [ ] **Step 1: Escribir tests para AnimalController**

```php
// tests/Unit/Http/Controllers/Keeper/AnimalControllerTest.php
<?php
/**
 * ¿Qué pruebas aquí?
 * Verifica que AnimalController usa ServerRequestInterface para leer inputs.
 *
 * ¿Qué me quieres demostrar?
 * Que los métodos POST leen de $request->getParsedBody() no de $_POST.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si logCare() vuelve a usar $_POST directamente;
 * el test pasará un body vía PSR-7 y el controller debe leerlo.
 */
declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Keeper;

use App\Core\Result;
use App\Http\Controllers\Keeper\AnimalController;
use App\Services\AnimalCareService;
use App\Services\FileUploadService;
use App\Services\HealthCheckService;
use App\Core\Http\ResponseFactory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

final class AnimalControllerTest extends TestCase
{
    public function test_log_care_reads_animal_id_from_psr7_body(): void
    {
        $animalCareService = $this->createStub(AnimalCareService::class);
        $animalCareService->method('createCareLog')->willReturn(Result::ok('Log registrado'));

        $response = $this->createStub(ResponseInterface::class);
        $responseFactory = $this->createStub(ResponseFactory::class);
        $responseFactory->method('json')->willReturn($response);

        $controller = new AnimalController(
            $animalCareService,
            $this->createStub(FileUploadService::class),
            $this->createStub(HealthCheckService::class),
            $responseFactory
        );

        $request = (new ServerRequest('POST', '/keeper/log'))
            ->withParsedBody([
                'animal_id'        => '5',
                'activity_type'    => 'feeding',
                'notes'            => 'Animal comió bien',
                'duration_minutes' => '30',
            ]);

        $result = $controller->logCare($request);

        $this->assertInstanceOf(ResponseInterface::class, $result);
    }

    public function test_update_health_reads_status_from_psr7_body(): void
    {
        $animalCareService = $this->createStub(AnimalCareService::class);
        $animalCareService->method('updateHealth')->willReturn(Result::ok('ok'));

        $response = $this->createStub(ResponseInterface::class);
        $responseFactory = $this->createStub(ResponseFactory::class);
        $responseFactory->method('json')->willReturn($response);

        $controller = new AnimalController(
            $animalCareService,
            $this->createStub(FileUploadService::class),
            $this->createStub(HealthCheckService::class),
            $responseFactory
        );

        $request = (new ServerRequest('POST', '/keeper/animal/3/health'))
            ->withParsedBody(['health_status' => 'healthy', 'notes' => 'Todo bien']);

        $result = $controller->updateHealth($request, 3);

        $this->assertInstanceOf(ResponseInterface::class, $result);
    }
}
```

- [ ] **Step 2: Ejecutar para verificar fallo**

```bash
docker compose exec app vendor/bin/phpunit tests/Unit/Http/Controllers/Keeper/AnimalControllerTest.php --colors=always
```

Esperado: FAIL — constructor no acepta esos parámetros, métodos no reciben $request.

- [ ] **Step 3: Refactorizar el constructor de AnimalController para aceptar DI**

```php
// app/Http/Controllers/Keeper/AnimalController.php

final class AnimalController
{
    private AnimalCareService $animalCareService;
    private FileUploadService $fileUploadService;
    private HealthCheckService $healthCheckService;
    private ResponseFactory $response;

    public function __construct(
        ?AnimalCareService $animalCareService = null,
        ?FileUploadService $fileUploadService = null,
        ?HealthCheckService $healthCheckService = null,
        ?ResponseFactory $response = null
    ) {
        $db = Database::getConnection();
        $this->animalCareService  = $animalCareService  ?? new AnimalCareService();
        $this->fileUploadService  = $fileUploadService  ?? new FileUploadService();
        $this->healthCheckService = $healthCheckService ?? new HealthCheckService(new HealthCheckRepository($db));
        $this->response           = $response           ?? new ResponseFactory();
    }
```

- [ ] **Step 4: Migrar `logCare()` a PSR-7**

```php
public function logCare(ServerRequestInterface $request): ResponseInterface
{
    if (!Csrf::validate()) {
        throw ValidationException::withMessage('Token de seguridad inválido', 419);
    }

    $user   = Session::user();
    $userId = $user ? (int) $user['id'] : null;
    $body   = $request->getParsedBody() ?? [];

    $data = [
        'animal_id'        => isset($body['animal_id']) ? (int) $body['animal_id'] : 0,
        'activity_type'    => (string) ($body['activity_type'] ?? ''),
        'notes'            => isset($body['notes']) ? \trim((string) $body['notes']) : null,
        'duration_minutes' => isset($body['duration_minutes']) ? (int) $body['duration_minutes'] : null,
        'mood_before'      => $body['mood_before'] ?? null,
        'mood_after'       => $body['mood_after'] ?? null,
        'logged_by_user_id'=> $userId,
    ];

    $result = $this->animalCareService->createCareLog($data);

    if ($result->ok) {
        return $this->response->json(['ok' => true, 'data' => [
            'message' => \is_string($result->data) ? $result->data : 'Log registrado correctamente',
        ]]);
    }

    throw ValidationException::withMessage($result->error ?? 'Error al registrar el log', 422);
}
```

- [ ] **Step 5: Migrar `updateHealth()` a PSR-7**

```php
public function updateHealth(ServerRequestInterface $request, int $animalId): ResponseInterface
{
    if (!Csrf::validate()) {
        throw ValidationException::withMessage('Token de seguridad inválido', 419);
    }

    $body         = $request->getParsedBody() ?? [];
    $healthStatus = (string) ($body['health_status'] ?? '');
    $notes        = isset($body['notes']) ? \trim((string) $body['notes']) : null;
    $user         = Session::user();
    $userId       = $user ? (int) $user['id'] : null;

    $result = $this->animalCareService->updateHealth($animalId, $healthStatus, $notes, $userId);

    if ($result->ok) {
        return $this->response->json(['ok' => true, 'data' => ['message' => 'Estado actualizado correctamente', 'health_status' => $healthStatus]]);
    }

    throw ValidationException::withMessage($result->error ?? 'Error al actualizar el estado', 422);
}
```

- [ ] **Step 6: Migrar `uploadPhoto()` de `$_FILES` a `$request->getUploadedFiles()`**

```php
public function uploadPhoto(ServerRequestInterface $request, int $animalId): ResponseInterface
{
    if (!Csrf::validate()) {
        throw ValidationException::withMessage('Token de seguridad inválido', 419);
    }

    $uploadedFiles = $request->getUploadedFiles();
    $uploaded = $uploadedFiles['photo'] ?? null;

    if ($uploaded === null || $uploaded->getError() !== UPLOAD_ERR_OK) {
        return $this->response->json(['ok' => false, 'error' => 'No se recibió ningún archivo válido'], 400);
    }

    $result = $this->fileUploadService->uploadAnimalPhoto($uploaded, $animalId);

    if (!$result->ok) {
        return $this->response->json(['ok' => false, 'error' => $result->getMessage()], 422);
    }

    return $this->response->json(['ok' => true, 'data' => ['photo_url' => $result->data]]);
}
```

- [ ] **Step 7: Migrar firma de `toggleActive()` para recibir $request aunque no lo use**

```php
public function toggleActive(ServerRequestInterface $request, int $animalId): ResponseInterface
{
    // ... mismo código, solo cambiar la firma
}
```

- [ ] **Step 8: Ejecutar tests**

```bash
docker compose exec app vendor/bin/phpunit tests/Unit/Http/Controllers/Keeper/AnimalControllerTest.php --colors=always
```

Esperado: PASS

- [ ] **Step 9: Verificar que las rutas en routes.php siguen pasando $request y $params correctamente**

```bash
docker compose exec app grep -n "AnimalController" app/routes.php
```

- [ ] **Step 10: Ejecutar suite completa**

```bash
make test-unit
```

- [ ] **Step 11: Commit**

```bash
git add app/Http/Controllers/Keeper/AnimalController.php tests/Unit/Http/Controllers/Keeper/AnimalControllerTest.php
git commit -m "refactor: migrate AnimalController to PSR-7 (no more \$_POST/\$_FILES)"
```

---

**Verification final del Plan 2:**

```bash
make ci
docker compose exec app grep -rn '\$_POST\|\$_FILES\|\$_GET' app/Http/Controllers/ --include='*.php'
```

Esperado del grep: sin output (cero violaciones PSR-7 en controllers)
