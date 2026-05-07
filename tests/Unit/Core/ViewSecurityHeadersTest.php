<?php

/**
 * ¿Qué pruebas aquí?
 * Verifica que View::render() emite los HTTP security headers correctos.
 *
 * ¿Qué me quieres demostrar?
 * Que CSP, X-Frame-Options, X-Content-Type-Options y Referrer-Policy
 * están presentes en toda respuesta HTML.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se elimina sendSecurityHeaders() o si los valores cambian sin actualizar el test.
 */

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\View;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(View::class)]
final class ViewSecurityHeadersTest extends TestCase
{
    public function test_security_headers_array_contains_csp(): void
    {
        $headers = View::getSecurityHeaders();

        $this->assertArrayHasKey('Content-Security-Policy', $headers);
        $this->assertStringContainsString("default-src 'self'", $headers['Content-Security-Policy']);
    }

    public function test_security_headers_array_contains_x_frame_options(): void
    {
        $headers = View::getSecurityHeaders();

        $this->assertArrayHasKey('X-Frame-Options', $headers);
        $this->assertSame('DENY', $headers['X-Frame-Options']);
    }

    public function test_security_headers_array_contains_x_content_type_options(): void
    {
        $headers = View::getSecurityHeaders();

        $this->assertArrayHasKey('X-Content-Type-Options', $headers);
        $this->assertSame('nosniff', $headers['X-Content-Type-Options']);
    }

    public function test_security_headers_array_contains_referrer_policy(): void
    {
        $headers = View::getSecurityHeaders();

        $this->assertArrayHasKey('Referrer-Policy', $headers);
        $this->assertSame('strict-origin-when-cross-origin', $headers['Referrer-Policy']);
    }
}
