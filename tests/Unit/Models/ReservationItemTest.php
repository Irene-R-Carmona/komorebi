<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\ReservationItem;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * ¿Qué pruebas aquí? Constantes y métodos CRUD del modelo ReservationItem.
 * ¿Qué me quieres demostrar? Que add retorna int, que los métodos de consulta delegan en PDO y que markReady/markServed delegan en updateStatus.
 * ¿Qué va a fallar en este test si se cambia el código? Cambios en los valores de constantes, en el retorno de add o en la lógica de updateStatus.
 */
#[CoversClass(ReservationItem::class)]
final class ReservationItemTest extends TestCase
{
    private PDO $pdo;
    private PDOStatement $stmt;
    private ReservationItem $model;

    protected function setUp(): void
    {
        $this->pdo   = $this->createStub(PDO::class);
        $this->stmt  = $this->createStub(PDOStatement::class);
        $this->pdo->method('prepare')->willReturn($this->stmt);
        $this->model = new ReservationItem($this->pdo);
    }

    // ── Constants ────────────────────────────────────────────────

    public function testStatusConstants(): void
    {
        $this->assertSame('pending', ReservationItem::STATUS_PENDING);
        $this->assertSame('kitchen', ReservationItem::STATUS_KITCHEN);
        $this->assertSame('ready', ReservationItem::STATUS_READY);
        $this->assertSame('served', ReservationItem::STATUS_SERVED);
    }

    // ── add ──────────────────────────────────────────────────────

    public function testAddReturnsInsertedId(): void
    {
        $this->stmt->method('execute')->willReturn(true);
        $this->pdo->method('lastInsertId')->willReturn('15');

        $result = $this->model->add(1, 3, 2, 4.50);

        $this->assertSame(15, $result);
    }

    // ── findByReservation ─────────────────────────────────────────

    public function testFindByReservationReturnsArray(): void
    {
        $rows = [
            ['id' => 1, 'reservation_id' => 5, 'product_id' => 3, 'quantity' => 2, 'status' => 'pending'],
        ];
        $this->stmt->method('fetchAll')->willReturn($rows);

        $result = $this->model->findByReservation(5);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertSame('pending', $result[0]['status']);
    }

    public function testFindByReservationReturnsEmptyArrayWhenNone(): void
    {
        $this->stmt->method('fetchAll')->willReturn([]);

        $result = $this->model->findByReservation(999);

        $this->assertSame([], $result);
    }

    // ── findPendingByStation ──────────────────────────────────────

    public function testFindPendingByStationReturnsArray(): void
    {
        $rows = [
            ['id' => 2, 'product_name' => 'Matcha', 'quantity' => 1, 'station' => 'bar', 'status' => 'pending'],
        ];
        $this->stmt->method('fetchAll')->willReturn($rows);

        $result = $this->model->findPendingByStation(1, 'bar');

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
    }

    // ── updateStatus ─────────────────────────────────────────────

    public function testUpdateStatusReturnsTrue(): void
    {
        $this->stmt->method('execute')->willReturn(true);

        $result = $this->model->updateStatus(1, ReservationItem::STATUS_KITCHEN);

        $this->assertTrue($result);
    }

    // ── markReady ────────────────────────────────────────────────

    public function testMarkReadyReturnsTrue(): void
    {
        $this->stmt->method('execute')->willReturn(true);

        $result = $this->model->markReady(1);

        $this->assertTrue($result);
    }

    // ── markServed ───────────────────────────────────────────────

    public function testMarkServedReturnsTrue(): void
    {
        $this->stmt->method('execute')->willReturn(true);

        $result = $this->model->markServed(1);

        $this->assertTrue($result);
    }
}
