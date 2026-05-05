<?php

declare(strict_types=1);

namespace Tests\Unit\Seeders;

use App\Core\Seeders\Partials\MenuPassSeeder;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

/**
 * ¿Qué pruebas aquí?
 * El seeder de pases/experiencias: que usa UPSERT (ON DUPLICATE KEY UPDATE),
 * que incluye slug en cada pase, y que todos los pases tienen image_url definida.
 *
 * ¿Qué me quieres demostrar?
 * Que re-ejecutar el seeder no falla con duplicados porque usa ON DUPLICATE KEY UPDATE.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se elimina el UPSERT del SQL, si se elimina el campo slug, o si algún pase
 * pierde su image_url, los tests fallarán detectando la regresión.
 */
final class MenuPassSeederTest extends TestCase
{
    public function testSqlContainsOnDuplicateKeyUpdate(): void
    {
        $capturedSql = null;

        $stmtStub = $this->createStub(PDOStatement::class);
        $stmtStub->method('execute')->willReturn(true);

        $pdoStub = $this->createStub(PDO::class);
        $pdoStub->method('prepare')->willReturnCallback(
            static function (string $sql) use ($stmtStub, &$capturedSql): PDOStatement {
                $capturedSql = $sql;
                return $stmtStub;
            }
        );

        $seeder = new MenuPassSeeder($pdoStub);
        $seeder->run(1);

        $this->assertNotNull($capturedSql, 'prepare() debe haber sido llamado');
        $this->assertStringContainsStringIgnoringCase(
            'ON DUPLICATE KEY UPDATE',
            $capturedSql,
            'El seeder debe usar UPSERT para ser idempotente'
        );
    }

    public function testSqlIncludesSlugField(): void
    {
        $capturedSql = null;

        $stmtStub = $this->createStub(PDOStatement::class);
        $stmtStub->method('execute')->willReturn(true);

        $pdoStub = $this->createStub(PDO::class);
        $pdoStub->method('prepare')->willReturnCallback(
            static function (string $sql) use ($stmtStub, &$capturedSql): PDOStatement {
                $capturedSql = $sql;
                return $stmtStub;
            }
        );

        $seeder = new MenuPassSeeder($pdoStub);
        $seeder->run(1);

        $this->assertStringContainsString('slug', $capturedSql, 'El SQL debe incluir el campo slug');
        $this->assertStringContainsString(':slug', $capturedSql, 'El SQL debe ligar el parámetro :slug');
    }

    public function testAllPassesHaveSlug(): void
    {
        $capturedParams = [];

        $stmtStub = $this->createStub(PDOStatement::class);
        $stmtStub->method('execute')->willReturnCallback(
            static function (array $params) use (&$capturedParams): bool {
                $capturedParams[] = $params;
                return true;
            }
        );

        $pdoStub = $this->createStub(PDO::class);
        $pdoStub->method('prepare')->willReturn($stmtStub);

        $seeder = new MenuPassSeeder($pdoStub);
        $seeder->run(1);

        $this->assertCount(10, $capturedParams, 'Deben insertarse/actualizarse exactamente 10 pases');

        foreach ($capturedParams as $params) {
            $this->assertArrayHasKey('slug', $params, 'Cada pase debe tener slug en los parámetros');
            $this->assertNotEmpty($params['slug'], 'El slug no puede estar vacío');
            $this->assertArrayHasKey('image_url', $params, 'Cada pase debe tener image_url');
            $this->assertNotEmpty($params['image_url'], 'La image_url no puede estar vacía');
        }
    }

    public function testAllSlugsAreUnique(): void
    {
        $capturedParams = [];

        $stmtStub = $this->createStub(PDOStatement::class);
        $stmtStub->method('execute')->willReturnCallback(
            static function (array $params) use (&$capturedParams): bool {
                $capturedParams[] = $params;
                return true;
            }
        );

        $pdoStub = $this->createStub(PDO::class);
        $pdoStub->method('prepare')->willReturn($stmtStub);

        $seeder = new MenuPassSeeder($pdoStub);
        $seeder->run(1);

        $slugs = \array_column($capturedParams, 'slug');
        $this->assertSame(
            \count($slugs),
            \count(\array_unique($slugs)),
            'Todos los slugs de pases deben ser únicos'
        );
    }
}
