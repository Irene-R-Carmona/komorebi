<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Utility for GD-based image resizing.
 * Keeps aspect ratio, does not upscale, preserves PNG/WebP alpha.
 */
final class ImageProcessor
{
    /**
     * Resize $sourcePath to fit within $maxWidth × $maxHeight, save to $destPath.
     * Format inferred from file extension of $destPath.
     * Returns false on any GD/IO error.
     */
    public static function resizeAndSave(
        string $sourcePath,
        string $destPath,
        int    $maxWidth,
        int    $maxHeight,
        int    $quality = 85,
    ): bool {
        if (!file_exists($sourcePath) || !is_readable($sourcePath)) {
            return false;
        }

        $info = @getimagesize($sourcePath);
        if ($info === false) {
            return false;
        }

        [$width, $height, $type] = $info;

        $source = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($sourcePath),
            IMAGETYPE_PNG  => @imagecreatefrompng($sourcePath),
            IMAGETYPE_GIF  => @imagecreatefromgif($sourcePath),
            IMAGETYPE_WEBP => @imagecreatefromwebp($sourcePath),
            default        => false,
        };

        if ($source === false) {
            return false;
        }

        $ratio = min($maxWidth / $width, $maxHeight / $height);

        if ($ratio < 1.0) {
            $newW   = (int) round($width * $ratio);
            $newH   = (int) round($height * $ratio);
            $canvas = imagecreatetruecolor($newW, $newH);

            if ($canvas === false) {
                imagedestroy($source);
                return false;
            }

            if ($type === IMAGETYPE_PNG || $type === IMAGETYPE_WEBP) {
                imagealphablending($canvas, false);
                imagesavealpha($canvas, true);
                $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
                if ($transparent !== false) {
                    imagefill($canvas, 0, 0, $transparent);
                }
            }

            imagecopyresampled($canvas, $source, 0, 0, 0, 0, $newW, $newH, $width, $height);
            imagedestroy($source);
            $source = $canvas;
        }
        // If ratio >= 1.0, no resize needed — save as-is

        $ext   = strtolower(pathinfo($destPath, PATHINFO_EXTENSION));
        $saved = match ($ext) {
            'jpg', 'jpeg' => imagejpeg($source, $destPath, $quality),
            'png'         => imagepng($source, $destPath, (int) round((100 - $quality) / 10)),
            'webp'        => imagewebp($source, $destPath, $quality),
            'gif'         => imagegif($source, $destPath),
            default       => false,
        };

        imagedestroy($source);

        if ($saved) {
            chmod($destPath, 0644);
        }

        return (bool) $saved;
    }
}
