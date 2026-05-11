<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use App\Core\Result;
use App\Services\Contracts\FileStorageServiceInterface;
use Cloudinary\Api\Upload\UploadApi;
use Cloudinary\Configuration\Configuration;
use Override;
use Throwable;

/**
 * Implementación de almacenamiento de archivos usando Cloudinary.
 *
 * Requiere el paquete: composer require cloudinary/cloudinary_php
 * Variable de entorno: CLOUDINARY_URL=cloudinary://<api_key>:<api_secret>@<cloud_name>
 */
final class CloudinaryStorageService implements FileStorageServiceInterface
{
    private bool $configured = false;

    public function __construct(private readonly string $cloudinaryUrl)
    {
        // La configuración del SDK se realiza de forma lazy para evitar
        // que una CLOUDINARY_URL vacía o ausente rompa la instanciación
        // del servicio y bloquee módulos que no necesitan almacenamiento.
    }

    private function configure(): bool
    {
        if ($this->configured) {
            return true;
        }

        if ($this->cloudinaryUrl === '') {
            Logger::warning('[CloudinaryStorageService] CLOUDINARY_URL no configurada — operación omitida');

            return false;
        }

        Configuration::instance($this->cloudinaryUrl);
        $this->configured = true;

        return true;
    }

    /**
     * Sube una imagen a Cloudinary.
     * resource_type 'image' es el default del SDK.
     *
     * @return Result<string|null> Result::ok($secureUrl) | Result::fail(...)
     */
    #[Override]
    public function uploadImage(string $localPath, string $folder, string $publicId): Result
    {
        if (!$this->configure()) {
            return Result::fail('Almacenamiento en la nube no disponible', 'cloudinary_not_configured');
        }

        try {
            $apiResponse = new UploadApi()->upload($localPath, [
                'resource_type' => 'image',
                'public_id' => $publicId,
                'folder' => $folder,
                'overwrite' => true,
            ]);
            /** @var array<string, mixed> $response */
            $response = (array) $apiResponse;

            return Result::ok((string) ($response['secure_url'] ?? ''));
        } catch (Throwable $e) {
            Logger::error('[CloudinaryStorageService] uploadImage failed', [
                'public_id' => $publicId,
                'folder' => $folder,
                'exception' => $e->getMessage(),
            ]);

            return Result::fail('Error al subir imagen a Cloudinary', 'cloudinary_upload_error');
        }
    }

    /**
     * Sube un archivo binario (PDF, CSV, etc.) a Cloudinary.
     * Usa resource_type 'raw' — requerido para archivos no-imagen/video.
     *
     * @return Result<string|null> Result::ok($secureUrl) | Result::fail(...)
     */
    #[Override]
    public function uploadRaw(string $localPath, string $folder, string $publicId): Result
    {
        if (!$this->configure()) {
            return Result::fail('Almacenamiento en la nube no disponible', 'cloudinary_not_configured');
        }

        try {
            $apiResponse = new UploadApi()->upload($localPath, [
                'resource_type' => 'raw',
                'public_id' => $publicId,
                'folder' => $folder,
                'overwrite' => true,
            ]);
            /** @var array<string, mixed> $response */
            $response = (array) $apiResponse;

            return Result::ok((string) ($response['secure_url'] ?? ''));
        } catch (Throwable $e) {
            Logger::error('[CloudinaryStorageService] uploadRaw failed', [
                'public_id' => $publicId,
                'folder' => $folder,
                'exception' => $e->getMessage(),
            ]);

            return Result::fail('Error al subir archivo a Cloudinary', 'cloudinary_upload_error');
        }
    }

    /**
     * Elimina un recurso de Cloudinary.
     * Devuelve true si la respuesta es 'ok', false en cualquier otro caso.
     */
    #[Override]
    public function destroy(string $publicId, string $resourceType = 'image'): bool
    {
        if (!$this->configure()) {
            return false;
        }

        try {
            $apiResponse = new UploadApi()->destroy($publicId, [
                'resource_type' => $resourceType,
            ]);
            /** @var array<string, mixed> $response */
            $response = (array) $apiResponse;

            return ($response['result'] ?? '') === 'ok';
        } catch (Throwable $e) {
            Logger::error('[CloudinaryStorageService] destroy failed', [
                'public_id' => $publicId,
                'resource_type' => $resourceType,
                'exception' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
