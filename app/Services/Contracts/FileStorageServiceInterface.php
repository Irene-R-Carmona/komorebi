<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Core\Result;

/**
 * Contrato para almacenamiento externo de archivos.
 *
 * Las implementaciones concretas (Cloudinary, S3, filesystem local)
 * resuelven este contrato sin afectar a los consumidores.
 */
interface FileStorageServiceInterface
{
    /**
     * Sube una imagen y devuelve su URL pública en Result::ok($url).
     *
     * @param string $localPath Ruta absoluta al archivo temporal en disco
     * @param string $folder    Carpeta lógica de destino (p.ej. 'animals', 'avatars')
     * @param string $publicId  Identificador único dentro de la carpeta
     */
    public function uploadImage(string $localPath, string $folder, string $publicId): Result;

    /**
     * Sube un archivo binario (PDF, etc.) y devuelve su URL pública en Result::ok($url).
     *
     * @param string $localPath Ruta absoluta al archivo en disco
     * @param string $folder    Carpeta lógica de destino (p.ej. 'invoices')
     * @param string $publicId  Identificador único dentro de la carpeta
     */
    public function uploadRaw(string $localPath, string $folder, string $publicId): Result;

    /**
     * Elimina un archivo remoto.
     *
     * @param string $publicId     Identificador público del archivo
     * @param string $resourceType Tipo de recurso: 'image' | 'raw' | 'video'
     */
    public function destroy(string $publicId, string $resourceType = 'image'): bool;
}
