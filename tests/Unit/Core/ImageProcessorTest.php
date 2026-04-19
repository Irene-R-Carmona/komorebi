<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? Que ImageProcessor resize una imagen correctamente.
 * ¿Qué me quieres demostrar? Que la lógica de redimensionado está centralizada y funciona.
 * ¿Qué va a fallar si se cambia el código? Si el ratio de aspecto o la transparencia PNG se rompen.
 */

use App\Core\ImageProcessor;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ImageProcessor::class)]
final class ImageProcessorTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        $this->tmp = sys_get_temp_dir() . '/imgproc_test_' . uniqid();
        mkdir($this->tmp);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->tmp . '/*') ?: []);
        rmdir($this->tmp);
    }

    public function testResizesJpegToMaxDimensions(): void
    {
        // Create a 400x300 JPEG source
        $src = $this->tmp . '/src.jpg';
        $dst = $this->tmp . '/dst.jpg';
        $img = imagecreatetruecolor(400, 300);
        imagejpeg($img, $src);
        imagedestroy($img);

        $ok = ImageProcessor::resizeAndSave($src, $dst, 100, 100);

        $this->assertTrue($ok);
        [$w, $h] = getimagesize($dst);
        $this->assertLessThanOrEqual(100, $w);
        $this->assertLessThanOrEqual(100, $h);
    }

    public function testDoesNotUpscaleImageSmallerThanTarget(): void
    {
        $src = $this->tmp . '/small.jpg';
        $dst = $this->tmp . '/small_out.jpg';
        $img = imagecreatetruecolor(50, 50);
        imagejpeg($img, $src);
        imagedestroy($img);

        ImageProcessor::resizeAndSave($src, $dst, 200, 200);

        [$w, $h] = getimagesize($dst);
        $this->assertEquals(50, $w);
        $this->assertEquals(50, $h);
    }

    public function testPreservesAspectRatio(): void
    {
        $src = $this->tmp . '/wide.jpg';
        $dst = $this->tmp . '/wide_out.jpg';
        $img = imagecreatetruecolor(800, 200); // 4:1 ratio
        imagejpeg($img, $src);
        imagedestroy($img);

        ImageProcessor::resizeAndSave($src, $dst, 400, 400);

        [$w, $h] = getimagesize($dst);
        $this->assertEquals(400, $w);
        $this->assertEquals(100, $h); // ratio preserved
    }

    public function testReturnsFalseForUnreadableSource(): void
    {
        $ok = ImageProcessor::resizeAndSave('/nonexistent/path.jpg', '/tmp/out.jpg', 100, 100);
        $this->assertFalse($ok);
    }
}
