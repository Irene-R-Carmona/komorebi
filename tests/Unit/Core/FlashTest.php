<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * La clase Flash gestiona mensajes temporales de interfaz almacenados en
 * sesión ($_SESSION[Flash::KEY]).
 *
 * ¿Qué me quieres demostrar?
 * Que set() escribe correctamente en sesión, que los helpers semánticos
 * (success, error, info, warning) usan el tipo correcto, que all() limpia
 * los mensajes tras devolverlos, que consume() devuelve el primero, y que
 * get(type) filtra y elimina mensajes por tipo.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Cambios en Flash::KEY, en la estructura del array de mensaje
 * {type, message}, o en la lógica de borrado de all()/get().
 */

namespace Tests\Unit\Core;

use App\Core\Flash;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Flash::class)]
final class FlashTest extends TestCase
{
    protected function setUp(): void
    {
        // Arrancar sesión si no está activa y limpiar mensajes previos
        if (\session_status() !== PHP_SESSION_ACTIVE) {
            \session_start();
        }
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    // ──────────────────────────────────────────────────────────
    // Flash::set()
    // ──────────────────────────────────────────────────────────

    public function testSetAddsMessageToSession(): void
    {
        Flash::set('info', 'Hello');
        $all = Flash::all();
        self::assertCount(1, $all);
        self::assertSame('info', $all[0]['type']);
        self::assertSame('Hello', $all[0]['message']);
    }

    public function testSetAccumulatesMultipleMessages(): void
    {
        Flash::set('info', 'First');
        Flash::set('error', 'Second');
        self::assertCount(2, Flash::all());
    }

    // ──────────────────────────────────────────────────────────
    // Semantic helpers
    // ──────────────────────────────────────────────────────────

    public function testSuccessHelperSetsSuccessType(): void
    {
        Flash::success('Saved!');
        $messages = Flash::all();
        self::assertSame('success', $messages[0]['type']);
        self::assertSame('Saved!', $messages[0]['message']);
    }

    public function testErrorHelperSetsErrorType(): void
    {
        Flash::error('Oops');
        $messages = Flash::all();
        self::assertSame('error', $messages[0]['type']);
    }

    public function testInfoHelperSetsInfoType(): void
    {
        Flash::info('Note');
        $messages = Flash::all();
        self::assertSame('info', $messages[0]['type']);
    }

    public function testWarningHelperSetsWarningType(): void
    {
        Flash::warning('Careful');
        $messages = Flash::all();
        self::assertSame('warning', $messages[0]['type']);
    }

    // ──────────────────────────────────────────────────────────
    // Flash::all()
    // ──────────────────────────────────────────────────────────

    public function testAllReturnsAllMessages(): void
    {
        Flash::success('A');
        Flash::error('B');
        $messages = Flash::all();
        self::assertCount(2, $messages);
    }

    public function testAllClearsMessagesFromSession(): void
    {
        Flash::success('Gone');
        Flash::all();
        self::assertFalse(Flash::has());
    }

    public function testAllReturnsEmptyArrayWhenNoMessages(): void
    {
        self::assertSame([], Flash::all());
    }

    // ──────────────────────────────────────────────────────────
    // Flash::consume()
    // ──────────────────────────────────────────────────────────

    public function testConsumeReturnsFirstMessage(): void
    {
        Flash::success('First');
        Flash::error('Second');
        $msg = Flash::consume();
        self::assertIsArray($msg);
        self::assertSame('success', $msg['type']);
        self::assertSame('First', $msg['message']);
    }

    public function testConsumeReturnsNullWhenEmpty(): void
    {
        self::assertNull(Flash::consume());
    }

    // ──────────────────────────────────────────────────────────
    // Flash::get(type)
    // ──────────────────────────────────────────────────────────

    public function testGetReturnsMessageOfCorrectType(): void
    {
        Flash::info('Info message');
        Flash::error('Error message');
        $msg = Flash::get('info');
        self::assertSame('Info message', $msg);
    }

    public function testGetRemovesReturnedMessageFromSession(): void
    {
        Flash::info('Gone after get');
        Flash::get('info');
        // The info message should no longer be returned
        self::assertNull(Flash::get('info'));
    }

    public function testGetReturnsNullWhenTypeNotFound(): void
    {
        Flash::success('Only success');
        self::assertNull(Flash::get('error'));
    }

    public function testGetReturnsNullOnEmptySession(): void
    {
        self::assertNull(Flash::get('info'));
    }

    public function testGetDoesNotRemoveOtherTypes(): void
    {
        Flash::info('Info msg');
        Flash::error('Error msg');
        Flash::get('info');
        // Error message should still be there
        self::assertSame('Error msg', Flash::get('error'));
    }

    // ──────────────────────────────────────────────────────────
    // Flash::has()
    // ──────────────────────────────────────────────────────────

    public function testHasReturnsTrueWhenMessagesExist(): void
    {
        Flash::warning('Watch out');
        self::assertTrue(Flash::has());
    }

    public function testHasReturnsFalseWhenEmpty(): void
    {
        self::assertFalse(Flash::has());
    }

    public function testHasReturnsFalseAfterAllConsumed(): void
    {
        Flash::success('Temp');
        Flash::all();
        self::assertFalse(Flash::has());
    }
}
