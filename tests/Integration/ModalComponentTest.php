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
 * Modal Component Test Suite
 *
 * Tests para el componente de modal/diálogo reutilizable.
 * Verifica renderizado, variantes, tamaños y accesibilidad.
 *
 * Run with:
 * docker compose exec app php vendor/bin/phpunit tests/Integration/ModalComponentTest.php --testdox
 */
#[CoversNothing]
final class ModalComponentTest extends TestCase
{
    private string $componentPath;

    protected function setUp(): void
    {
        $this->componentPath = __DIR__ . '/../../resources/views/components/modal.php';

        if (!\file_exists($this->componentPath)) {
            $this->markTestSkipped('Modal component not yet created');
        }
    }

    public function testModalComponentExists(): void
    {
        $this->assertFileExists($this->componentPath);
    }

    public function testRenderModalFunctionExists(): void
    {
        require_once $this->componentPath;
        $this->assertTrue(\function_exists('renderModal'));
    }

    public function testRenderBasicModal(): void
    {
        require_once $this->componentPath;

        $html = \renderModal([
            'id' => 'test-modal',
            'body' => 'Test content',
        ]);

        $this->assertStringContainsString('modal-backdrop', $html);
        $this->assertStringContainsString('modal', $html);
        $this->assertStringContainsString('modal__dialog', $html);
        $this->assertStringContainsString('modal__body', $html);
        $this->assertStringContainsString('Test content', $html);
        $this->assertStringContainsString('id="test-modal"', $html);
    }

    public function testModalSizes(): void
    {
        require_once $this->componentPath;

        $sizes = ['sm', 'md', 'lg', 'xl', 'full'];

        foreach ($sizes as $size) {
            $html = \renderModal([
                'id' => "modal-{$size}",
                'body' => 'Content',
                'size' => $size,
            ]);

            $this->assertStringContainsString("modal--{$size}", $html);
        }
    }

    public function testModalDefaultSize(): void
    {
        require_once $this->componentPath;

        $html = \renderModal([
            'id' => 'modal-default',
            'body' => 'Content',
        ]);

        $this->assertStringContainsString('modal--md', $html);
    }

    public function testModalWithTitle(): void
    {
        require_once $this->componentPath;

        $html = \renderModal([
            'id' => 'titled-modal',
            'title' => 'Test Title',
            'body' => 'Content',
        ]);

        $this->assertStringContainsString('modal__title', $html);
        $this->assertStringContainsString('Test Title', $html);
        $this->assertStringContainsString('titled-modal-title', $html);
        $this->assertStringContainsString('aria-labelledby="titled-modal-title"', $html);
    }

    public function testModalWithHeader(): void
    {
        require_once $this->componentPath;

        $html = \renderModal([
            'id' => 'header-modal',
            'header' => '<div class="custom-header">Custom Header</div>',
            'body' => 'Content',
        ]);

        $this->assertStringContainsString('modal__header', $html);
        $this->assertStringContainsString('custom-header', $html);
        $this->assertStringContainsString('Custom Header', $html);
    }

    public function testModalWithFooter(): void
    {
        require_once $this->componentPath;

        $html = \renderModal([
            'id' => 'footer-modal',
            'body' => 'Content',
            'footer' => '<button>Save</button><button>Cancel</button>',
        ]);

        $this->assertStringContainsString('modal__footer', $html);
        $this->assertStringContainsString('<button>Save</button>', $html);
        $this->assertStringContainsString('<button>Cancel</button>', $html);
    }

    public function testModalCloseable(): void
    {
        require_once $this->componentPath;

        $html = \renderModal([
            'id' => 'closeable-modal',
            'body' => 'Content',
            'title' => 'Title',
            'closeable' => true,
        ]);

        $this->assertStringContainsString('modal__close', $html);
        $this->assertStringContainsString('@click="open = false"', $html);
        $this->assertStringContainsString('aria-label="Close modal"', $html);
        $this->assertStringContainsString('material-symbols-outlined', $html);
        $this->assertStringContainsString('close', $html);
    }

    public function testModalNotCloseable(): void
    {
        require_once $this->componentPath;

        $html = \renderModal([
            'id' => 'not-closeable-modal',
            'body' => 'Content',
            'closeable' => false,
        ]);

        $this->assertStringNotContainsString('modal__close', $html);
    }

    public function testModalAriaAttributes(): void
    {
        require_once $this->componentPath;

        $html = \renderModal([
            'id' => 'aria-modal',
            'title' => 'Aria Test',
            'body' => 'Content',
        ]);

        $this->assertStringContainsString('role="dialog"', $html);
        $this->assertStringContainsString('aria-modal="true"', $html);
        $this->assertStringContainsString('aria-labelledby="aria-modal-title"', $html);
    }

