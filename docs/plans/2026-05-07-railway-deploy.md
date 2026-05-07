# Railway Deploy Preparation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Preparar la rama `develop` para merge a `main` y despliegue en Railway con todos los servicios externos funcionales (Resend, Cloudinary, Sentry, BetterStack).

**Architecture:** Custom PHP 8.4 MVC sobre FrankenPHP worker mode. Todas las fases son aditivas/compatibles hacia atrás — no hay breaking changes para el entorno local. Las Fases 2–6 son de infra; la Fase 1 es deuda técnica de utilidades.

**Tech Stack:** PHP 8.4, FrankenPHP, Railway (MySQL + Redis plugins), Resend SMTP, Cloudinary SDK v2, Sentry PHP SDK (`sentry/sentry`), BetterStack log drain (sin código).

---

## Contexto de servicios externos (todas las decisiones ya tomadas)

| Servicio | Free tier | Propósito | Código necesario |
|----------|-----------|-----------|-----------------|
| Resend | 3000/mes | SMTP producción | Solo env vars |
| Cloudinary | 25GB storage + 25GB BW | Avatares, fotos animales, PDFs facturas | Sí — nueva interfaz + servicio |
| Sentry | 5000 errors/mes | Error tracking | `composer require sentry/sentry` + init config |
| BetterStack | 1GB/mes | Log drain Railway | **Cero código** — config post-deploy en Railway dashboard |

**Bug crítico resuelto por Cloudinary:** `FileUploadService::uploadAvatar()` guarda en `storage/uploads/avatars/` y devuelve `/storage/uploads/avatars/filename`, pero NO existe ruta que sirva esos archivos → avatares rotos. Cloudinary entrega URL pública CDN directa.

---

## Fase 1 — Aplicar utilidades S5 (deuda técnica pre-despliegue)

Las clases ya existen en `app/Support/`. Solo hay que reemplazar patrones inline en las vistas que quedaron pendientes.

### Archivos

- Modify: `resources/views/shared/reservas/lista.php`
- Modify: `resources/views/shared/reservas/confirmation.php`
- Modify: `resources/views/shared/reservas/paso-3.php`
- Modify: `resources/views/admin/data-viewer.php`
- Modify: `resources/views/admin/waitlist/show.php`
- Modify: `resources/views/admin/home.php`
- Modify: `resources/views/public/cafes/show.php`
- Modify: `resources/views/manager/cafe/show.php`
- Modify: `resources/views/manager/staff/show.php`
- Modify: `resources/views/user/waitlists.php`
- Modify: `resources/views/public/waitlist-status.php`
- Modify: `resources/views/backoffice/keeper/animals/show.php`
- Modify: `app/Services/EmailService.php`
- Modify: `app/Services/InvoicePDFService.php`
- Modify: `app/Services/LoyaltyService.php`
- Modify: `app/Http/Controllers/Admin/ReportController.php`
- Modify: `app/Http/Controllers/Manager/ReportController.php`
- Modify: `app/Http/Controllers/Admin/HomeController.php`

### Tarea 1.A — Grep de patrones pendientes

- [x] **Paso 1: Identificar patrones TimeHelper**

```bash
docker compose exec app grep -rn "substr(\$" resources/views/ | grep ",0,5)"
```

- [x] **Paso 2: Identificar patrones DateFormatting**

```bash
docker compose exec app grep -rn "date('d/m/Y'" resources/views/ app/Services/ app/Http/Controllers/
```

- [x] **Paso 3: Identificar patrones CurrencyFormatting**

```bash
docker compose exec app grep -rn "number_format(" resources/views/ app/Http/Controllers/
```

- [x] **Paso 4: Identificar StatusLabeling pendiente**

```bash
docker compose exec app grep -rn "getStatusBadge\|statusBadge\|status_badge" resources/views/
```

### Tarea 1.B — TimeHelper::display() — `substr($time, 0, 5)` → `TimeHelper::display($time)`

En cada vista con ese patrón (verificado por grep del paso anterior):

