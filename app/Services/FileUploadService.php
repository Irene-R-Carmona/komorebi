<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\ImageProcessor;
use App\Core\Result;
use App\Services\Contracts\FileStorageServiceInterface;
use App\Services\Contracts\FileUploadServiceInterface;
use finfo;
use Override;

/**
 * Servicio de gestión de subida de archivos
 *
 * Maneja la subida, validación y optimización de archivos,
 * especialmente imágenes para avatares y fotos de animales.
 *
 * @package Komorebi\Services
 */
final class FileUploadService implements FileUploadServiceInterface
{
    private const MAX_AVATAR_SIZE = 2 * 1024 * 1024; // 2 MB
    private const MAX_ANIMAL_PHOTO_SIZE = 5 * 1024 * 1024; // 5 MB

    private const ALLOWED_AVATAR_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
    private const ALLOWED_AVATAR_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    private const AVATAR_MAX_WIDTH = 500;
    private const AVATAR_MAX_HEIGHT = 500;

    public function __construct(private readonly ?FileStorageServiceInterface $fileStorage = null)
    {
    }

    /**
     * Sube un avatar de usuario
     *
     * @param array   $file   Archivo de $_FILES
     * @param integer $userId ID del usuario
     * @return Result URL pública del avatar subido (Cloudinary)
     */
    #[Override]
    public function uploadAvatar(array $file, int $userId): Result
    {
        $validation = $this->validateFile($file, self::MAX_AVATAR_SIZE);
        if ($validation->error !== null) {
            return $validation;
        }

        if ($this->fileStorage === null) {
            return Result::fail('Almacenamiento externo no disponible');
        }

        $extension = \strtolower(\pathinfo($file['name'], PATHINFO_EXTENSION));
        $tmpPath = \sys_get_temp_dir() . '/komorebi_avatar_' . $userId . '_' . \time() . '.' . $extension;

        $saveResult = $this->processAndSaveImage(
            $file['tmp_name'],
            $tmpPath,
            self::AVATAR_MAX_WIDTH,
            self::AVATAR_MAX_HEIGHT
        );

        if ($saveResult->error !== null) {
            @\unlink($tmpPath);

            return $saveResult;
        }

        $uploadResult = $this->fileStorage->uploadImage($tmpPath, 'komorebi/avatars', "user_{$userId}");
        @\unlink($tmpPath);

        if ($uploadResult->error !== null) {
            return $uploadResult;
        }

        return Result::ok((string) ($uploadResult->data ?? ''));
    }

    /**
     * Elimina el avatar de un usuario de Cloudinary
     *
     * @param integer $userId ID del usuario
     * @return Result
     */
    #[Override]
    public function deleteAvatar(int $userId): Result
    {
        if ($this->fileStorage === null) {
            return Result::fail('Almacenamiento externo no disponible');
        }

        $destroyed = $this->fileStorage->destroy("komorebi/avatars/user_{$userId}");

        if ($destroyed) {
            return Result::ok(true);
        }

        return Result::fail('No se encontró ningún avatar para eliminar');
    }

    /**
     * Sube una foto de animal
     *
     * @param array   $file     Archivo de $_FILES
     * @param integer $animalId ID del animal
     * @return Result URL pública de la foto subida (Cloudinary)
     */
    #[Override]
    public function uploadAnimalPhoto(array $file, int $animalId): Result
    {
        $validation = $this->validateFile($file, self::MAX_ANIMAL_PHOTO_SIZE);
        if ($validation->error !== null) {
            return $validation;
        }

        if ($this->fileStorage === null) {
            return Result::fail('Almacenamiento externo no disponible');
        }

        $extension = \strtolower(\pathinfo($file['name'], PATHINFO_EXTENSION));
        $tmpPath = \sys_get_temp_dir() . '/komorebi_animal_' . $animalId . '_' . \time() . '.' . $extension;

        $saveResult = $this->processAndSaveImage(
            $file['tmp_name'],
            $tmpPath,
            1200,
            1200
        );

        if ($saveResult->error !== null) {
            @\unlink($tmpPath);

            return $saveResult;
        }

        $uploadResult = $this->fileStorage->uploadImage($tmpPath, 'komorebi/animals', "animal_{$animalId}");
        @\unlink($tmpPath);

        if ($uploadResult->error !== null) {
            return $uploadResult;
        }

        return Result::ok((string) ($uploadResult->data ?? ''));
    }

    /**
     * Valida un archivo subido
     *
     * @param array   $file    Archivo de $_FILES
     * @param integer $maxSize Tamaño máximo en bytes
     * @return Result
     */
    private function validateFile(
        array $file,
        int $maxSize
    ): Result { // Verificar que se subió correctamente
        if (!isset($file['error']) || \is_array($file['error'])) {
            return Result::fail('Error en la subida del archivo');
        }

        // Verificar errores de PHP
        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return Result::fail('El archivo excede el tamaño máximo permitido');
            case UPLOAD_ERR_NO_FILE:
                return Result::fail('No se seleccionó ningún archivo');
            default:
                return Result::fail('Error desconocido al subir el archivo');
        }

