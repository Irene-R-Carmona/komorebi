<?php

declare(strict_types=1);

/**
 * Tests de FileUploadService
 *
 * ¿Qué pruebas aquí?
 * - Validaciones de archivos: error codes de PHP, tamaño, MIME type, extensión
 * - Protección contra path traversal en deleteFile()
 * - Valores de getUploadLimits() para cada tipo
 *
 * ¿Qué me quieres demostrar?
 * - Que la validación rechaza correctamente archivos prohibidos antes de procesarlos
 * - Que deleteFile() nunca puede eliminar ficheros fuera del directorio de uploads
 * - Que los límites reportados coinciden con las constantes internas del servicio
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * - Si se modifiquen los límites de tamaño (MAX_AVATAR_SIZE, MAX_ANIMAL_PHOTO_SIZE)
 * - Si se añaden o quitan tipos MIME/extensiones permitidas
 * - Si se cambia la lógica de validación de error codes de PHP
 * - Si se debilita la protección path-traversal en deleteFile()
 */

namespace Tests\Unit\Services;

use App\Services\FileUploadService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(\App\Services\FileUploadService::class)]
final class FileUploadServiceTest extends TestCase
{
    private FileUploadService $service;

    /** Rutas temporales a limpiar en tearDown */
    private array $tmpFiles = [];

    protected function setUp(): void
    {
        $this->service = new FileUploadService();
    }

    protected function tearDown(): void
    {
        foreach ($this->tmpFiles as $path) {
            if (\is_file($path)) {
                @\unlink($path);
            }
        }
    }

    // ─────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────

    /** Crea un fichero temporal de texto (MIME: text/plain). */
    private function createTextTmpFile(string $content = 'not an image'): string
    {
        $path = \tempnam(\sys_get_temp_dir(), 'komorebi_test_');
        \file_put_contents($path, $content);
        $this->tmpFiles[] = $path;

        return $path;
    }

    /**
     * Crea un JPEG mínimo válido usando GD.
     * Requiere extensión GD (disponible en el contenedor Docker del proyecto).
     */
    private function createTmpJpegFile(): string
    {
        $path = \tempnam(\sys_get_temp_dir(), 'komorebi_test_') . '.jpg';
        $this->tmpFiles[] = $path;
        $img = \imagecreatetruecolor(10, 10);
        \imagejpeg($img, $path, 85);
        \imagedestroy($img);

        return $path;
    }