- [x] Añadir `use App\Support\TimeHelper;` en el bloque PHP del archivo (si no existe)
- [x] Reemplazar `substr($someVar, 0, 5)` → `TimeHelper::display($someVar)` (donde `$someVar` es una cadena de hora)

### Tarea 1.C — StatusLabeling en `resources/views/user/waitlists.php`

- [x] Añadir `use App\Support\StatusLabeling;`
- [x] Eliminar función inline `getStatusBadge()` / `getStatusLabel()` hardcoded
- [x] Reemplazar llamadas inline → `StatusLabeling::waitlistBadge($status)` / `StatusLabeling::waitlistLabel($status)`

### Tarea 1.D — DateFormatting — todos los archivos pendientes

- [x] En cada archivo detectado por grep: añadir `use App\Support\DateFormatting;`
- [x] Reemplazar `date('d/m/Y', strtotime($var))` → `DateFormatting::toSpanishDate($var)`
- [x] Reemplazar `date('d/m/Y H:i', strtotime($var))` → `DateFormatting::toSpanishDateTime($var)`

### Tarea 1.E — CurrencyFormatting — todos los archivos pendientes

- [x] En cada archivo detectado por grep: añadir `use App\Support\CurrencyFormatting;`
- [x] Reemplazar `number_format($amount, 0, '.', ',')` / `number_format($amount)` → `CurrencyFormatting::yen($amount)`
- [x] Reemplazar `number_format($rating, 1)` → `CurrencyFormatting::rating($rating)`

### Tarea 1.F — Verificación S5

- [ ] `grep -rn "substr(\$" resources/views/` → 0 ocurrencias de patrón HH:MM
- [ ] `grep -rn "date('d/m/Y'" resources/views/ app/Services/` → 0 ocurrencias
- [ ] `grep -rn "number_format(" resources/views/` → 0 ocurrencias
- [ ] `make phpstan` → sin errores nuevos
- [ ] Commit: `git commit -m "refactor: apply S5 utility classes to all remaining views"`

---

## Fase 2 — Redis Sessions

### Archivos

- Modify: `app/Core/Session.php`

### Tarea 2.A — Implementar SESSION_DRIVER=redis

- [x] **Paso 1:** En `Session::start()`, antes del bloque `else { session_set_cookie_params... session_start(); }`, añadir:

```php
// Configurar Redis como session handler si SESSION_DRIVER=redis
if (\App\Core\Env::get('SESSION_DRIVER', 'file') === 'redis') {
    $redisHost = \App\Core\Env::get('REDIS_HOST', '127.0.0.1');
    $redisPort = \App\Core\Env::int('REDIS_PORT', 6379);
    $redisPass = \App\Core\Env::get('REDIS_PASSWORD', '');
    $sessionTtl = \App\Core\Env::int('SESSION_LIFETIME', 7200);
    $savePath = "tcp://{$redisHost}:{$redisPort}?database=1&lifetime={$sessionTtl}";
    if ($redisPass !== '') {
        $savePath .= "&auth={$redisPass}";
    }
    \ini_set('session.save_handler', 'redis');
    \ini_set('session.save_path', $savePath);
}
```

- [x] **Paso 2:** Verificar que `ext-redis` ya está instalado en `docker/php/Dockerfile.prod` (línea `redis` en `install-php-extensions`)

- [ ] **Paso 3:** Commit: `git commit -m "feat: implement SESSION_DRIVER=redis in Session::start()"`

---

## Fase 3 — Cloudinary Integration

### Archivos

- Create: `app/Services/Contracts/FileStorageServiceInterface.php`
- Create: `app/Services/CloudinaryStorageService.php`
- Modify: `app/Http/Controllers/Auth/AccountController.php`
- Modify: `app/Http/Controllers/Api/V1/KeeperApiController.php`
- Modify: `app/Services/InvoicePDFService.php`
- Modify: `bootstrap/container.php`
- Create: `migrations/030_invoice_pdf_url.sql`

### Tarea 3.A — Instalar SDK