        // Verificar tamaño
        if ($file['size'] > $maxSize) {
            $maxSizeMB = \round($maxSize / 1024 / 1024, 1);

            return Result::fail("El archivo debe ser menor a {$maxSizeMB}MB");
        }

        // Verificar tipo MIME real
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);

        if (!\in_array($mimeType, self::ALLOWED_AVATAR_TYPES, true)) {
            return Result::fail('Tipo de archivo no permitido. Solo se aceptan imágenes JPG, PNG o WebP');
        }

        // Verificar extensión
        $extension = \strtolower(\pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!\in_array($extension, self::ALLOWED_AVATAR_EXTENSIONS, true)) {
            return Result::fail('Extensión de archivo no permitida');
        }

        // Verificar que es una imagen válida
        $imageInfo = @\getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            return Result::fail('El archivo no es una imagen válida');
        }

        return Result::ok(true);
    }

    /**
     * Procesa y guarda una imagen con redimensionamiento
     *
     * @param string  $sourcePath Ruta temporal del archivo
     * @param string  $destPath   Ruta de destino
     * @param integer $maxWidth   Ancho máximo
     * @param integer $maxHeight  Alto máximo
     * @return Result
     */
    private function processAndSaveImage(
        string $sourcePath,
        string $destPath,
        int $maxWidth,
        int $maxHeight
    ): Result {
        $ok = ImageProcessor::resizeAndSave($sourcePath, $destPath, $maxWidth, $maxHeight);

        return $ok ? Result::ok(true) : Result::fail('Error al procesar la imagen');
    }

    /**
     * Elimina una foto específica.
     * Soporta URLs de Cloudinary y rutas locales (backward compat).
     *
     * @param string $relativeUrl URL de Cloudinary o ruta relativa local
     * @return Result
     */
    #[Override]
    public function deleteFile(string $relativeUrl): Result
    {
        // URLs de Cloudinary: extraer public_id y eliminar vía storage
        if (\str_starts_with($relativeUrl, 'https://res.cloudinary.com/')) {
            if ($this->fileStorage === null) {
                return Result::fail('Almacenamiento externo no disponible');
            }

            $path = (string) \parse_url($relativeUrl, PHP_URL_PATH);
            // Eliminar prefijo de entrega: /<cloud>/<resource_type>/upload/[v12345/]
            $publicId = (string) \preg_replace('#^/[^/]+/(?:image|raw|video)/upload/(?:v\d+/)?#', '', $path);
            $publicId = \pathinfo($publicId, PATHINFO_DIRNAME) . '/' . \pathinfo($publicId, PATHINFO_FILENAME);
            $publicId = \ltrim($publicId, '/');

            $destroyed = $this->fileStorage->destroy($publicId);

            return $destroyed
                ? Result::ok(true)
                : Result::fail('Error al eliminar el archivo de Cloudinary');
        }

        // Backward compat: rutas locales en storage/uploads/
        $relativePath = \str_replace('/storage/uploads/', '', $relativeUrl);
        $localBasePath = __DIR__ . '/../../storage/uploads';
        $filePath = $localBasePath . '/' . $relativePath;

        $realPath = \realpath($filePath);
        $realBasePath = \realpath($localBasePath);

        if ($realPath === false || $realBasePath === false || !\str_starts_with($realPath, $realBasePath)) {
            return Result::fail('Archivo no válido o fuera del directorio permitido');
        }

        if (!\is_file($realPath)) {
            return Result::fail('El archivo no existe');
        }

        if (@\unlink($realPath)) {
            return Result::ok(true);
        }

        return Result::fail('Error al eliminar el archivo');
    }

    /**
     * Obtiene información sobre límites de subida
     *
     * @param string $type Tipo de archivo ('avatar' o 'animal')
     * @return array{maxSize: int, maxSizeMB: float, allowedTypes: array, allowedExtensions: array}
     */
    #[Override]
    public function getUploadLimits(string $type = 'avatar'): array
    {
        if ($type === 'animal') {
            return [
                'maxSize' => self::MAX_ANIMAL_PHOTO_SIZE,
                'maxSizeMB' => \round(self::MAX_ANIMAL_PHOTO_SIZE / 1024 / 1024, 1),
                'allowedTypes' => self::ALLOWED_AVATAR_TYPES,
                'allowedExtensions' => self::ALLOWED_AVATAR_EXTENSIONS,
            ];
        }

        return [
            'maxSize' => self::MAX_AVATAR_SIZE,
            'maxSizeMB' => \round(self::MAX_AVATAR_SIZE / 1024 / 1024, 1),
            'allowedTypes' => self::ALLOWED_AVATAR_TYPES,
            'allowedExtensions' => self::ALLOWED_AVATAR_EXTENSIONS,
        ];
    }
}
