<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * La estructura y jerarquía de herencia de LoggingPDO sin requerir una conexión real a BD.
 *
 * ¿Qué me quieres demostrar?
 * Que LoggingPDO extiende PDO, por lo que Database::getConnection(): PDO sigue siendo
 * compatible con todos los repositorios sin cambios.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si LoggingPDO deja de extender PDO o cambia de namespace.
 */

namespace Tests\Unit\Core;

use App\Core\LoggingPDO;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LoggingPDO::class)]
final class LoggingPDOTest extends TestCase
{
    public function testLogginPDOExtendsPDO(): void
    {
        $parents = \class_parents(LoggingPDO::class);

        $this->assertIsArray($parents);
        $this->assertContains(PDO::class, $parents);
    }

    public function testLogginPDOIsInAppCoreNamespace(): void
    {
        $this->assertSame('App\\Core\\LoggingPDO', LoggingPDO::class);
    }

    public function testLogginPDOStatementExtendsPDOStatement(): void
    {
        $parents = \class_parents(\App\Core\LoggingPDOStatement::class);

        $this->assertIsArray($parents);
        $this->assertContains(PDOStatement::class, $parents);
    }
}
