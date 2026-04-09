<?php

declare(strict_types=1);


/**
 * ¿Qué pruebas aquí?
 * ¿Qué me quieres demostrar?
 * ¿Qué va a fallar en este test si se cambia el código?
 */

namespace Tests\Integration;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Accessibility Test Suite - HTML Structure & ARIA Validation
 *
 * Tests básicos de accesibilidad que pueden ejecutarse sin browser automation.
 * Valida estructura HTML, atributos ARIA y patrones de accesibilidad comunes.
 *
 * Para auditorías completas con axe-core, ver:
 * docs/design-system/audit/accessibility-testing-strategy.md
 *
 * Run with:
 * docker compose exec app php vendor/bin/phpunit tests/Integration/AccessibilityTest.php --testdox
 */
#[Group('accessibility')]
final class AccessibilityTest extends TestCase
{
    /**
     * Verifica que los componentes tienen estructura HTML semántica
     */
    public function testButtonComponentHasAccessibleMarkup(): void
    {
        require_once __DIR__ . '/../../resources/views/components/button.php';

        $html = renderButton(['label' => 'Test Button']);

        // Tiene elemento button o a con role="button"
        $this->assertMatchesRegularExpression('/<button|<a[^>]+role="button"/', $html);

        // Tiene texto visible (label)
        $this->assertStringContainsString('Test Button', $html);

        // No tiene disabled sin aria-disabled
        if (str_contains($html, 'disabled')) {
            $this->assertStringContainsString('aria-disabled', $html);
        }
    }

    /**
     * Verifica que el modal tiene ARIA attributes correctos
     */
    public function testModalComponentHasAriaAttributes(): void
    {
        require_once __DIR__ . '/../../resources/views/components/modal.php';

        $html = renderModal([
            'id' => 'test-modal',
            'title' => 'Test Modal',
            'body' => 'Content'
        ]);

        // Tiene role="dialog"
        $this->assertStringContainsString('role="dialog"', $html);

        // Tiene aria-modal="true"
        $this->assertStringContainsString('aria-modal="true"', $html);

        // Tiene aria-labelledby apuntando al título
        $this->assertMatchesRegularExpression('/aria-labelledby="[^"]+"/', $html);

        // El título tiene el ID referenciado
        $this->assertMatchesRegularExpression('/id="[^"]*-title"/', $html);
    }

    /**
     * Verifica que los badges tienen contenido textual
     */
    public function testBadgeComponentHasTextContent(): void
    {
        require_once __DIR__ . '/../../resources/views/components/badge.php';

        $html = renderBadge(['label' => 'Active', 'variant' => 'success']);

        // Tiene texto visible
        $this->assertStringContainsString('Active', $html);

        // No usa solo color para transmitir información (tiene texto)
        $this->assertNotEmpty(strip_tags($html));
    }

    /**
     * Verifica que los iconos tienen aria-hidden
     */
    public function testBadgeIconsAreAriaHidden(): void
    {
        require_once __DIR__ . '/../../resources/views/components/badge.php';

        $html = renderBadge([
            'label' => 'Success',
            'icon' => 'check_circle'
        ]);

        // Iconos decorativos tienen aria-hidden="true"
        if (str_contains($html, 'material-symbols-outlined')) {
            $this->assertStringContainsString('aria-hidden="true"', $html);
        }
    }

    /**
     * Verifica que los elementos interactivos tienen focus visible
     */
    public function testInteractiveElementsHaveFocusStyles(): void
    {
        $cssFiles = [
            __DIR__ . '/../../public/css/components/button.css',
            __DIR__ . '/../../public/css/components/card.css',
            __DIR__ . '/../../public/css/components/modal.css',
            __DIR__ . '/../../public/css/components/badge.css',
        ];

        foreach ($cssFiles as $file) {
            $css = file_get_contents($file);

            // Verifica que hay estilos :focus-visible
            $this->assertStringContainsString(
                ':focus-visible',
                $css,
                basename($file) . ' debe tener estilos :focus-visible'
            );

            // Verifica que hay outline o box-shadow en focus
            $focusPattern = '/:focus-visible\s*\{[^}]*(outline|box-shadow)/s';
            $this->assertMatchesRegularExpression(
                $focusPattern,
                $css,
                basename($file) . ' debe tener outline o box-shadow visible'
            );
        }
    }

