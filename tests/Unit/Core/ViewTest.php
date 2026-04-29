<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Raw;
use App\Core\View;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(View::class)]
final class ViewTest extends TestCase
{
    protected function setUp(): void
    {
        // Limpiar $viewsDir estático para tests que necesiten paths controlados
        $ref = new \ReflectionProperty(View::class, 'viewsDir');
        $ref->setValue(null, null);
    }

    // ─── getSecurityHeaders() ──────────────────────────────────────────────────

    public function testGetSecurityHeadersReturnsExpectedKeys(): void
    {
        $headers = View::getSecurityHeaders();

        self::assertArrayHasKey('Content-Security-Policy', $headers);
        self::assertArrayHasKey('X-Frame-Options', $headers);
        self::assertArrayHasKey('X-Content-Type-Options', $headers);
        self::assertArrayHasKey('Referrer-Policy', $headers);
        self::assertArrayHasKey('Permissions-Policy', $headers);
    }

    public function testGetSecurityHeadersXFrameOptionsIsDeny(): void
    {
        $headers = View::getSecurityHeaders();

        self::assertSame('DENY', $headers['X-Frame-Options']);
    }

    public function testGetSecurityHeadersXContentTypeOptionsIsNosniff(): void
    {
        $headers = View::getSecurityHeaders();

        self::assertSame('nosniff', $headers['X-Content-Type-Options']);
    }

    public function testGetSecurityHeadersCspContainsSelf(): void
    {
        $headers = View::getSecurityHeaders();

        self::assertStringContainsString("'self'", $headers['Content-Security-Policy']);
    }

    public function testGetSecurityHeadersCspContainsFrameAncestorsNone(): void
    {
        $headers = View::getSecurityHeaders();

        self::assertStringContainsString("frame-ancestors 'none'", $headers['Content-Security-Policy']);
    }

    public function testGetSecurityHeadersReferrerPolicyIsStrict(): void
    {
        $headers = View::getSecurityHeaders();

        self::assertSame('strict-origin-when-cross-origin', $headers['Referrer-Policy']);
    }

    public function testGetSecurityHeadersPermissionsPolicyDisablesGeo(): void
    {
        $headers = View::getSecurityHeaders();

        self::assertStringContainsString('geolocation=()', $headers['Permissions-Policy']);
    }

    public function testGetSecurityHeadersReturnsFiveHeaders(): void
    {
        $headers = View::getSecurityHeaders();

        self::assertCount(5, $headers);
    }

    // ─── safeReferer() ────────────────────────────────────────────────────────

    public function testSafeRefererReturnsFallbackWhenNoReferer(): void
    {
        unset($_SERVER['HTTP_REFERER']);

        self::assertSame('/', View::safeReferer());
    }

    public function testSafeRefererReturnsCustomFallbackWhenNoReferer(): void
    {
        unset($_SERVER['HTTP_REFERER']);

        self::assertSame('/dashboard', View::safeReferer('/dashboard'));
    }

    public function testSafeRefererReturnsRelativeUrl(): void
    {
        $_SERVER['HTTP_REFERER'] = '/menu';

        self::assertSame('/menu', View::safeReferer());
    }

    public function testSafeRefererReturnsFallbackForExternalHost(): void
    {
        $_SERVER['HTTP_REFERER'] = 'https://evil.example.com/phishing';

        $result = View::safeReferer('/');

        self::assertSame('/', $result);
    }