    public function testModalEscapesHtmlInTitle(): void
    {
        require_once $this->componentPath;

        $html = \renderModal([
            'id' => 'escape-modal',
            'title' => '<script>alert("XSS")</script>',
            'body' => 'Content',
        ]);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testModalAllowsHtmlInBody(): void
    {
        require_once $this->componentPath;

        $html = \renderModal([
            'id' => 'html-modal',
            'body' => '<p class="text-bold">Formatted Content</p>',
        ]);

        // Body content should not be escaped (allows formatting)
        $this->assertStringContainsString('<p class="text-bold">Formatted Content</p>', $html);
    }

    public function testModalAllowsHtmlInFooter(): void
    {
        require_once $this->componentPath;

        $html = \renderModal([
            'id' => 'footer-html-modal',
            'body' => 'Content',
            'footer' => '<button class="btn btn-primary">Submit</button>',
        ]);

        // Footer content should not be escaped (allows buttons)
        $this->assertStringContainsString('<button class="btn btn-primary">Submit</button>', $html);
    }

    public function testModalWithAdditionalClass(): void
    {
        require_once $this->componentPath;

        $html = \renderModal([
            'id' => 'custom-modal',
            'body' => 'Content',
            'class' => 'modal--danger',
        ]);

        $this->assertStringContainsString('modal--danger', $html);
    }

    public function testModalWithCustomAttributes(): void
    {
        require_once $this->componentPath;

        $html = \renderModal([
            'id' => 'attr-modal',
            'body' => 'Content',
            'attributes' => [
                'x-data' => '{ open: false }',
                'x-show' => 'open',
                'data-test' => 'modal',
            ],
        ]);

        $this->assertStringContainsString('x-data="{ open: false }"', $html);
        $this->assertStringContainsString('x-show="open"', $html);
        $this->assertStringContainsString('data-test="modal"', $html);
    }

    public function testModalEscapesHtmlInAttributes(): void
    {
        require_once $this->componentPath;

        $html = \renderModal([
            'id' => 'escape-attr-modal',
            'body' => 'Content',
            'attributes' => [
                'data-value' => '<script>evil()</script>',
            ],
        ]);

        $this->assertStringNotContainsString('<script>evil()</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testModalBackdrop(): void
    {
        require_once $this->componentPath;

        $html = \renderModal([
            'id' => 'backdrop-modal',
            'body' => 'Content',
        ]);

        $this->assertStringContainsString('modal-backdrop', $html);
        $this->assertStringContainsString('@click="open = false"', $html);
        $this->assertStringContainsString('x-transition', $html);
    }

    public function testModalAlpineTransitions(): void
    {
        require_once $this->componentPath;

        $html = \renderModal([
            'id' => 'transition-modal',
            'body' => 'Content',
        ]);

        $this->assertStringContainsString('x-show="open"', $html);
        $this->assertStringContainsString('x-transition', $html);
        $this->assertStringContainsString('x-trap="open"', $html);
    }

    public function testModalGeneratesUniqueId(): void
    {
        require_once $this->componentPath;

        // Without explicit ID
        $html = \renderModal(['body' => 'Content']);

        $this->assertMatchesRegularExpression('/id="modal-[a-z0-9]+"/', $html);
    }

    public function testModalWithCompleteProps(): void
    {
        require_once $this->componentPath;

        $html = \renderModal([
            'id' => 'complete-modal',
            'title' => 'Complete Modal',
            'size' => 'lg',
            'closeable' => true,
            'body' => '<p>This is a complete modal with all props.</p>',
            'footer' => '<button>Action</button>',
            'class' => 'custom-modal-class',
            'attributes' => [
                'x-data' => '{ open: false }',
                'data-test' => 'complete',
            ],
        ]);

        $this->assertStringContainsString('id="complete-modal"', $html);
        $this->assertStringContainsString('Complete Modal', $html);
        $this->assertStringContainsString('modal--lg', $html);
        $this->assertStringContainsString('modal__close', $html);
        $this->assertStringContainsString('This is a complete modal with all props.', $html);
        $this->assertStringContainsString('<button>Action</button>', $html);
        $this->assertStringContainsString('custom-modal-class', $html);
        $this->assertStringContainsString('x-data="{ open: false }"', $html);
        $this->assertStringContainsString('data-test="complete"', $html);
    }

    public function testModalEscKeyHandler(): void
    {
        require_once $this->componentPath;

        $html = \renderModal([
            'id' => 'esc-modal',
            'body' => 'Content',
            'closeable' => true,
        ]);

        $this->assertStringContainsString('@keydown.escape.window', $html);
        $this->assertStringContainsString('open = false', $html);
    }

    public function testModalNoEscKeyWhenNotCloseable(): void
    {
        require_once $this->componentPath;

        $html = \renderModal([
            'id' => 'no-esc-modal',
            'body' => 'Content',
            'closeable' => false,
        ]);

        // Should still have the keydown handler but empty action
        $this->assertStringContainsString('@keydown.escape.window=""', $html);
    }

    public function testModalDefaultBodyContent(): void
    {
        require_once $this->componentPath;

        $html = \renderModal(['id' => 'default-modal']);

        // Should have default body content
        $this->assertStringContainsString('Modal content', $html);
    }
}
