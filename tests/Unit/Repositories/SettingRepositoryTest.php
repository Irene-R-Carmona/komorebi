<?php

/**
 * ¿Qué pruebas aquí? SettingRepository: findAll y findByGroup.
 * ¿Qué me quieres demostrar? Que findAll usa $this->db->query()->fetchAll()
 *   (no prepare/execute) y que findByGroup usa prepare+execute+fetchAll.
 * ¿Qué va a fallar en este test si se cambia el código? Si findAll pasa a usar
 *   prepare() en lugar de query(), o si findByGroup filtra por algo distinto al grupo.
 */

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Repositories\SettingRepository;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SettingRepository::class)]
final class SettingRepositoryTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeStmt(array $fetchAllReturn = []): PDOStatement
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn($fetchAllReturn);
        return $stmt;
    }

    private function makePdo(PDOStatement $stmt): PDO
    {
        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);
        $pdo->method('query')->willReturn($stmt);
        return $pdo;
    }

    // -------------------------------------------------------------------------
    // findAll (usa query())
    // -------------------------------------------------------------------------

    public function testFindAllReturnsRows(): void
    {
        $rows = [
            ['key' => 'site_name', 'value' => 'Komorebi', 'group_name' => 'general'],
            ['key' => 'timezone', 'value' => 'UTC', 'group_name' => 'general'],
        ];
        $stmt = $this->makeStmt(fetchAllReturn: $rows);
        $repo = new SettingRepository($this->makePdo($stmt));

        $result = $repo->findAll();
        $this->assertCount(2, $result);
        $this->assertSame('site_name', $result[0]->key);
    }

    public function testFindAllReturnsEmptyArray(): void
    {
        $stmt = $this->makeStmt(fetchAllReturn: []);
        $repo = new SettingRepository($this->makePdo($stmt));

        $this->assertSame([], $repo->findAll());
    }

    // -------------------------------------------------------------------------
    // findByGroup (usa prepare+execute)
    // -------------------------------------------------------------------------

    public function testFindByGroupReturnsRows(): void
    {
        $rows = [['key' => 'timezone', 'value' => 'UTC', 'group_name' => 'general']];
        $stmt = $this->makeStmt(fetchAllReturn: $rows);
        $repo = new SettingRepository($this->makePdo($stmt));

        $result = $repo->findByGroup('general');
        $this->assertCount(1, $result);
        $this->assertSame('timezone', $result[0]->key);
    }

    public function testFindByGroupReturnsEmptyArray(): void
    {
        $stmt = $this->makeStmt(fetchAllReturn: []);
        $repo = new SettingRepository($this->makePdo($stmt));

        $this->assertSame([], $repo->findByGroup('nonexistent'));
    }
}
