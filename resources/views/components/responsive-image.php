<!-- Componente de Imagen Responsive Optimizada -->
<!-- Uso: Incluir en vistas que muestren imágenes de productos, cafés, etc. -->

<?php
/**
 * Genera un elemento <picture> con srcset optimizado
 *
 * @param string $imagePath Ruta base de la imagen (sin extensión)
 * @param string $alt Texto alternativo
 * @param array $sizes Tamaños disponibles [width => descriptor]
 * @param bool $lazy Activar lazy loading
 * @param string $className Clases CSS adicionales
 */
function renderResponsiveImage(
    string $imagePath,
    string $alt,
    array $sizes = [320, 640, 768, 1024, 1280],
    bool $lazy = true,
    string $className = ''
): void {
    // Detectar si existe versión WebP
    $baseDir = dirname($imagePath);
    $baseName = pathinfo($imagePath, PATHINFO_FILENAME);
    $extension = pathinfo($imagePath, PATHINFO_EXTENSION) ?: 'jpg';

    $hasWebP = file_exists(str_replace(".$extension", '.webp', $imagePath));

    ?>
    <picture class="responsive-image <?= htmlspecialchars($className) ?>">
        <?php if ($hasWebP): ?>
            <!-- WebP sources -->
            <source
                type="image/webp"
                srcset="<?php
                            $srcset = [];
            foreach ($sizes as $width) {
                $webpPath = "{$baseDir}/{$baseName}-{$width}w.webp";
                if (file_exists($webpPath)) {
                    $srcset[] = "{$webpPath} {$width}w";
                }
            }
            echo implode(', ', $srcset);
            ?>"
                sizes="(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 33vw">
        <?php endif; ?>

        <!-- Fallback JPEG/PNG sources -->
        <source
            type="image/<?= $extension === 'jpg' ? 'jpeg' : $extension ?>"
            srcset="<?php
                    $srcset = [];
    foreach ($sizes as $width) {
        $resizedPath = "{$baseDir}/{$baseName}-{$width}w.{$extension}";
        if (file_exists($resizedPath)) {
            $srcset[] = "{$resizedPath} {$width}w";
        }
    }
    // Fallback a imagen original si no hay versiones
    if (empty($srcset)) {
        $srcset[] = "{$imagePath} " . ($sizes[count($sizes) - 1] ?? 1024) . 'w';
    }
    echo implode(', ', $srcset);
    ?>"
            sizes="(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 33vw">

        <!-- Fallback img -->
        <img
            src="<?= $imagePath ?>"
            alt="<?= htmlspecialchars($alt) ?>"
            <?= $lazy ? 'loading="lazy"' : '' ?>
            decoding="async"
            class="responsive-image__img"
            data-delegate-load="true">
    </picture>
<?php
}

/**
 * Genera srcset simple para imágenes
 */
function generateSrcset(string $basePath, array $widths = [320, 640, 1024]): string
{
    $srcset = [];
    $baseDir = dirname($basePath);
    $fileName = pathinfo($basePath, PATHINFO_FILENAME);
    $ext = pathinfo($basePath, PATHINFO_EXTENSION);

    foreach ($widths as $width) {
        $path = "{$baseDir}/{$fileName}-{$width}w.{$ext}";
        if (file_exists($_SERVER['DOCUMENT_ROOT'] . $path)) {
            $srcset[] = "{$path} {$width}w";
        }
    }

    return implode(', ', $srcset);
}
?>

<style>
    /* Estilos del componente de imagen responsive */
    .responsive-image {
        position: relative;
        display: block;
        overflow: hidden;
        background: linear-gradient(90deg,
                var(--color-fondo, #f5f5f5) 0%,
                var(--color-fondo-alt, #e8e8e8) 50%,
                var(--color-fondo, #f5f5f5) 100%);
        background-size: 200% 100%;
        animation: shimmer 1.5s ease-in-out infinite;
    }

    .responsive-image__img {
        width: 100%;
        height: auto;
        display: block;
        opacity: 0;
        transition: opacity 0.3s ease-in-out;
    }

    .responsive-image__img.loaded {
        opacity: 1;
        animation: none;
    }

    /* Aspect ratio containers */
    .responsive-image--16-9 {
        aspect-ratio: 16 / 9;
    }

    .responsive-image--4-3 {
        aspect-ratio: 4 / 3;
    }

    .responsive-image--1-1 {
        aspect-ratio: 1 / 1;
    }

    .responsive-image--16-9 .responsive-image__img,
    .responsive-image--4-3 .responsive-image__img,
    .responsive-image--1-1 .responsive-image__img {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    @keyframes shimmer {
        0% {
            background-position: 200% 0;
        }

        100% {
            background-position: -200% 0;
        }
    }

    /* Object fit options */
    .responsive-image--cover .responsive-image__img {
        object-fit: cover;
    }

    .responsive-image--contain .responsive-image__img {
        object-fit: contain;
    }

    /* Rounded corners */
    .responsive-image--rounded {
        border-radius: var(--radio-md, 8px);
    }

    .responsive-image--rounded-lg {
        border-radius: var(--radio-lg, 16px);
    }

    .responsive-image--circle {
        border-radius: 50%;
    }
</style>

<!-- Ejemplo de uso -->
<?php if (false): // Solo para documentación
    ?>
    <div class="product-card">
        <?php
            renderResponsiveImage(
                '/uploads/products/cafe-latte.jpg',
                'Café Latte con arte latte en forma de corazón',
                [320, 640, 1024],
                true,
                'responsive-image--16-9 responsive-image--rounded'
            );
    ?>
        <h3>Café Latte</h3>
        <p>Delicioso café con leche...</p>
    </div>

    <!-- O usando srcset directamente -->
    <img
        src="/uploads/products/matcha.jpg"
        srcset="<?= generateSrcset('/uploads/products/matcha.jpg') ?>"
        sizes="(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 33vw"
        alt="Té Matcha tradicional japonés"
        loading="lazy"
        class="product-image">
<?php endif; ?>
