<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * ¿Qué me quieres demostrar?
 * ¿Qué va a fallar en este test si se cambia el código?
 */

namespace Tests\Integration;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * Badge Component Test Suite
 *
 * Tests para el componente de badge/etiqueta reutilizable.
 * Verifica renderizado, variantes, tamaños e iconos.
 *
 * Run with:
 * docker compose exec app php vendor/bin/phpunit tests/Integration/BadgeComponentTest.php --testdox
 */
#[CoversNothing]
final class BadgeComponentTest extends TestCase
{
    private string $componentPath;

    protected function setUp(): void
    {
        $this->componentPath = __DIR__ . '/../../resources/views/components/badge.php';

        if (!\file_exists($this->componentPath)) {
            $this->markTestSkipped('Badge component not yet created');
        }
    }

    public function testBadgeComponentExists(): void
    {
        $this->assertFileExists($this->componentPath);
    }

    public function testRenderBadgeFunctionExists(): void
    {
        require_once $this->componentPath;
        $this->assertTrue(\function_exists('renderBadge'));
    }

    public function testRenderBasicBadge(): void
    {
        require_once $this->componentPath;

        $html = \renderBadge(['label' => 'Test']);

        $this->assertStringContainsString('badge', $html);
        $this->assertStringContainsString('badge--neutral', $html);
        $this->assertStringContainsString('badge--md', $html);
        $this->assertStringContainsString('badge__label', $html);
        $this->assertStringContainsString('Test', $html);
    }

    public function testBadgeVariants(): void
    {
        require_once $this->componentPath;

        $variants = ['success', 'warning', 'danger', 'info', 'neutral'];

        foreach ($variants as $variant) {
            $html = \renderBadge([
                'label' => \ucfirst($variant),
                'variant' => $variant,
            ]);

            $this->assertStringContainsString("badge--{$variant}", $html);
        }
    }

    public function testBadgeSizes(): void
    {
        require_once $this->componentPath;

        $sizes = ['sm', 'md', 'lg'];

        foreach ($sizes as $size) {
            $html = \renderBadge([
                'label' => \ucfirst($size),
                'size' => $size,
            ]);

            $this->assertStringContainsString("badge--{$size}", $html);
        }
    }

    public function testBadgeWithIcon(): void
    {
        require_once $this->componentPath;

        $html = \renderBadge([
            'label' => 'Alert',
            'icon' => 'warning',
        ]);

        $this->assertStringContainsString('badge__icon', $html);
        $this->assertStringContainsString('material-symbols-outlined', $html);
        $this->assertStringContainsString('warning', $html);
        $this->assertStringContainsString('aria-hidden="true"', $html);
    }

    public function testBadgeWithDot(): void
    {
        require_once $this->componentPath;

        $html = \renderBadge([
            'label' => 'Online',
            'dot' => true,
        ]);

        $this->assertStringContainsString('badge--dot', $html);
        $this->assertStringContainsString('badge__dot', $html);
    }

    public function testBadgeDotHidesIcon(): void
    {
        require_once $this->componentPath;

        $html = \renderBadge([
            'label' => 'Status',
            'icon' => 'circle',
            'dot' => true,
        ]);

        // Dot mode should hide icon
        $this->assertStringNotContainsString('badge__icon', $html);
        $this->assertStringContainsString('badge__dot', $html);
    }

    public function testBadgeWithAdditionalClass(): void
    {
        require_once $this->componentPath;

        $html = \renderBadge([
            'label' => 'Test',
            'class' => 'custom-badge',
        ]);

        $this->assertStringContainsString('custom-badge', $html);
    }

    public function testBadgeWithAttributes(): void
    {
        require_once $this->componentPath;

        $html = \renderBadge([
            'label' => 'Test',
            'attributes' => [
                'id' => 'badge-1',
                'data-value' => '5',
            ],
        ]);

        $this->assertStringContainsString('id="badge-1"', $html);
        $this->assertStringContainsString('data-value="5"', $html);
    }

    public function testBadgeEscapesHtmlInLabel(): void
    {
        require_once $this->componentPath;

        $html = \renderBadge([
            'label' => '<script>alert("XSS")</script>',
        ]);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testBadgeEscapesHtmlInIcon(): void
    {
        require_once $this->componentPath;

        $html = \renderBadge([
            'label' => 'Test',
            'icon' => '<img src=x onerror=alert(1)>',
        ]);

        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringContainsString('&lt;img', $html);
    }

    public function testBadgeEscapesHtmlInAttributes(): void
    {
        require_once $this->componentPath;

        $html = \renderBadge([
            'label' => 'Test',
            'attributes' => [
                'onclick' => '<script>alert("XSS")</script>',
            ],
        ]);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testBadgeDefaultsCorrectly(): void
    {
        require_once $this->componentPath;

        $html = \renderBadge(['label' => 'Test']);

        // Should have default variant
        $this->assertStringContainsString('badge--neutral', $html);
        // Should have default size
        $this->assertStringContainsString('badge--md', $html);
        // Should not have dot
        $this->assertStringNotContainsString('badge--dot', $html);
        // Should not have icon
        $this->assertStringNotContainsString('badge__icon', $html);
    }

    public function testBadgeLabelIsRequired(): void
    {
        require_once $this->componentPath;

        // Even with empty props, should have default label
        $html = \renderBadge([]);
        $this->assertStringContainsString('Badge', $html); // Default label
    }

    public function testBadgeUsesSpanElement(): void
    {
        require_once $this->componentPath;

        $html = \renderBadge(['label' => 'Test']);

        $this->assertStringStartsWith('<span', \trim($html));
        $this->assertStringEndsWith('</span>', \trim($html));
    }

    public function testBadgeWithAllProps(): void
    {
        require_once $this->componentPath;

        $html = \renderBadge([
            'label' => 'Active',
            'variant' => 'success',
            'size' => 'lg',
            'icon' => 'check_circle',
            'class' => 'custom-badge',
            'attributes' => [
                'id' => 'status-badge',
                'data-status' => 'active',
            ],
        ]);

        $this->assertStringContainsString('Active', $html);
        $this->assertStringContainsString('badge--success', $html);
        $this->assertStringContainsString('badge--lg', $html);
        $this->assertStringContainsString('check_circle', $html);
        $this->assertStringContainsString('custom-badge', $html);
        $this->assertStringContainsString('id="status-badge"', $html);
        $this->assertStringContainsString('data-status="active"', $html);
    }

    public function testBadgeCounterUse(): void
    {
        require_once $this->componentPath;

        $html = \renderBadge([
            'label' => '5',
            'variant' => 'danger',
            'size' => 'sm',
        ]);

        $this->assertStringContainsString('5', $html);
        $this->assertStringContainsString('badge--danger', $html);
        $this->assertStringContainsString('badge--sm', $html);
    }
}
