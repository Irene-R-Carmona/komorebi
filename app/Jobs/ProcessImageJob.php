<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Core\ImageProcessor;
use App\Core\Logger;
use App\Exceptions\FilesystemException;
use Throwable;

/**
 * Job para procesamiento asíncrono de imágenes
 *
 * Genera thumbnails y diferentes tamaños de imágenes para optimizar
 * la carga de las páginas. No bloquea la petición HTTP mientras se
 * procesan las imágenes.
 *
 * Payload esperado:
 * - source_path: string (ruta absoluta del archivo original)
 * - sizes: array<array{width: int, height: int, suffix: string}> (tamaños a generar)
 * - quality: int (calidad JPEG 1-100, opcional, por defecto: 85)
 * - preserve_original: bool (opcional, por defecto: true)
 *
 * Ejemplo:
 * ```php
 * Queue::push(ProcessImageJob::class, [
 *     'source_path' => '/app/storage/uploads/photo.jpg',
 *     'sizes' => [
 *         ['width' => 150, 'height' => 150, 'suffix' => 'thumb'],
 *         ['width' => 800, 'height' => 600, 'suffix' => 'medium'],
 *     ],
 *     'quality' => 85,
 * ]);
 * ```
 *
 * @package App\Jobs
 */
final class ProcessImageJob implements JobInterface
{
    /** @var int Calidad por defecto para JPEG */
    private const int DEFAULT_QUALITY = 85;

    /** @var array<string> Tipos MIME soportados */
    private const array SUPPORTED_TYPES = [
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/gif',
        'image/webp',
    ];

    /**
     * Ejecuta el procesamiento de la imagen
     *
     * @param array<string, mixed> $payload Datos del procesamiento
     * @return void
     * @throws FilesystemException Si falla el procesamiento
     */
    #[\Override]
    public function handle(array $payload): void
    {
        $this->validatePayload($payload);

        // Narrow types for static analysis and runtime safety
        if (!isset($payload['source_path']) || !is_string($payload['source_path'])) {
            throw new FilesystemException('Campo source_path inválido en ProcessImageJob');
        }
        if (!isset($payload['sizes']) || !is_array($payload['sizes'])) {
            throw new FilesystemException('Campo sizes inválido en ProcessImageJob');
        }

        // Normalizar y castear entradas para evitar types="mixed"
        $sourcePath = (string) $payload['source_path'];
        $sizes = $payload['sizes'];
        $quality = isset($payload['quality']) ? (int) $payload['quality'] : self::DEFAULT_QUALITY;

        try {
            // Verificar que el archivo existe
            if (!\file_exists($sourcePath)) {
                throw new FilesystemException("Archivo de origen no encontrado: {$sourcePath}");
            }

            // Verificar tipo de imagen
            $imageInfo = \getimagesize($sourcePath);
            if ($imageInfo === false) {
                throw new FilesystemException("No se pudo leer la información de la imagen: {$sourcePath}");
            }

            // getimagesize garantiza 'mime' cuando devuelve array
            $mime = (string) $imageInfo['mime'];

            if (!\in_array($mime, self::SUPPORTED_TYPES, true)) {
                throw FilesystemException::withMessage(
                    "Tipo de imagen no soportado: {$mime}"
                );
            }

            // Procesar cada tamaño solicitado
            foreach ($sizes as $size) {
                $w = isset($size['width']) ? (int) $size['width'] : 0;
                $h = isset($size['height']) ? (int) $size['height'] : 0;
                $suf = isset($size['suffix']) ? (string) $size['suffix'] : '';

                if ($w <= 0 || $h <= 0 || $suf === '') {
                    // saltar tamaños inválidos
                    continue;
                }

                $this->generateThumbnail($sourcePath, $w, $h, $suf, $quality);
            }

            Logger::info('[ProcessImageJob] Imagen procesada correctamente', [
                'source' => $sourcePath,
                'sizes_generated' => (int) \count($sizes),
            ]);
        } catch (Throwable $e) {
            Logger::error('[ProcessImageJob] Error al procesar imagen', [
                'source' => $sourcePath,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Genera un thumbnail usando ImageProcessor centralizado
     *
     * @param string  $sourcePath   Ruta del archivo original
     * @param integer $targetWidth  Ancho deseado
     * @param integer $targetHeight Alto deseado
     * @param string  $suffix       Sufijo para el nombre del archivo
     * @param integer $quality      Calidad
     * @return void
     */
    private function generateThumbnail(
        string $sourcePath,
        int $targetWidth,
        int $targetHeight,
        string $suffix,
        int $quality,
    ): void {
        $pathInfo    = pathinfo($sourcePath);
        $dirname     = $pathInfo['dirname'] ?? '';
        $filename    = $pathInfo['filename'] ?? basename($sourcePath);
        $extension   = $pathInfo['extension'] ?? 'jpg';
        $destPath    = rtrim($dirname, '/') . '/' . $filename . '_' . $suffix . '.' . $extension;

        $saved = ImageProcessor::resizeAndSave($sourcePath, $destPath, $targetWidth, $targetHeight, $quality);

        if (!$saved) {
            throw FilesystemException::withMessage(
                "No se pudo guardar el thumbnail: {$destPath}"
            );
        }

        Logger::debug('[ProcessImageJob] Thumbnail generado', [
            'source'      => $sourcePath,
            'destination' => $destPath,
            'size'        => "{$targetWidth}x{$targetHeight}",
        ]);
    }

    /**
     * Valida que el payload tenga los campos requeridos
     *
     * @param array<string, mixed> $payload
     * @return void
     * @throws FilesystemException Si falta algún campo requerido
     */
    private function validatePayload(array $payload): void
    {
        if (!isset($payload['source_path']) || empty($payload['source_path'])) {
            throw FilesystemException::withMessage(
                'Campo requerido ausente en ProcessImageJob: source_path'
            );
        }

        if (!isset($payload['sizes']) || !\is_array($payload['sizes']) || empty($payload['sizes'])) {
            throw FilesystemException::withMessage(
                'Campo requerido ausente o inválido en ProcessImageJob: sizes'
            );
        }

        // Validar estructura de cada tamaño
        foreach ($payload['sizes'] as $index => $size) {
            if (!isset($size['width'], $size['height'], $size['suffix'])) {
                throw FilesystemException::withMessage(
                    "Estructura de size inválida en índice {$index}. Requerido: width, height, suffix"
                );
            }
        }
    }
}
