<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Setting;
use Exception;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

/**
 * Tests del modelo Setting.
 *
 * Nota: Los métodos estáticos (get, set, getGroup, getPublic, loadCache) no son testeables
 * unitariamente porque crean instancias internas con `new self()` usando $db = null,
 * lo que requiere una conexión real a la base de datos (fuera del alcance de unit tests).
 * Están cubiertos en tests de integración.
 */
final class SettingTest extends TestCase
{
    private function stubPdoWithPrepare(PDOStatement $stmt): PDO
    {
        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        return $pdo;
    }

    // ── findAll ───────────────────────────────────────────────────

    public function testFindAllReturnsArray(): void
    {
        $rows = [
            ['key' => 'site_name', 'value' => 'Komorebi', 'type' => 'string', 'group_name' => 'general'],
        ];
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('fetchAll')->willReturn($rows);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('query')->willReturn($stmt);

        $result = new Setting($pdo)->findAll();
        $this->assertSame($rows, $result);
    }

    // ── findByGroup ───────────────────────────────────────────────

    public function testFindByGroupReturnsMatchingSettings(): void
    {
        $rows = [
            ['key' => 'smtp_host', 'value' => 'mail.example.com', 'type' => 'string', 'group_name' => 'email'],
        ];
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('fetchAll')->willReturn($rows);

        $result = new Setting($this->stubPdoWithPrepare($stmt))->findByGroup('email');
        $this->assertSame($rows, $result);
    }

    public function testFindByGroupReturnsEmptyWhenNoneFound(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('fetchAll')->willReturn([]);

        $result = new Setting($this->stubPdoWithPrepare($stmt))->findByGroup('nonexistent');
        $this->assertSame([], $result);
    }

    // ── updateBatch ───────────────────────────────────────────────

    public function testUpdateBatchReturnsTrueWhenSettingsUpdated(): void
    {
        $stmtUpdate = $this->createStub(PDOStatement::class);
        $stmtUpdate->method('execute')->willReturn(true);

        $stmtType = $this->createStub(PDOStatement::class);
        $stmtType->method('fetchColumn')->willReturn('string');

        $pdo = $this->createStub(PDO::class);
        $pdo->method('beginTransaction')->willReturn(true);
        $pdo->method('commit')->willReturn(true);
        $pdo->method('prepare')->willReturnOnConsecutiveCalls($stmtUpdate, $stmtType);

        $result = new Setting($pdo)->updateBatch(['site_name' => 'Komorebi Café']);
        $this->assertTrue($result);
    }

    public function testUpdateBatchSkipsKeyWhenTypeNotFound(): void
    {
        $stmtUpdate = $this->createStub(PDOStatement::class);
        $stmtUpdate->method('execute')->willReturn(true);

        $stmtType = $this->createStub(PDOStatement::class);
        $stmtType->method('fetchColumn')->willReturn(false); // key does not exist in DB

        $pdo = $this->createStub(PDO::class);
        $pdo->method('beginTransaction')->willReturn(true);
        $pdo->method('commit')->willReturn(true);
        $pdo->method('prepare')->willReturnOnConsecutiveCalls($stmtUpdate, $stmtType);

        $result = new Setting($pdo)->updateBatch(['ghost_key' => 'value']);
        $this->assertTrue($result);
    }

    public function testUpdateBatchReturnsFalseOnException(): void
    {
        $pdo = $this->createStub(PDO::class);
        $pdo->method('beginTransaction')->willReturn(true);
        $pdo->method('prepare')->willThrowException(new Exception('DB error'));
        $pdo->method('rollBack')->willReturn(true);

        $result = new Setting($pdo)->updateBatch(['site_name' => 'Test']);
        $this->assertFalse($result);
    }

    // ── delete ────────────────────────────────────────────────────

    public function testDeleteReturnsTrueOnSuccess(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);

        $result = new Setting($this->stubPdoWithPrepare($stmt))->delete('site_name');
        $this->assertTrue($result);
    }

    public function testDeleteReturnsFalseWhenKeyNotFound(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(false);

        $result = new Setting($this->stubPdoWithPrepare($stmt))->delete('nonexistent_key');
        $this->assertFalse($result);
    }
}
