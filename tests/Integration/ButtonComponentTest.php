<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * ¿Qué me quieres demostrar?
 * ¿Qué va a fallar en este test si se cambia el código?
 */

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Button Component Test Suite
 *
 * Tests para el componente de botón reutilizable.
 * Verifica renderizado, props, variantes, tamaños y accesibilidad.
 *
 * Run with:
 * docker compose exec app php vendor/bin/phpunit tests/Integration/ButtonComponentTest.php --testdox
 */
final class ButtonComponentTest extends TestCase
{
    private string $componentPath;

    protected function setUp(): void
    {
        $this->componentPath = __DIR__ . '/../../resources/views/components/button.php';

        if (!\file_exists($this->componentPath)) {
            $this->markTestSkipped('Button component not yet created');
        }
    }

    public function testButtonComponentExists(): void
    {
        $this->assertFileExists($this->componentPath);
    }

    public function testRenderButtonFunctionExists(): void
    {
        require_once $this->componentPath;
        $this->assertTrue(\function_exists('renderButton'));
    }

    public function testRenderBasicButton(): void
    {
        require_once $this->componentPath;

        $html = \renderButton(['label' => 'Test Button']);

        $this->assertStringContainsString('btn', $html);
        $this->assertStringContainsString('btn--primary', $html);
        $this->assertStringContainsString('btn--md', $html);
        $this->assertStringContainsString('Test Button', $html);
        $this->assertStringContainsString('type="button"', $html);
    }

    public function testRenderButtonWithIcon(): void
    {
        require_once $this->componentPath;

        $html = \renderButton([
            'label' => 'Save',
            'icon' => 'save',
        ]);

        $this->assertStringContainsString('material-symbols-outlined', $html);
        $this->assertStringContainsString('save', $html);
        $this->assertStringContainsString('aria-hidden="true"', $html);
    }

    public function testRenderButtonDisabled(): void
    {
        require_once $this->componentPath;

        $html = \renderButton([
            'label' => 'Disabled',
            'disabled' => true,
        ]);

        $this->assertStringContainsString('disabled', $html);
        $this->assertStringContainsString('btn--disabled', $html);
    }

    public function testRenderButtonLoading(): void
    {
        require_once $this->componentPath;

        $html = \renderButton([
            'label' => 'Loading',
            'loading' => true,
        ]);

        $this->assertStringContainsString('btn--loading', $html);
        $this->assertStringContainsString('btn__spinner', $html);
        $this->assertStringContainsString('Cargando...', $html);
        $this->assertStringContainsString('sr-only', $html);
    }

    public function testButtonVariants(): void
    {
        require_once $this->componentPath;

        $variants = ['primary', 'secondary', 'danger', 'ghost'];

        foreach ($variants as $variant) {
            $html = \renderButton([
                'label' => \ucfirst($variant),
                'variant' => $variant,
            ]);

            $this->assertStringContainsString("btn--{$variant}", $html);
        }
    }

    public function testButtonSizes(): void
    {
        require_once $this->componentPath;

        $sizes = ['sm', 'md', 'lg'];

        foreach ($sizes as $size) {
            $html = \renderButton([
                'label' => \ucfirst($size),
                'size' => $size,
            ]);

            $this->assertStringContainsString("btn--{$size}", $html);
        }
    }

    public function testButtonTypes(): void
    {
        require_once $this->componentPath;

        $types = ['button', 'submit', 'reset'];

        foreach ($types as $type) {
            $html = \renderButton([
                'label' => \ucfirst($type),
                'type' => $type,
            ]);

            $this->assertStringContainsString("type=\"{$type}\"", $html);
        }
    }

    public function testButtonEscapesHtmlInLabel(): void
    {
        require_once $this->componentPath;

        $html = \renderButton(['label' => '<script>alert("XSS")</script>']);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testButtonEscapesHtmlInIcon(): void
    {
        require_once $this->componentPath;

        $html = \renderButton([
            'label' => 'Test',
            'icon' => '<img src=x onerror=alert(1)>',
        ]);

        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringContainsString('&lt;img', $html);
    }

    public function testButtonWithAdditionalClass(): void
    {
        require_once $this->componentPath;

        $html = \renderButton([
            'label' => 'Test',
            'class' => 'custom-class',
        ]);

        $this->assertStringContainsString('custom-class', $html);
    }

    public function testButtonWithAttributes(): void
    {
        require_once $this->componentPath;

        $html = \renderButton([
            'label' => 'Test',
            'attributes' => [
                'id' => 'test-button',
                'data-action' => 'submit',
            ],
        ]);

        $this->assertStringContainsString('id="test-button"', $html);
        $this->assertStringContainsString('data-action="submit"', $html);
    }

    public function testButtonLoadingHidesIcon(): void
    {
        require_once $this->componentPath;

        $html = \renderButton([
            'label' => 'Test',
            'icon' => 'save',
            'loading' => true,
        ]);

        // Icon should not be rendered when loading
        $this->assertStringNotContainsString('btn__icon', $html);
        // Spinner should be present
        $this->assertStringContainsString('btn__spinner', $html);
    }

    public function testButtonLoadingIsDisabled(): void
    {
        require_once $this->componentPath;

        $html = \renderButton([
            'label' => 'Test',
            'loading' => true,
        ]);

        // Loading state should add disabled class
        $this->assertStringContainsString('btn--disabled', $html);
        $this->assertStringContainsString('disabled', $html);
    }

    public function testButtonDefaultsCorrectly(): void
    {
        require_once $this->componentPath;

        $html = \renderButton(['label' => 'Test']);

        // Should have default type
        $this->assertStringContainsString('type="button"', $html);
        // Should have default variant
        $this->assertStringContainsString('btn--primary', $html);
        // Should have default size
        $this->assertStringContainsString('btn--md', $html);
        // Should not be disabled
        $this->assertStringNotContainsString('btn--disabled', $html);
        // Should not be loading
        $this->assertStringNotContainsString('btn--loading', $html);
    }

    public function testButtonAccessibilityAttributes(): void
    {
        require_once $this->componentPath;

        $html = \renderButton([
            'label' => 'Test',
            'icon' => 'save',
            'loading' => true,
        ]);

        // Icon should have aria-hidden when present
        // Loading spinner should have aria-hidden
        $this->assertStringContainsString('aria-hidden="true"', $html);
        // Loading state should have sr-only text for screen readers
        $this->assertStringContainsString('sr-only', $html);
        $this->assertStringContainsString('Cargando...', $html);
    }

    public function testButtonLabelIsRequired(): void
    {
        require_once $this->componentPath;

        // Even with empty props, should have default label
        $html = \renderButton([]);
        $this->assertStringContainsString('Button', $html); // Default label
    }

    public function testButtonWithAllProps(): void
    {
        require_once $this->componentPath;

        $html = \renderButton([
            'type' => 'submit',
            'label' => 'Submit Form',
            'variant' => 'primary',
            'size' => 'lg',
            'icon' => 'send',
            'disabled' => false,
            'loading' => false,
            'class' => 'w-full mt-4',
            'attributes' => [
                'id' => 'submit-btn',
                'data-testid' => 'form-submit',
            ],
        ]);

        $this->assertStringContainsString('type="submit"', $html);
        $this->assertStringContainsString('Submit Form', $html);
        $this->assertStringContainsString('btn--primary', $html);
        $this->assertStringContainsString('btn--lg', $html);
        $this->assertStringContainsString('send', $html);
        $this->assertStringContainsString('w-full mt-4', $html);
        $this->assertStringContainsString('id="submit-btn"', $html);
        $this->assertStringContainsString('data-testid="form-submit"', $html);
    }
}
