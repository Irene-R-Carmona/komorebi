<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * ¿Qué me quieres demostrar?
 * ¿Qué va a fallar en este test si se cambia el código?
 */

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Card Component Test Suite
 *
 * Tests para el componente de tarjeta reutilizable.
 * Verifica renderizado, variantes, secciones y layouts.
 *
 * Run with:
 * docker compose exec app php vendor/bin/phpunit tests/Integration/CardComponentTest.php --testdox
 */
#[CoversNothing]
final class CardComponentTest extends TestCase
{
    private string $componentPath;

    protected function setUp(): void
    {
        $this->componentPath = __DIR__ . '/../../resources/views/components/card.php';

        if (!\file_exists($this->componentPath)) {
            $this->markTestSkipped('Card component not yet created');
        }
    }

    public function testCardComponentExists(): void
    {
        $this->assertFileExists($this->componentPath);
    }

    public function testRenderCardFunctionExists(): void
    {
        require_once $this->componentPath;
        $this->assertTrue(\function_exists('renderCard'));
    }

    public function testRenderBasicCard(): void
    {
        require_once $this->componentPath;

        $html = \renderCard(['body' => '<p>Test content</p>']);

        $this->assertStringContainsString('card', $html);
        $this->assertStringContainsString('card--default', $html);
        $this->assertStringContainsString('card--padding-md', $html);
        $this->assertStringContainsString('card__body', $html);
        $this->assertStringContainsString('Test content', $html);
    }

    public function testCardVariants(): void
    {
        require_once $this->componentPath;

        $variants = ['default', 'glass', 'outlined', 'elevated'];

        foreach ($variants as $variant) {
            $html = \renderCard([
                'variant' => $variant,
                'body' => 'Content',
            ]);

            $this->assertStringContainsString("card--{$variant}", $html);
        }
    }

    public function testCardPaddingSizes(): void
    {
        require_once $this->componentPath;

        $sizes = ['none', 'sm', 'md', 'lg'];

        foreach ($sizes as $size) {
            $html = \renderCard([
                'padding' => $size,
                'body' => 'Content',
            ]);

            $this->assertStringContainsString("card--padding-{$size}", $html);
        }
    }

    public function testCardWithHeader(): void
    {
        require_once $this->componentPath;

        $html = \renderCard([
            'header' => '<h3>Card Title</h3>',
            'body' => '<p>Body content</p>',
        ]);

        $this->assertStringContainsString('card__header', $html);
        $this->assertStringContainsString('Card Title', $html);
    }

    public function testCardWithFooter(): void
    {
        require_once $this->componentPath;

        $html = \renderCard([
            'body' => '<p>Body content</p>',
            'footer' => '<button>Action</button>',
        ]);

        $this->assertStringContainsString('card__footer', $html);
        $this->assertStringContainsString('Action', $html);
    }

    public function testCardWithAllSections(): void
    {
        require_once $this->componentPath;

        $html = \renderCard([
            'header' => '<h3>Title</h3>',
            'body' => '<p>Content</p>',
            'footer' => '<button>Action</button>',
        ]);

        $this->assertStringContainsString('card__header', $html);
        $this->assertStringContainsString('card__body', $html);
        $this->assertStringContainsString('card__footer', $html);
        $this->assertStringContainsString('Title', $html);
        $this->assertStringContainsString('Content', $html);
        $this->assertStringContainsString('Action', $html);
    }

    public function testCardInteractive(): void
    {
        require_once $this->componentPath;

        $html = \renderCard([
            'body' => 'Content',
            'interactive' => true,
        ]);

        $this->assertStringContainsString('card--interactive', $html);
    }

    public function testCardWithAdditionalClass(): void
    {
        require_once $this->componentPath;

        $html = \renderCard([
            'body' => 'Content',
            'class' => 'custom-card',
        ]);

        $this->assertStringContainsString('custom-card', $html);
    }

    public function testCardWithAttributes(): void
    {
        require_once $this->componentPath;

        $html = \renderCard([
            'body' => 'Content',
            'attributes' => [
                'id' => 'card-1',
                'data-category' => 'test',
            ],
        ]);

        $this->assertStringContainsString('id="card-1"', $html);
        $this->assertStringContainsString('data-category="test"', $html);
    }

    public function testCardEscapesHtmlInAttributes(): void
    {
        require_once $this->componentPath;

        $html = \renderCard([
            'body' => 'Content',
            'attributes' => [
                'onclick' => '<script>alert("XSS")</script>',
            ],
        ]);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testCardBodyHtmlNotEscaped(): void
    {
        require_once $this->componentPath;

        $html = \renderCard([
            'body' => '<strong>Bold text</strong>',
        ]);

        // Body content should render HTML as-is (for flexibility)
        $this->assertStringContainsString('<strong>Bold text</strong>', $html);
    }

    public function testCardHeaderHtmlNotEscaped(): void
    {
        require_once $this->componentPath;

        $html = \renderCard([
            'header' => '<h3 class="title">Header</h3>',
            'body' => 'Content',
        ]);

        // Header content should render HTML as-is
        $this->assertStringContainsString('<h3 class="title">Header</h3>', $html);
    }

    public function testCardFooterHtmlNotEscaped(): void
    {
        require_once $this->componentPath;

        $html = \renderCard([
            'body' => 'Content',
            'footer' => '<button class="btn">Action</button>',
        ]);

        // Footer content should render HTML as-is
        $this->assertStringContainsString('<button class="btn">Action</button>', $html);
    }

    public function testCardDefaultsCorrectly(): void
    {
        require_once $this->componentPath;

        $html = \renderCard(['body' => 'Content']);

        // Should have default variant
        $this->assertStringContainsString('card--default', $html);
        // Should have default padding
        $this->assertStringContainsString('card--padding-md', $html);
        // Should not be interactive
        $this->assertStringNotContainsString('card--interactive', $html);
    }

    public function testCardWithoutHeaderNoHeaderSection(): void
    {
        require_once $this->componentPath;

        $html = \renderCard(['body' => 'Content']);

        $this->assertStringNotContainsString('card__header', $html);
    }

    public function testCardWithoutFooterNoFooterSection(): void
    {
        require_once $this->componentPath;

        $html = \renderCard(['body' => 'Content']);

        $this->assertStringNotContainsString('card__footer', $html);
    }

    public function testCardEmptyBodyRendersEmpty(): void
    {
        require_once $this->componentPath;

        $html = \renderCard(['body' => '']);

        $this->assertStringContainsString('card__body', $html);
        // Body section should exist but be empty
        $this->assertMatchesRegularExpression('/<div class="card__body">\s*<\/div>/', $html);
    }
}
