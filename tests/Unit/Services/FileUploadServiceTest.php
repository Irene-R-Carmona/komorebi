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
        $file = ['error' => UPLOAD_ERR_NO_FILE, 'name' => '', 'size' => 0, 'tmp_name' => ''];
        $result = $this->service->uploadAvatar($file, 1);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('archivo', $result->error);
    }

    public function testUploadAvatarFailsWhenFileExceedsMaxSize(): void
    {
        $file = ['error' => UPLOAD_ERR_INI_SIZE, 'name' => 'photo.jpg', 'size' => 0, 'tmp_name' => ''];
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
        $file = ['error' => UPLOAD_ERR_NO_FILE, 'name' => '', 'size' => 0, 'tmp_name' => ''];
        $result = $this->service->uploadAnimalPhoto($file, 1);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('archivo', $result->error);
    }

    public function testUploadAnimalPhotoFailsWhenFileExceedsIniMaxSize(): void
    {
        $file = ['error' => UPLOAD_ERR_INI_SIZE, 'name' => 'big.jpg', 'size' => 0, 'tmp_name' => ''];
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

    public function testUploadAvatarFailsWhenFileSizeExceedsLimit(): void
    {
        $file = ['error' => UPLOAD_ERR_OK, 'name' => 'big.jpg', 'size' => PHP_INT_MAX, 'tmp_name' => ''];

        $result = $this->service->uploadAvatar($file, 1);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('menor a', $result->error);
    }

    public function testUploadAvatarFailsWithFormSizeError(): void
    {
        $file = ['error' => UPLOAD_ERR_FORM_SIZE, 'name' => 'photo.jpg', 'size' => 0, 'tmp_name' => ''];

        $result = $this->service->uploadAvatar($file, 1);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('tamaño', $result->error);
    }

    public function testUploadAvatarFailsWithUnknownError(): void
    {
        $file = ['error' => UPLOAD_ERR_PARTIAL, 'name' => 'photo.jpg', 'size' => 0, 'tmp_name' => ''];

        $result = $this->service->uploadAvatar($file, 1);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('desconocido', $result->error);
    }

    public function testUploadAnimalPhotoFailsWhenFileSizeExceedsLimit(): void
    {
        $file = ['error' => UPLOAD_ERR_OK, 'name' => 'big.jpg', 'size' => PHP_INT_MAX, 'tmp_name' => ''];

        $result = $this->service->uploadAnimalPhoto($file, 3);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('menor a', $result->error);
    }

    public function testGetUploadLimitsForAnimalReturnsArray(): void
    {
        $limits = $this->service->getUploadLimits('animal');

        $this->assertIsArray($limits);
        $this->assertArrayHasKey('maxSize', $limits);
        $this->assertArrayHasKey('maxSizeMB', $limits);
    }

    public function testDeleteFileFailsWhenFileDoesNotExist(): void
    {
        $result = $this->service->deleteFile('/storage/uploads/avatars/nonexistent_99999.jpg');

        $this->assertFalse($result->ok);
        $this->assertNotEmpty($result->error);
    }

    public function testUploadAvatarFailsWithInvalidMimeType(): void
    {
        $tmpFile = \tempnam(\sys_get_temp_dir(), 'komtest');
        \file_put_contents($tmpFile, 'this is not an image file content');
        $file = ['error' => UPLOAD_ERR_OK, 'name' => 'photo.jpg', 'size' => 33, 'tmp_name' => $tmpFile];

        $result = $this->service->uploadAvatar($file, 1);

        @\unlink($tmpFile);
        $this->assertFalse($result->ok);
        $this->assertStringContainsString('no permitido', $result->error);
    }

    public function testDeleteFileSucceedsWhenFileExists(): void
    {
        $avatarDir = \sys_get_temp_dir() . '/avatars';
        $filename = 'test_del_' . \getmypid() . '.jpg';
        \file_put_contents($avatarDir . '/' . $filename, 'fake image content');

        $result = $this->service->deleteFile('/storage/uploads/avatars/' . $filename);

        $this->assertTrue($result->ok);
    }

    public function testDeleteFileFailsWhenPathOutsideBasePath(): void
    {
        // Un path relativo que escapa del directorio de uploads
        $result = $this->service->deleteFile('/storage/uploads/../../etc/passwd');

        $this->assertFalse($result->ok);
    }
}