    /** Construye un array compatible con $_FILES dado error code y tamaño. */
    private function makeFileArray(
        int $errorCode,
        string $name = 'photo.jpg',
        string $tmpName = '',
        int $size = 100
    ): array {
        return [
            'error' => $errorCode,
            'name' => $name,
            'tmp_name' => $tmpName,
            'size' => $size,
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // getUploadLimits()
    // ─────────────────────────────────────────────────────────────

    #[Test]
    public function getUploadLimitsForAvatarReturnsCorrectValues(): void
    {
        $limits = $this->service->getUploadLimits('avatar');

        $this->assertSame(2 * 1024 * 1024, $limits['maxSize']);
        $this->assertSame(2.0, $limits['maxSizeMB']);
        $this->assertContains('image/jpeg', $limits['allowedTypes']);
        $this->assertContains('image/png', $limits['allowedTypes']);
        $this->assertContains('image/webp', $limits['allowedTypes']);
        $this->assertContains('jpg', $limits['allowedExtensions']);
        $this->assertContains('png', $limits['allowedExtensions']);
    }

    #[Test]
    public function getUploadLimitsForAnimalReturnsCorrectMaxSize(): void
    {
        $limits = $this->service->getUploadLimits('animal');

        $this->assertSame(5 * 1024 * 1024, $limits['maxSize']);
        $this->assertSame(5.0, $limits['maxSizeMB']);
    }

    #[Test]
    public function getUploadLimitsDefaultsToAvatar(): void
    {
        $defaultLimits = $this->service->getUploadLimits();
        $avatarLimits = $this->service->getUploadLimits('avatar');

        $this->assertSame($avatarLimits['maxSize'], $defaultLimits['maxSize']);
    }

    // ─────────────────────────────────────────────────────────────
    // uploadAvatar() — validaciones de PHP upload error codes
    // ─────────────────────────────────────────────────────────────

    #[Test]
    public function uploadAvatarWithErrorArrayInsteadOfCodeReturnsFail(): void
    {
        $file = [
            'error' => [UPLOAD_ERR_OK, UPLOAD_ERR_NO_FILE], // array, no escalar
            'name' => 'photo.jpg',
            'tmp_name' => '',
            'size' => 100,
        ];

        $result = $this->service->uploadAvatar($file, 1);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Error en la subida', $result->getMessage());
    }

    #[Test]
    public function uploadAvatarWithPhpIniSizeErrorReturnsFail(): void
    {
        $file = $this->makeFileArray(UPLOAD_ERR_INI_SIZE);

        $result = $this->service->uploadAvatar($file, 1);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('tamaño máximo', $result->getMessage());
    }

    #[Test]
    public function uploadAvatarWithFormSizeErrorReturnsFail(): void
    {
        $file = $this->makeFileArray(UPLOAD_ERR_FORM_SIZE);

        $result = $this->service->uploadAvatar($file, 1);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('tamaño máximo', $result->getMessage());
    }

    #[Test]
    public function uploadAvatarWithNoFileErrorReturnsFail(): void
    {
        $file = $this->makeFileArray(UPLOAD_ERR_NO_FILE);

        $result = $this->service->uploadAvatar($file, 1);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('No se seleccionó ningún archivo', $result->getMessage());
    }

    #[Test]
    public function uploadAvatarWithUnknownErrorCodeReturnsFail(): void
    {
        $file = $this->makeFileArray(999);

        $result = $this->service->uploadAvatar($file, 1);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Error desconocido', $result->getMessage());
    }

    // ─────────────────────────────────────────────────────────────
    // uploadAvatar() — validación de tamaño (antes del acceso MIME)
    // ─────────────────────────────────────────────────────────────

    #[Test]
    public function uploadAvatarWithFileTooLargeReturnsFail(): void
    {
        // La comprobación de tamaño ocurre ANTES del acceso a finfo,
        // así que tmp_name vacío no importa aquí.
        $file = $this->makeFileArray(
            UPLOAD_ERR_OK,
            'photo.jpg',
            '',
            3 * 1024 * 1024  // 3 MB > límite de 2 MB para avatares
        );

        $result = $this->service->uploadAvatar($file, 1);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('2MB', $result->getMessage());
    }

    #[Test]
    public function uploadAnimalPhotoWithFileTooLargeReturnsFail(): void
    {
        $file = $this->makeFileArray(
            UPLOAD_ERR_OK,
            'animal.jpg',
            '',
            6 * 1024 * 1024  // 6 MB > límite de 5 MB para fotos de animales
        );

        $result = $this->service->uploadAnimalPhoto($file, 42);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('5MB', $result->getMessage());
    }

    // ─────────────────────────────────────────────────────────────
    // uploadAvatar() — validación de MIME type y extensión
    // ─────────────────────────────────────────────────────────────

    #[Test]
    public function uploadAvatarWithTextFileMimeTypeReturnsFail(): void
    {
        $tmpFile = $this->createTextTmpFile('this is plain text, not an image');

        $file = $this->makeFileArray(UPLOAD_ERR_OK, 'photo.jpg', $tmpFile, 50);

        $result = $this->service->uploadAvatar($file, 1);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Tipo de archivo no permitido', $result->getMessage());
    }

    #[Test]
    public function uploadAvatarWithDisallowedExtensionReturnsFail(): void
    {
        // El fichero temporal es un JPEG real (supera MIME check)
        // pero su nombre tiene extensión .gif (falla la comprobación de extensión)
        $tmpFile = $this->createTmpJpegFile();

        $file = $this->makeFileArray(UPLOAD_ERR_OK, 'photo.gif', $tmpFile, 500);

        $result = $this->service->uploadAvatar($file, 1);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Extensión de archivo no permitida', $result->getMessage());
    }

    // ─────────────────────────────────────────────────────────────
    // deleteFile() — protección path traversal + fichero inexistente
    // ─────────────────────────────────────────────────────────────

    #[Test]
    public function deleteFileWithPathTraversalReturnsFail(): void
    {
        // Intento de salir del directorio de uploads
        $result = $this->service->deleteFile('/storage/uploads/../../etc/passwd');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Archivo no válido', $result->getMessage());
    }

    #[Test]
    public function deleteFileWithNonExistentFileReturnsFail(): void
    {
        $result = $this->service->deleteFile('/storage/uploads/avatars/nonexistent_999.jpg');

        $this->assertFalse($result->ok);
    }

    // ─────────────────────────────────────────────────────────────
    // uploadAnimalPhoto() — smoke test válido
    // ─────────────────────────────────────────────────────────────

    #[Test]
    public function uploadAnimalPhotoWithValidJpegReturnsOkWithRelativeUrl(): void
    {
        $tmpFile = $this->createTmpJpegFile();

        $file = $this->makeFileArray(UPLOAD_ERR_OK, 'animal.jpg', $tmpFile, 1024);

        $result = $this->service->uploadAnimalPhoto($file, 7);

        $this->assertTrue($result->ok);
        $this->assertIsString($result->data);
        $this->assertStringStartsWith('/storage/uploads/animals/', $result->data);
        $this->assertStringContainsString('animal_7_', $result->data);
    }

    #[Test]
    public function uploadAvatarWithValidJpegReturnsOkWithRelativeUrl(): void
    {
        $tmpFile = $this->createTmpJpegFile();

        $file = $this->makeFileArray(UPLOAD_ERR_OK, 'avatar.jpg', $tmpFile, 1024);

        $result = $this->service->uploadAvatar($file, 99);

        $this->assertTrue($result->ok);
        $this->assertIsString($result->data);
        $this->assertStringStartsWith('/storage/uploads/avatars/', $result->data);
        $this->assertStringContainsString('user_99_', $result->data);
    }
}
