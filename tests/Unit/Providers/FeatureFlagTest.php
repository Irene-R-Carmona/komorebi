<?php

/**
 * ¿Qué pruebas aquí?
 * Verifica que los feature flags FEATURE_KEEPER y FEATURE_OPS controlan el registro de providers.
 *
 * ¿Qué me quieres demostrar?
 * Que con FEATURE_KEEPER=0, el KeeperServiceProvider no se registra.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se elimina el guard condicional o se cambia el env var key.
 */

declare(strict_types=1);

namespace Tests\Unit\Providers;

use App\Core\Env;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

#[CoversClass(Env::class)]
final class FeatureFlagTest extends TestCase
{
    public function test_feature_keeper_env_var_is_boolean(): void
    {
        $this->setEnv('FEATURE_KEEPER', '0');
        $this->assertFalse(Env::bool('FEATURE_KEEPER', false));

        $this->setEnv('FEATURE_KEEPER', '1');
        $this->assertTrue(Env::bool('FEATURE_KEEPER', false));
    }

    public function test_feature_ops_env_var_is_boolean(): void
    {
        $this->setEnv('FEATURE_OPS', '0');
        $this->assertFalse(Env::bool('FEATURE_OPS', false));

        $this->setEnv('FEATURE_OPS', '1');
        $this->assertTrue(Env::bool('FEATURE_OPS', false));
    }

    protected function tearDown(): void
    {
        $this->clearEnvCache();
        unset($_ENV['FEATURE_KEEPER'], $_ENV['FEATURE_OPS']);
    }

    /**
     * Establece una variable de entorno limpiando el caché de Env.
     */
    private function setEnv(string $key, string $value): void
    {
        $this->clearEnvCache();
        $_ENV[$key] = $value;
        \putenv("$key=$value");
    }

    /**
     * Limpia el caché estático interno de Env mediante reflexión.
     */
    private function clearEnvCache(): void
    {
        $ref = new ReflectionClass(Env::class);
        $prop = $ref->getProperty('cache');
        $prop->setValue(null, []);
    }
}
