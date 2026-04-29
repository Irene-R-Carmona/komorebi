<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

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
}