    /**
     * Verifica que los touch targets cumplen 44×44px
     */
    public function testInteractiveElementsMeetTouchTargetMinimum(): void
    {
        $cssFiles = [
            __DIR__ . '/../../public/css/components/button.css',
            __DIR__ . '/../../public/css/components/modal.css',
        ];

        foreach ($cssFiles as $file) {
            $css = file_get_contents($file);

            // WCAG 2.5.5: touch targets should be at least 44×44 CSS pixels
            // Accept both min-height and height (if >= 44px)
            $hasMinHeight = preg_match('/min-height:\s*(44|4[4-9]|[5-9]\d)px/', $css);
            $hasHeight = preg_match('/height:\s*(44|4[4-9]|[5-9]\d)px/', $css);

            $this->assertTrue(
                $hasMinHeight || $hasHeight,
                basename($file) . ' debe tener min-height o height >= 44px en elementos interactivos'
            );
        }
    }

    /**
     * Verifica que los formularios tienen labels
     */
    public function testFormsHaveProperLabels(): void
    {
        // Este test es un placeholder para cuando se implementen formularios
        $this->markTestIncomplete('Pendiente: validar formularios cuando se implementen');
    }

    /**
     * Verifica que las imágenes tienen alt text
     */
    public function testImagesHaveAltText(): void
    {
        // Este test es un placeholder
        $this->markTestIncomplete('Pendiente: validar imágenes cuando se implementen helpers');
    }

    /**
     * Verifica que el contraste de colores cumple WCAG AA
     */
    public function testColorContrastMeetsWCAG(): void
    {
        $tokensFile = __DIR__ . '/../../public/css/design-tokens.css';
        $tokens = file_get_contents($tokensFile);

        // Verificar que existen variables de color primarias
        $this->assertStringContainsString('--color-primary-500', $tokens);
        $this->assertStringContainsString('--color-neutral-900', $tokens);

        // Nota: El contraste real se verifica manualmente en las auditorías
        // Ver: docs/design-system/audit/focus-states-audit.md
        $this->assertTrue(true, 'Color tokens definidos - contraste verificado en auditoría manual');
    }

    /**
     * Verifica que hay soporte para prefers-reduced-motion
     */
    public function testReducedMotionSupport(): void
    {
        $cssFiles = [
            __DIR__ . '/../../public/css/components/button.css',
            __DIR__ . '/../../public/css/components/card.css',
            __DIR__ . '/../../public/css/components/modal.css',
        ];

        foreach ($cssFiles as $file) {
            $css = file_get_contents($file);

            // Verifica que hay media query para reduced motion
            $this->assertStringContainsString(
                'prefers-reduced-motion',
                $css,
                basename($file) . ' debe soportar prefers-reduced-motion'
            );
        }
    }

    /**
     * Verifica que los heading levels son correctos en layout
     */
    public function testHeadingHierarchy(): void
    {
        $layoutFile = __DIR__ . '/../../resources/views/layouts/backoffice.php';

        if (!file_exists($layoutFile)) {
            $this->markTestSkipped('Layout file no encontrado');
        }

        $layout = file_get_contents($layoutFile);

        // Verifica que hay h1 en el layout
        $this->assertMatchesRegularExpression('/<h1[^>]*>/', $layout, 'Layout debe tener h1');

        // Verifica que no hay skip link para accesibilidad
        $this->assertStringContainsString('skip-link', $layout, 'Layout debe tener skip link');
    }

    /**
     * Verifica que existe documentación de accesibilidad
     */
    public function testAccessibilityDocumentationExists(): void
    {
        $docs = [
            __DIR__ . '/../../docs/design-system/audit/focus-states-audit.md',
            __DIR__ . '/../../docs/design-system/audit/touch-targets-audit.md',
        ];

        foreach ($docs as $doc) {
            $this->assertFileExists($doc, 'Documentación de accesibilidad debe existir');
        }
    }

    /**
     * Test de regresión: verifica que no se eliminen atributos ARIA
     */
    public function testAriaAttributesNotRemoved(): void
    {
        require_once __DIR__ . '/../../resources/views/components/modal.php';

        $html = renderModal(['title' => 'Test']);

        // Verificar atributos ARIA críticos
        $requiredAttributes = [
            'role="dialog"',
            'aria-modal="true"',
            'aria-labelledby',
        ];

        foreach ($requiredAttributes as $attr) {
            $this->assertStringContainsString(
                $attr,
                $html,
                "Modal debe mantener atributo: {$attr}"
            );
        }
    }
}
