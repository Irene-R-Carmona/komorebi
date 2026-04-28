<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\LoyaltyRewardCatalog;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * ¿Qué pruebas aquí? Métodos del modelo LoyaltyRewardCatalog con stubs de PDO.
 * ¿Qué me quieres demostrar? Que getActiveRewards, findByType y getRewardsForTier delegan en PDO.
 * ¿Qué va a fallar en este test si se cambia el código? Cambios en la lógica de filtrado de tier o en las queries.
 */
#[CoversClass(LoyaltyRewardCatalog::class)]
final class LoyaltyRewardCatalogTest extends TestCase
{
    private PDO $pdo;
    private PDOStatement $stmt;
    private LoyaltyRewardCatalog $model;

    protected function setUp(): void
    {
        $this->pdo   = $this->createStub(PDO::class);
        $this->stmt  = $this->createStub(PDOStatement::class);
        $this->model = new LoyaltyRewardCatalog($this->pdo);
    }

    // ── getActiveRewards ──────────────────────────────────────────

    public function testGetActiveRewardsReturnsArray(): void
    {
        $rewards = [
            ['id' => 1, 'name' => 'Café gratis', 'type' => 'free_drink', 'tier_required' => 'bronze', 'is_active' => true],
        ];
        $this->stmt->method('fetchAll')->willReturn($rewards);
        $this->pdo->method('query')->willReturn($this->stmt);

        $result = $this->model->getActiveRewards();

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertSame('free_drink', $result[0]['type']);
    }

    public function testGetActiveRewardsReturnsEmptyArrayWhenNone(): void
    {
        $this->stmt->method('fetchAll')->willReturn([]);
        $this->pdo->method('query')->willReturn($this->stmt);

        $result = $this->model->getActiveRewards();

        $this->assertSame([], $result);
    }

    // ── findByType ────────────────────────────────────────────────

    public function testFindByTypeReturnsArrayWhenFound(): void
    {
        $row = ['id' => 2, 'name' => 'Visita gratis', 'type' => 'free_visit', 'tier_required' => 'silver', 'is_active' => true];
        $this->stmt->method('fetch')->willReturn($row);
        $this->pdo->method('prepare')->willReturn($this->stmt);

        $result = $this->model->findByType('free_visit');

        $this->assertIsArray($result);
        $this->assertSame('free_visit', $result['type']);
    }

    public function testFindByTypeReturnsNullWhenNotFound(): void
    {
        $this->stmt->method('fetch')->willReturn(false);
        $this->pdo->method('prepare')->willReturn($this->stmt);

        $result = $this->model->findByType('nonexistent');

        $this->assertNull($result);
    }

    // ── getRewardsForTier ─────────────────────────────────────────

    public function testGetRewardsForTierReturnsBronzeEligibleRewards(): void
    {
        // bronze tier level=1, so rewards with tier_required=bronze (level 1) are eligible
        $allRewards = [
            ['id' => 1, 'name' => 'Café gratis', 'type' => 'free_drink', 'tier_required' => 'bronze', 'is_active' => true],
            ['id' => 2, 'name' => 'Descuento', 'type' => 'discount', 'tier_required' => 'gold', 'is_active' => true],
        ];
        $this->stmt->method('fetchAll')->willReturn($allRewards);
        $this->pdo->method('query')->willReturn($this->stmt);

        $result = $this->model->getRewardsForTier('bronze');

        // Only bronze-level reward is eligible for bronze tier (gold requires level 3, bronze is level 1)
        $result = \array_values($result);
        $this->assertCount(1, $result);
        $this->assertSame('bronze', $result[0]['tier_required']);
    }

    public function testGetRewardsForTierReturnsPlatinumIncludesAllRewards(): void
    {
        // platinum tier level=4, so all tiers are eligible
        $allRewards = [
            ['id' => 1, 'tier_required' => 'bronze', 'is_active' => true],
            ['id' => 2, 'tier_required' => 'silver', 'is_active' => true],
            ['id' => 3, 'tier_required' => 'gold', 'is_active' => true],
            ['id' => 4, 'tier_required' => 'platinum', 'is_active' => true],
        ];
        $this->stmt->method('fetchAll')->willReturn($allRewards);
        $this->pdo->method('query')->willReturn($this->stmt);

        $result = $this->model->getRewardsForTier('platinum');

        $this->assertCount(4, \array_values($result));
    }
}