- [ ] **Paso 1:** Instalar en contenedor:

```bash
docker compose exec app composer require cloudinary/cloudinary_php
```

### Tarea 3.B — Crear interfaz `FileStorageServiceInterface`

- [x] **Paso 1:** Crear `app/Services/Contracts/FileStorageServiceInterface.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Core\Result;

interface FileStorageServiceInterface
{
    /**
     * Sube una imagen desde un array de $_FILES y devuelve la URL CDN pública.
     */
    public function uploadImage(array $uploadedFile, string $folder, string $publicId): Result;

    /**
     * Sube un archivo raw (PDF) desde un path local y devuelve la URL CDN pública.
     */
    public function uploadRaw(string $localPath, string $folder, string $publicId): Result;

    /**
     * Elimina un asset de Cloudinary por su public_id completo (folder/name).
     */
    public function delete(string $publicId, string $resourceType = 'image'): bool;
}
```

### Tarea 3.C — Crear `CloudinaryStorageService`

- [x] **Paso 1:** Crear `app/Services/CloudinaryStorageService.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Env;
use App\Core\Logger;
use App\Core\Result;
use App\Services\Contracts\FileStorageServiceInterface;
use Cloudinary\Configuration\Configuration;
use Cloudinary\Api\Upload\UploadApi;
use Throwable;

final class CloudinaryStorageService implements FileStorageServiceInterface
{
    private readonly UploadApi $uploadApi;

    public function __construct(
        private readonly string $cloudName,
        private readonly string $apiKey,
        private readonly string $apiSecret,
    ) {
        Configuration::instance([
            'cloud' => [
                'cloud_name' => $this->cloudName,
                'api_key'    => $this->apiKey,
                'api_secret' => $this->apiSecret,
            ],
            'url' => ['secure' => true],
        ]);
        $this->uploadApi = new UploadApi();
    }

    #[\Override]
    public function uploadImage(array $uploadedFile, string $folder, string $publicId): Result
    {
        if (($uploadedFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return Result::fail('Error en el archivo subido.', 'upload_error');
        }

        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $mime = \mime_content_type($uploadedFile['tmp_name']);
        if (!\in_array($mime, $allowed, true)) {
            return Result::fail('Tipo de archivo no permitido.', 'invalid_mime');
        }

        try {
            $response = $this->uploadApi->upload($uploadedFile['tmp_name'], [
                'folder'        => "komorebi/{$folder}",
                'public_id'     => $publicId,
                'overwrite'     => true,
                'resource_type' => 'image',
            ]);
            return Result::ok((string) $response['secure_url']);
        } catch (Throwable $e) {
            Logger::error('[CloudinaryStorageService] uploadImage failed', ['error' => $e->getMessage()]);
            return Result::fail('Error al subir la imagen.', 'cloudinary_error');
        }
    }

    #[\Override]
    public function uploadRaw(string $localPath, string $folder, string $publicId): Result
    {
        try {
            $response = $this->uploadApi->upload($localPath, [
                'folder'        => "komorebi/{$folder}",
                'public_id'     => $publicId,
                'overwrite'     => true,
                'resource_type' => 'raw',
            ]);
            return Result::ok((string) $response['secure_url']);
        } catch (Throwable $e) {
            Logger::error('[CloudinaryStorageService] uploadRaw failed', ['error' => $e->getMessage()]);
            return Result::fail('Error al subir el archivo.', 'cloudinary_error');
        }
    }

    #[\Override]
    public function delete(string $publicId, string $resourceType = 'image'): bool
    {
        try {
            $this->uploadApi->destroy("komorebi/{$publicId}", ['resource_type' => $resourceType]);
            return true;
        } catch (Throwable $e) {
            Logger::error('[CloudinaryStorageService] delete failed', ['error' => $e->getMessage()]);
            return false;
        }
    }
}
```

### Tarea 3.D — Registrar en container

- [x] **Paso 1:** En `bootstrap/container.php`, añadir registro del servicio:

```php
Container::singleton(
    \App\Services\Contracts\FileStorageServiceInterface::class,
    static fn() => new \App\Services\CloudinaryStorageService(
        Env::get('CLOUDINARY_CLOUD_NAME', ''),
        Env::get('CLOUDINARY_API_KEY', ''),
        Env::get('CLOUDINARY_API_SECRET', ''),
    )
);
```

### Tarea 3.E — Migrar AccountController

- [x] **Paso 1:** En `app/Http/Controllers/Auth/AccountController.php`:
  - Cambiar inyección: `FileUploadServiceInterface` → `FileStorageServiceInterface`
  - En `uploadAvatar()`: `$this->storage->uploadImage($uploadedFile, 'avatars', "user_{$userId}")` → guardar `$result->data` en `users.avatar`
  - En `deleteAvatar()`: `$this->storage->delete("avatars/user_{$userId}")` y limpiar `users.avatar` a null

### Tarea 3.F — Migrar KeeperApiController

- [x] **Paso 1:** En `app/Http/Controllers/Api/V1/KeeperApiController.php`:
  - Cambiar inyección: `FileUploadServiceInterface` → `FileStorageServiceInterface`
  - En `uploadPhoto()`: `$this->storage->uploadImage($uploadedFile, 'animals', "animal_{$animalId}")` → actualizar URL en animales

### Tarea 3.G — Migración SQL

- [x] **Paso 1:** Crear `migrations/030_invoice_pdf_url.sql`:

```sql
-- 030: Añadir columna invoice_pdf_url a reservations
ALTER TABLE reservations
    ADD COLUMN invoice_pdf_url VARCHAR(500) NULL AFTER status;
```

### Tarea 3.H — Migrar InvoicePDFService

- [x] **Paso 1:** Inyectar `FileStorageServiceInterface` en constructor
- [x] **Paso 2:** Tras generar PDF temporal:

```php
$uploadResult = $this->fileStorage->uploadRaw($tempPath, 'invoices', "reserva_{$reservationCode}");
@\unlink($tempPath);
if (!$uploadResult->ok) {
    return Result::fail($uploadResult->getMessage(), 'pdf_upload_error');
}
return Result::ok($uploadResult->data); // URL Cloudinary
```

- [x] **Paso 3:** En `ReservationService` / quien llame a `generateReservationInvoice()`: guardar URL en `reservations.invoice_pdf_url`
- [x] **Paso 4:** En `resources/views/shared/reservas/lista.php`: añadir botón "Descargar PDF" si `$reservation['invoice_pdf_url']` no es null

- [ ] **Paso 5:** Commit: `git commit -m "feat: Cloudinary integration for avatars, animal photos and invoices"`

---

## Fase 4 — Sentry

### Archivos

- Modify: `docker/php/Dockerfile.prod`
- Modify: `public/index.php`

### Tarea 4.A — Instalar SDK

- [ ] **Paso 1:**

```bash
docker compose exec app composer require sentry/sentry
```

### Tarea 4.B — Añadir extensión excimer al Dockerfile.prod

- [x] **Paso 1:** En el bloque `install-php-extensions`, añadir `excimer`:

```dockerfile
RUN install-php-extensions \
    pdo_mysql \
    mbstring \
    intl \
    gd \
    zip \
    bcmath \
    exif \
    redis \
    pcntl \
    excimer
```

### Tarea 4.C — Actualizar init en public/index.php

- [x] **Paso 1:** Reemplazar el bloque actual `\Sentry\init([...])` con:

```php
\Sentry\init([
    'dsn'                  => $dsn,
    'environment'          => $env,
    'release'              => \App\Core\Env::get('APP_VERSION', 'unknown'),
    'traces_sample_rate'   => 0.1,
    'profiles_sample_rate' => 0.1,
    'enable_logs'          => true,
    'send_default_pii'     => false,
    'in_app_include'       => ['app/'],
]);
```

- [ ] **Paso 2:** Commit: `git commit -m "feat: Sentry PHP SDK with profiling (excimer) and full options"`

---

## Fase 5 — Asset Cache Busting

