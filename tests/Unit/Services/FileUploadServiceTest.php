<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? FileUploadService: validación de errores en la subida de archivos.
 * ¿Qué me quieres demostrar? Que uploadAvatar retorna fail cuando el archivo tiene errores de subida.
 * ¿Qué va a fallar en este test si se cambia el código? Si se elimina la validación de errores PHP de subida.
 */

namespace Tests\Unit\Services;

use App\Services\FileUploadService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FileUploadService::class)]
final class FileUploadServiceTest extends TestCase
{
    private FileUploadService $service;

    protected function setUp(): void
    {
        $this->service = new FileUploadService(\sys_get_temp_dir());
    }

    public function testUploadAvatarFailsWhenNoFileSelected(): void
    {
        $file   = ['error' => UPLOAD_ERR_NO_FILE, 'name' => '', 'size' => 0, 'tmp_name' => ''];
        $result = $this->service->uploadAvatar($file, 1);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('archivo', $result->error);
    }

    public function testUploadAvatarFailsWhenFileExceedsMaxSize(): void
    {
        $file   = ['error' => UPLOAD_ERR_INI_SIZE, 'name' => 'photo.jpg', 'size' => 0, 'tmp_name' => ''];
        $result = $this->service->uploadAvatar($file, 1);

        $this->assertFalse($result->ok);
    }

    public function testGetUploadLimitsReturnsArray(): void
    {
        $limits = $this->service->getUploadLimits('avatar');

        $this->assertIsArray($limits);
        $this->assertNotEmpty($limits);
    }

    public function testUploadAnimalPhotoFailsWhenNoFileSelected(): void
    {
        $file   = ['error' => UPLOAD_ERR_NO_FILE, 'name' => '', 'size' => 0, 'tmp_name' => ''];
        $result = $this->service->uploadAnimalPhoto($file, 1);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('archivo', $result->error);
    }

    public function testUploadAnimalPhotoFailsWhenFileExceedsIniMaxSize(): void
    {
        $file   = ['error' => UPLOAD_ERR_INI_SIZE, 'name' => 'big.jpg', 'size' => 0, 'tmp_name' => ''];
        $result = $this->service->uploadAnimalPhoto($file, 5);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('tamaño', $result->error);
    }

    public function testDeleteAvatarReturnsFailWhenNoAvatarExists(): void
    {
        $result = $this->service->deleteAvatar(99999);

        $this->assertFalse($result->ok);
        $this->assertNotEmpty($result->error);
    }
}
