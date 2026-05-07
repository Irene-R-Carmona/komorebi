<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Tracker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * ¿Qué pruebas aquí? Constantes de dominio del modelo Tracker.
 * ¿Qué me quieres demostrar? Que los valores de estado y tipo no cambian silenciosamente.
 * ¿Qué va a fallar en este test si se cambia el código? Cualquier renombre o eliminación de constante.
 */
#[CoversClass(Tracker::class)]
final class TrackerTest extends TestCase
{
    public function testStatusConstants(): void
    {
        $this->assertSame('available', Tracker::STATUS_AVAILABLE);
        $this->assertSame('in_use', Tracker::STATUS_IN_USE);
        $this->assertSame('lost', Tracker::STATUS_LOST);
    }

    public function testTypeConstants(): void
    {
        $this->assertSame('token', Tracker::TYPE_TOKEN);
        $this->assertSame('beeper', Tracker::TYPE_BEEPER);
        $this->assertSame('nfc', Tracker::TYPE_NFC);
        $this->assertSame('qr', Tracker::TYPE_QR);
    }

    public function testValidStatusesContainsAllThree(): void
    {
        $this->assertCount(3, Tracker::VALID_STATUSES);
        $this->assertContains(Tracker::STATUS_AVAILABLE, Tracker::VALID_STATUSES);
        $this->assertContains(Tracker::STATUS_IN_USE, Tracker::VALID_STATUSES);
        $this->assertContains(Tracker::STATUS_LOST, Tracker::VALID_STATUSES);
    }

    public function testValidTypesContainsAllFour(): void
    {
        $this->assertCount(4, Tracker::VALID_TYPES);
        $this->assertContains(Tracker::TYPE_TOKEN, Tracker::VALID_TYPES);
        $this->assertContains(Tracker::TYPE_BEEPER, Tracker::VALID_TYPES);
        $this->assertContains(Tracker::TYPE_NFC, Tracker::VALID_TYPES);
        $this->assertContains(Tracker::TYPE_QR, Tracker::VALID_TYPES);
    }

    public function testValidStatusesDoesNotContainUnknown(): void
    {
        $this->assertNotContains('broken', Tracker::VALID_STATUSES);
    }
}