### Archivos

- Modify: `resources/views/layouts/backoffice.php`
- Modify: `resources/views/layouts/main.php`

### Tarea 5.A — backoffice.php

- [x] **Paso 1:** Al inicio del bloque PHP del layout (antes del DOCTYPE), añadir:

```php
$assetVersion = \App\Core\Env::get('APP_VERSION', '1');
```

- [x] **Paso 2:** A todos los `<link href="/css/...">` y `<script src="/js/...">` locales (NO a URLs de CDN tipo `cdn.jsdelivr.net`), añadir `?v=<?= e($assetVersion) ?>`:

```html
<link rel="stylesheet" href="/css/design-tokens.css?v=<?= e($assetVersion) ?>">
<link rel="stylesheet" href="/css/backoffice-modern.css?v=<?= e($assetVersion) ?>">
<!-- ... etc para todos los assets locales ... -->
```

### Tarea 5.B — main.php

- [x] **Paso 1:** Mismo patrón que backoffice.php — variable `$assetVersion` + query string en assets locales.

- [ ] **Paso 2:** Commit: `git commit -m "feat: asset cache busting with APP_VERSION query string"`

---

## Fase 6 — Railway Config

### Archivos

- Modify: `railway.toml` (sección de comentarios de env vars)
- Modify: `.env.example` (añadir vars Cloudinary si faltan)
- Create: `.env.railway.example`
- Modify: `docs/DEPLOYMENT.md`

### Tarea 6.A — Actualizar comentarios railway.toml

- [x] **Paso 1:** Añadir al bloque de comentarios de variables en `railway.toml`:

```toml
#   MAIL_HOST=smtp.resend.com
#   MAIL_PORT=587
#   MAIL_USERNAME=resend
#   MAIL_PASSWORD=re_xxxxxxxxxxxx
#   MAIL_FROM=no-reply@tu-dominio.com
#   MAIL_FROM_NAME=Komorebi Café
#   MAIL_ENCRYPTION=tls
#   CLOUDINARY_CLOUD_NAME=tu_cloud_name
#   CLOUDINARY_API_KEY=tu_api_key
#   CLOUDINARY_API_SECRET=tu_api_secret
#   SENTRY_DSN=https://xxx@oXX.ingest.sentry.io/xxx
#   APP_VERSION=1.0.0
#   APP_HTTPS=true
#   SESSION_SECURE=true
```

### Tarea 6.B — Crear .env.railway.example

- [x] **Paso 1:** Crear `.env.railway.example` en la raíz del repo con TODAS las variables agrupadas por servicio

### Tarea 6.C — Verificar .env.example

- [x] **Paso 1:** Confirmar que `.env.example` tiene `CLOUDINARY_CLOUD_NAME=`, `CLOUDINARY_API_KEY=`, `CLOUDINARY_API_SECRET=`, `APP_VERSION=1.0.0`

- [ ] **Paso 2:** Commit: `git commit -m "chore: Railway config — .env.railway.example, railway.toml env vars, Cloudinary vars"`

---

## Verificación final

- [ ] `make phpstan` → 0 errores
- [ ] `make cs-check` → limpio
- [ ] `make test` → verde
- [ ] `grep -rn "substr(\$" resources/views/ | grep ",0,5)"` → 0
- [ ] `grep -rn "date('d/m/Y'" resources/views/ app/Services/` → 0
- [ ] `grep -rn "number_format(" resources/views/` → 0
- [ ] `make ci` → verde completo

## Merge

- [ ] PR `develop` → `main`
- [ ] Skill `requesting-code-review`
- [ ] Merge → Railway auto-deploy

## Post-deploy (sin código)

1. BetterStack log drain: Railway dashboard → Service → Settings → Observability → Log Drains → pegar drain URL de BetterStack
2. Uptime Robot: crear monitor ping a `/health` cada 5 minutos
3. Verificar Sentry recibe primer error de prueba desde Railway
4. Resend: verificar DNS del dominio en dashboard → `no-reply@dominio.com` activo
