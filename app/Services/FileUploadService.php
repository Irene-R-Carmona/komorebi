<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\ImageProcessor;
use App\Core\Result;
use App\Exceptions\ConfigurationException;
use App\Services\Contracts\FileUploadServiceInterface;
use Exception;
use finfo;
use Random\RandomException;

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

    private string $uploadBasePath;
    private string $avatarPath;
    private string $animalPhotoPath;

    /**
     * @throws ConfigurationException
     */
    public function __construct()
    {
        $this->uploadBasePath = __DIR__ . '/../../storage/uploads';
        $this->avatarPath = $this->uploadBasePath . '/avatars';
        $this->animalPhotoPath = $this->uploadBasePath . '/animals';

        // Crear directorios si no existen
        $this->ensureDirectoriesExist();
    }

    /**
     * Sube un avatar de usuario
     *
     * @param array   $file   Archivo de $_FILES
     * @param integer $userId ID del usuario
     * @return Result URL relativa del avatar subido
     * @throws RandomException
     */
    #[\Override]
    public function uploadAvatar(array $file, int $userId): Result
    {
        // Validar archivo
        $validation = $this->validateFile(
            $file,
            self::MAX_AVATAR_SIZE
        );

        if ($validation->error !== null) {
            return $validation;
        }

        // Generar nombre único
        $extension = \strtolower(\pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = "user_{$userId}_" . \time() . '_' . \bin2hex(\random_bytes(8)) . ".$extension";
        $filepath = $this->avatarPath . '/' . $filename;

        // Eliminar avatar anterior si existe
        $this->deleteOldUserAvatars($userId);

        // Procesar y guardar imagen
        $saveResult = $this->processAndSaveImage(
            $file['tmp_name'],
            $filepath,
            self::AVATAR_MAX_WIDTH,
            self::AVATAR_MAX_HEIGHT
        );

        if ($saveResult->error !== null) {
            return $saveResult;
        }

        // Retornar URL relativa
        $relativeUrl = '/storage/uploads/avatars/' . $filename;

        return Result::ok($relativeUrl);
    }

    /**
     * Elimina el avatar de un usuario
     *
     * @param integer $userId ID del usuario
     * @return Result
     */
    #[\Override]
    public function deleteAvatar(int $userId): Result
    {
        try {
            $deleted = $this->deleteOldUserAvatars($userId);

            if ($deleted > 0) {
                return Result::ok(true);
            }

            return Result::fail('No se encontró ningún avatar para eliminar');
        } catch (Exception $e) {
            return Result::fail('Error al eliminar avatar: ' . $e->getMessage());
        }
    }

    /**
     * Sube una foto de animal
     *
     * @param array   $file     Archivo de $_FILES
     * @param integer $animalId ID del animal
     * @return Result URL relativa de la foto subida
     * @throws RandomException
     */
    #[\Override]
    public function uploadAnimalPhoto(array $file, int $animalId): Result
    {
        // Validar archivo
        $validation = $this->validateFile(
            $file,
            self::MAX_ANIMAL_PHOTO_SIZE
        );

        if ($validation->error !== null) {
            return $validation;
        }

        // Generar nombre único
        $extension = \strtolower(\pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = "animal_{$animalId}_" . \time() . '_' . \bin2hex(\random_bytes(8)) . ".$extension";
        $filepath = $this->animalPhotoPath . '/' . $filename;

        // Procesar y guardar imagen (más grande para animales)
        $saveResult = $this->processAndSaveImage(
            $file['tmp_name'],
            $filepath,
            1200,
            1200
        );

        if ($saveResult->error !== null) {
            return $saveResult;
        }

        // Retornar URL relativa
        $relativeUrl = '/storage/uploads/animals/' . $filename;

        return Result::ok($relativeUrl);
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
     * Elimina avatares antiguos de un usuario
     *
     * @param integer $userId ID del usuario
     * @return integer Número de archivos eliminados
     */
    private function deleteOldUserAvatars(int $userId): int
    {
        $pattern = $this->avatarPath . "/user_{$userId}_*.*";
        $files = \glob($pattern);
        $deleted = 0;

        if ($files) {
            foreach ($files as $file) {
                if (\is_file($file) && @\unlink($file)) {
                    $deleted++;
                }
            }
        }

        return $deleted;
    }

    /**
     * Elimina una foto específica
     *
     * @param string $relativeUrl URL relativa del archivo
     * @return Result
     */
    #[\Override]
    public function deleteFile(string $relativeUrl): Result
    {
        // Construir ruta absoluta desde URL relativa
        $relativePath = \str_replace('/storage/uploads/', '', $relativeUrl);
        $filePath = $this->uploadBasePath . '/' . $relativePath;

        // Validar que el archivo está en el directorio de uploads (seguridad)
        $realPath = \realpath($filePath);
        $realBasePath = \realpath($this->uploadBasePath);

        if ($realPath === false || !\str_starts_with($realPath, $realBasePath)) {
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
     * Asegura que los directorios de upload existan
     *
     * @throws ConfigurationException Si no se puede crear el directorio
     */
    private function ensureDirectoriesExist(): void
    {
        $directories = [
            $this->uploadBasePath,
            $this->avatarPath,
            $this->animalPhotoPath,
        ];

        foreach ($directories as $dir) {
            if (!\is_dir($dir) && !\mkdir($dir, 0o755, true) && !\is_dir($dir)) {
                throw ConfigurationException::directoryNotWritable($dir);
            }

            if (!\is_writable($dir)) {
                throw ConfigurationException::directoryNotWritable($dir);
            }
        }
    }

    /**
     * Obtiene información sobre límites de subida
     *
     * @param string $type Tipo de archivo ('avatar' o 'animal')
     * @return array{maxSize: int, maxSizeMB: float, allowedTypes: array, allowedExtensions: array}
     */
    #[\Override]
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
