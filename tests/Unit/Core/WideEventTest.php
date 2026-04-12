<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\WideEvent;
use PHPUnit\Framework\TestCase;

/**
 * ¿Qué pruebas aquí?
 * Comportamiento del acumulador estático WideEvent: set, setSection, has, all, reset.
 *
 * ¿Qué me quieres demostrar?
 * Que WideEvent acumula campos planos y secciones anidadas durante el ciclo de una request,
 * y que reset() limpia completamente el estado para el siguiente request.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si set/setSection dejan de acumular, si reset() no borra todo, o si has() falla.
 */
final class WideEventTest extends TestCase
{
    protected function setUp(): void
    {
        WideEvent::reset();
    }

    protected function tearDown(): void
    {
        WideEvent::reset();
    }

    public function testSetStoresTopLevelField(): void
    {
        WideEvent::set('request_id', 'abc123');

        $this->assertSame(['request_id' => 'abc123'], WideEvent::all());
    }

    public function testSetOverwritesExistingKey(): void
    {
        WideEvent::set('status', 200);
        WideEvent::set('status', 500);

        $this->assertSame(500, WideEvent::all()['status']);
    }

    public function testSetAccumulatesMultipleFields(): void
    {
        WideEvent::set('method', 'POST');
        WideEvent::set('path', '/api/reservas');
        WideEvent::set('status', 201);

        $all = WideEvent::all();
        $this->assertSame('POST', $all['method']);
        $this->assertSame('/api/reservas', $all['path']);
        $this->assertSame(201, $all['status']);
    }

    public function testSetSectionCreatesNestedArray(): void
    {
        WideEvent::setSection('user', ['id' => 42, 'role' => 'admin']);

        $this->assertSame(['id' => 42, 'role' => 'admin'], WideEvent::all()['user']);
    }

    public function testSetSectionMergesIntoExistingSection(): void
    {
        WideEvent::setSection('user', ['id' => 42]);
        WideEvent::setSection('user', ['role' => 'manager']);

        $user = WideEvent::all()['user'];
        $this->assertSame(42, $user['id']);
        $this->assertSame('manager', $user['role']);
    }

    public function testSetSectionNewValueOverridesExistingKey(): void
    {
        WideEvent::setSection('error', ['type' => 'ValidationException']);
        WideEvent::setSection('error', ['type' => 'DatabaseException', 'code' => 500]);

        $error = WideEvent::all()['error'];
        $this->assertSame('DatabaseException', $error['type']);
        $this->assertSame(500, $error['code']);
    }

    public function testSetSectionDoesNotAffectOtherSections(): void
    {
        WideEvent::setSection('user', ['id' => 10]);
        WideEvent::setSection('reservation', ['id' => 99, 'cafe_id' => 5]);

        $all = WideEvent::all();
        $this->assertSame(['id' => 10], $all['user']);
        $this->assertSame(['id' => 99, 'cafe_id' => 5], $all['reservation']);
    }

    public function testHasReturnsTrueForExistingKey(): void
    {
        WideEvent::set('status', 200);

        $this->assertTrue(WideEvent::has('status'));
    }

    public function testHasReturnsFalseForMissingKey(): void
    {
        $this->assertFalse(WideEvent::has('nonexistent'));
    }

    public function testHasReturnsTrueForSetSection(): void
    {
        WideEvent::setSection('user', ['id' => 1]);

        $this->assertTrue(WideEvent::has('user'));
    }

    public function testResetClearsAllData(): void
    {
        WideEvent::set('method', 'GET');
        WideEvent::setSection('user', ['id' => 5]);
        WideEvent::reset();

        $this->assertSame([], WideEvent::all());
    }

    public function testIsolationBetweenRequestCycles(): void
    {
        WideEvent::set('request_id', 'first');
        WideEvent::setSection('user', ['id' => 1]);
        WideEvent::reset();

        WideEvent::set('request_id', 'second');

        $all = WideEvent::all();
        $this->assertSame('second', $all['request_id']);
        $this->assertArrayNotHasKey('user', $all);
    }

    public function testAllReturnsEmptyArrayByDefault(): void
    {
        $this->assertSame([], WideEvent::all());
    }

    public function testMixedTopLevelAndSections(): void
    {
        WideEvent::set('method', 'POST');
        WideEvent::set('duration_ms', 142);
        WideEvent::setSection('user', ['id' => 7, 'role' => 'user']);
        WideEvent::setSection('reservation', ['cafe_id' => 3]);

        $all = WideEvent::all();
        $this->assertSame('POST', $all['method']);
        $this->assertSame(142, $all['duration_ms']);
        $this->assertSame(['id' => 7, 'role' => 'user'], $all['user']);
        $this->assertSame(['cafe_id' => 3], $all['reservation']);
    }
}