    public function testSafeRefererAcceptsSameHost(): void
    {
        // Simular que APP_URL es http://localhost
        $_SERVER['HTTP_REFERER'] = 'http://localhost/reservations';

        $result = View::safeReferer('/');

        self::assertSame('http://localhost/reservations', $result);
    }

    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_REFERER']);
        // Restaurar $viewsDir a null para que tests posteriores no hereden un path temporal corrupto
        $ref = new \ReflectionProperty(View::class, 'viewsDir');
        $ref->setValue(null, null);
    }

    // ─── img() ────────────────────────────────────────────────────────────────

    public function testImgReturnsBasicTag(): void
    {
        $html = View::img('/images/logo.png', 'Logo');

        self::assertStringContainsString('<img', $html);
        self::assertStringContainsString('src="/images/logo.png"', $html);
        self::assertStringContainsString('alt="Logo"', $html);
    }

    public function testImgDefaultsToLazyLoading(): void
    {
        $html = View::img('/img/cat.jpg', 'Cat');

        self::assertStringContainsString('loading="lazy"', $html);
    }

    public function testImgEagerLoadingCanBeSet(): void
    {
        $html = View::img('/img/hero.jpg', 'Hero', ['loading' => 'eager']);

        self::assertStringContainsString('loading="eager"', $html);
        self::assertStringNotContainsString('loading="lazy"', $html);
    }

    public function testImgIncludesAdditionalAttributes(): void
    {
        $html = View::img('/img/product.jpg', 'Product', ['width' => 400, 'height' => 300]);

        self::assertStringContainsString('width="400"', $html);
        self::assertStringContainsString('height="300"', $html);
    }

    public function testImgEscapesSrcForXss(): void
    {
        $html = View::img('"><script>alert(1)</script>', 'XSS');

        self::assertStringNotContainsString('<script>', $html);
    }

    public function testImgEscapesAltForXss(): void
    {
        $html = View::img('/img/safe.jpg', '"><script>xss</script>');

        self::assertStringNotContainsString('<script>', $html);
    }

    public function testImgSkipsNullAttributes(): void
    {
        $html = View::img('/img/product.jpg', 'P', ['class' => null]);

        self::assertStringNotContainsString('class=', $html);
    }

    public function testImgSkipsFalseAttributes(): void
    {
        $html = View::img('/img/product.jpg', 'P', ['hidden' => false]);

        self::assertStringNotContainsString('hidden=', $html);
    }

    public function testImgIncludesClassAttribute(): void
    {
        $html = View::img('/img/product.jpg', 'P', ['class' => 'hero-img rounded']);

        self::assertStringContainsString('class="hero-img rounded"', $html);
    }

    // ─── resolvePath() via renderToString ─────────────────────────────────────

    public function testComponentToStringThrowsForInvalidIdentifier(): void
    {
        // resolvePath() valida antes de ob_start() — usar componentToString para evitar buffer colgante
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Identificador de vista inválido');

        View::componentToString('../etc/passwd');
    }

    public function testComponentToStringThrowsForPathTraversal(): void
    {
        $this->expectException(RuntimeException::class);

        View::componentToString('../../config/database');
    }

    public function testComponentToStringThrowsForNonExistentView(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Vista no encontrada');

        View::componentToString('totally/nonexistent/view-that-does-not-exist');
    }

    // ─── componentToString() — success path ────────────────────────────────────

    public function testComponentToStringRendersSimpleViewWithData(): void
    {
        $tmpDir = \sys_get_temp_dir() . '/komorebi_view_tests_' . \getmypid();
        \mkdir($tmpDir, 0777, true);
        \file_put_contents($tmpDir . '/hello.php', '<?php echo htmlspecialchars($name, ENT_QUOTES, "UTF-8"); ?>');

        $ref = new \ReflectionProperty(View::class, 'viewsDir');
        $ref->setValue(null, $tmpDir . '/');

        $output = View::componentToString('hello', ['name' => 'World']);

        // cleanup
        \unlink($tmpDir . '/hello.php');
        \rmdir($tmpDir);

        self::assertSame('World', $output);
    }

    public function testComponentToStringEscapesHtmlSpecialChars(): void
    {
        $tmpDir = \sys_get_temp_dir() . '/komorebi_view_tests2_' . \getmypid();
        \mkdir($tmpDir, 0777, true);
        // The view just echoes the variable — escapeData() happens before the view receives it
        \file_put_contents($tmpDir . '/xss.php', '<?php echo $label; ?>');

        $ref = new \ReflectionProperty(View::class, 'viewsDir');
        $ref->setValue(null, $tmpDir . '/');

        $output = View::componentToString('xss', ['label' => '<script>alert(1)</script>']);

        \unlink($tmpDir . '/xss.php');
        \rmdir($tmpDir);

        self::assertStringContainsString('&lt;script&gt;', $output);
    }

    public function testComponentToStringEscapesNestedArrayData(): void
    {
        $tmpDir = \sys_get_temp_dir() . '/komorebi_view_tests3_' . \getmypid();
        \mkdir($tmpDir, 0777, true);
        \file_put_contents($tmpDir . '/nested.php', '<?php echo $items[0]; ?>');

        $ref = new \ReflectionProperty(View::class, 'viewsDir');
        $ref->setValue(null, $tmpDir . '/');

        $output = View::componentToString('nested', ['items' => ['<b>bold</b>']]);

        \unlink($tmpDir . '/nested.php');
        \rmdir($tmpDir);

        self::assertStringContainsString('&lt;b&gt;', $output);
    }

    // ─── renderToString() — layout = null ─────────────────────────────────────

    public function testRenderToStringWithNoLayoutOutputsViewDirectly(): void
    {
        $tmpDir = \sys_get_temp_dir() . '/komorebi_render_' . \getmypid();
        \mkdir($tmpDir, 0777, true);
        \file_put_contents($tmpDir . '/simple.php', '<?php echo $title; ?>');

        $ref = new \ReflectionProperty(View::class, 'viewsDir');
        $ref->setValue(null, $tmpDir . '/');

        $output = View::renderToString('simple', ['title' => 'Hello'], [], null);

        \unlink($tmpDir . '/simple.php');
        \rmdir($tmpDir);

        self::assertSame('Hello', $output);
    }

    public function testRenderToStringExtractExtraCssFromData(): void
    {
        $tmpDir = \sys_get_temp_dir() . '/komorebi_render_css_' . \getmypid();
        \mkdir($tmpDir, 0777, true);
        \file_put_contents($tmpDir . '/page.php', '<?php echo "page"; ?>');

        $ref = new \ReflectionProperty(View::class, 'viewsDir');
        $ref->setValue(null, $tmpDir . '/');

        $output = View::renderToString('page', ['extraCss' => ['style.css']], [], null);

        \unlink($tmpDir . '/page.php');
        \rmdir($tmpDir);

        self::assertSame('page', $output);
    }

    public function testRenderToStringExtractExtraJsFromData(): void
    {
        $tmpDir = \sys_get_temp_dir() . '/komorebi_render_js_' . \getmypid();
        \mkdir($tmpDir, 0777, true);
        \file_put_contents($tmpDir . '/page.php', '<?php echo "js_page"; ?>');

        $ref = new \ReflectionProperty(View::class, 'viewsDir');
        $ref->setValue(null, $tmpDir . '/');

        $output = View::renderToString('page', ['extraJs' => ['app.js']], [], null);

        \unlink($tmpDir . '/page.php');
        \rmdir($tmpDir);

        self::assertSame('js_page', $output);
    }

    public function testRenderToStringWithLayoutRendersLayout(): void
    {
        $tmpDir = \sys_get_temp_dir() . '/komorebi_render_layout_' . \getmypid();
        \mkdir($tmpDir . '/layouts', 0777, true);
        \file_put_contents($tmpDir . '/inner.php', '<?php echo "inner-content"; ?>');
        \file_put_contents($tmpDir . '/layouts/simple.php', '<?php echo $content; ?>');

        $ref = new \ReflectionProperty(View::class, 'viewsDir');
        $ref->setValue(null, $tmpDir . '/');

        $output = View::renderToString('inner', [], [], 'simple');

        \unlink($tmpDir . '/inner.php');
        \unlink($tmpDir . '/layouts/simple.php');
        \rmdir($tmpDir . '/layouts');
        \rmdir($tmpDir);

        self::assertStringContainsString('inner-content', $output);
    }

    // ─── component() ──────────────────────────────────────────────────────────

    public function testComponentOutputsRenderedContent(): void
    {
        $tmpDir = \sys_get_temp_dir() . '/komorebi_comp_' . \getmypid();
        \mkdir($tmpDir, 0777, true);
        \file_put_contents($tmpDir . '/widget.php', '<?php echo $label; ?>');

        $ref = new \ReflectionProperty(View::class, 'viewsDir');
        $ref->setValue(null, $tmpDir . '/');

        \ob_start();
        View::component('widget', ['label' => 'ButtonText']);
        $output = (string) \ob_get_clean();

        \unlink($tmpDir . '/widget.php');
        \rmdir($tmpDir);

        self::assertSame('ButtonText', $output);
    }

    // ─── capture() scope — section methods ───────────────────────────────────

    public function testCaptureStartEndSectionReturnsSectionContent(): void
    {
        $tmpDir = \sys_get_temp_dir() . '/komorebi_sections_' . \getmypid();
        \mkdir($tmpDir, 0777, true);
        \file_put_contents(
            $tmpDir . '/sectioned.php',
            '<?php $this->start("content"); echo "section-body"; $this->end(); ?>'
        );

        $ref = new \ReflectionProperty(View::class, 'viewsDir');
        $ref->setValue(null, $tmpDir . '/');

        $output = View::componentToString('sectioned');

        \unlink($tmpDir . '/sectioned.php');
        \rmdir($tmpDir);

        self::assertSame('section-body', $output);
    }

    public function testCaptureExtendDoesNotBreakRendering(): void
    {
        $tmpDir = \sys_get_temp_dir() . '/komorebi_extend_' . \getmypid();
        \mkdir($tmpDir, 0777, true);
        \file_put_contents(
            $tmpDir . '/extending.php',
            '<?php $this->extend("base"); echo "extended-content"; ?>'
        );

        $ref = new \ReflectionProperty(View::class, 'viewsDir');
        $ref->setValue(null, $tmpDir . '/');

        $output = View::componentToString('extending');

        \unlink($tmpDir . '/extending.php');
        \rmdir($tmpDir);

        self::assertSame('extended-content', $output);
    }

    // ─── escapeData() — Raw and other scalar types ────────────────────────────

    public function testComponentToStringPassesThroughRawDataUnescaped(): void
    {
        $tmpDir = \sys_get_temp_dir() . '/komorebi_raw_' . \getmypid();
        \mkdir($tmpDir, 0777, true);
        \file_put_contents($tmpDir . '/rawview.php', '<?php echo $html; ?>');

        $ref = new \ReflectionProperty(View::class, 'viewsDir');
        $ref->setValue(null, $tmpDir . '/');

        $output = View::componentToString('rawview', ['html' => new Raw('<b>bold</b>')]);

        \unlink($tmpDir . '/rawview.php');
        \rmdir($tmpDir);

        self::assertSame('<b>bold</b>', $output);
    }

    public function testComponentToStringPassesThroughIntDataUnchanged(): void
    {
        $tmpDir = \sys_get_temp_dir() . '/komorebi_int_' . \getmypid();
        \mkdir($tmpDir, 0777, true);
        \file_put_contents($tmpDir . '/intview.php', '<?php echo $count; ?>');

        $ref = new \ReflectionProperty(View::class, 'viewsDir');
        $ref->setValue(null, $tmpDir . '/');

        $output = View::componentToString('intview', ['count' => 42]);

        \unlink($tmpDir . '/intview.php');
        \rmdir($tmpDir);

        self::assertSame('42', $output);
    }
}
