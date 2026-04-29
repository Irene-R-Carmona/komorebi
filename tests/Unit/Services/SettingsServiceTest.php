<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? SettingsService: getAll y getByGroup delegan al repositorio; isSmtpEnabled devuelve bool.
 * ¿Qué me quieres demostrar? Que la construcción sin repo y con repo funciona y los métodos de consulta retornan datos.
 * ¿Qué va a fallar en este test si se cambia el código? Si getAll deja de devolver array o isSmtpEnabled deja de retornar bool.
 */

namespace Tests\Unit\Services;

use App\Domain\DTO\SettingDTO;
use App\Exceptions\ConfigurationException;
use App\Repositories\Contracts\SettingRepositoryInterface;
use App\Services\SettingsService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SettingsService::class)]
final class SettingsServiceTest extends TestCase
{
    private SettingRepositoryInterface $repoStub;
    private SettingsService $service;

    protected function setUp(): void
    {
        $this->repoStub = $this->createStub(SettingRepositoryInterface::class);
        $this->service  = new SettingsService($this->repoStub);
    }

    public function testGetAllReturnsArray(): void
    {
        $this->repoStub->method('findAll')->willReturn([]);

        $result = $this->service->getAll();

        $this->assertIsArray($result);
    }

    public function testGetByGroupReturnsArray(): void
    {
        $this->repoStub->method('findByGroup')->willReturn([]);

        $result = $this->service->getByGroup('email');

        $this->assertIsArray($result);
    }

    public function testIsSmtpEnabledReturnsBool(): void
    {
        $result = $this->service->isSmtpEnabled();

        $this->assertIsBool($result);
    }

    public function testGetSmtpConfigReturnsArray(): void
    {
        $result = $this->service->getSmtpConfig();

        $this->assertIsArray($result);
    }

    public function testValidateReturnsArray(): void
    {
        $result = $this->service->validate();

        $this->assertIsArray($result);
    }

    // --- Tests de isSmtpEnabled con datos reales ---

    public function testIsSmtpEnabledReturnsTrueWhenSettingIsOne(): void
    {
        $this->repoStub->method('findAll')->willReturn([
            new SettingDTO('smtp_enabled', '1', 'bool', 'smtp', null, false),
        ]);
        $service = new SettingsService($this->repoStub);

        $this->assertTrue($service->isSmtpEnabled());
    }

    public function testIsSmtpEnabledReturnsFalseWhenSettingIsZero(): void
    {
        $this->repoStub->method('findAll')->willReturn([
            new SettingDTO('smtp_enabled', '0', 'bool', 'smtp', null, false),
        ]);
        $service = new SettingsService($this->repoStub);

        $this->assertFalse($service->isSmtpEnabled());
    }

    // --- Tests de validate() con datos reales ---

    public function testValidateReturnsFalseWhenAppNameMissing(): void
    {
        $this->repoStub->method('findAll')->willReturn([]);
        $service = new SettingsService($this->repoStub);

        $result = $service->validate();

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['issues']);
    }

    public function testValidateReturnsTrueWhenAppNameSet(): void
    {
        $this->repoStub->method('findAll')->willReturn([
            new SettingDTO('app_name', 'Komorebi Café', 'string', 'app', null, true),
        ]);
        $service = new SettingsService($this->repoStub);

        $result = $service->validate();

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['issues']);
    }

    public function testValidateIncludesSmtpIssueWhenEnabledButNoHost(): void
    {
        $this->repoStub->method('findAll')->willReturn([
            new SettingDTO('app_name', 'Komorebi Café', 'string', 'app', null, true),
            new SettingDTO('smtp_enabled', '1', 'bool', 'smtp', null, false),
        ]);
        $service = new SettingsService($this->repoStub);

        $result = $service->validate();

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['issues']);
    }

    // --- Tests de getSmtpConfig() ---

    public function testGetSmtpConfigContainsAllRequiredKeys(): void
    {
        $this->repoStub->method('findAll')->willReturn([]);
        $service = new SettingsService($this->repoStub);

        $config = $service->getSmtpConfig();

        foreach (['enabled', 'host', 'port', 'username', 'password', 'from_email', 'from_name', 'encryption'] as $key) {
            $this->assertArrayHasKey($key, $config);
        }
    }

    public function testGetSmtpConfigReturnsActualValues(): void
    {
        $this->repoStub->method('findAll')->willReturn([
            new SettingDTO('smtp_host', 'mail.example.com', 'string', 'smtp', null, false),
            new SettingDTO('smtp_port', '465', 'int', 'smtp', null, false),
        ]);
        $service = new SettingsService($this->repoStub);

        $config = $service->getSmtpConfig();

        $this->assertSame('mail.example.com', $config['host']);
        $this->assertSame(465, $config['port']);
    }

    // --- Tests de getStats() ---

    public function testGetStatsReturnsZeroCountForEmptySettings(): void
    {
        $this->repoStub->method('findAll')->willReturn([]);
        $service = new SettingsService($this->repoStub);

        $stats = $service->getStats();

        $this->assertSame(0, $stats['total']);
        $this->assertEmpty($stats['groups']);
        $this->assertFalse($stats['smtp_enabled']);
    }

    public function testGetStatsGroupsSettingsByKeyPrefix(): void
    {
        $this->repoStub->method('findAll')->willReturn([
            new SettingDTO('smtp_host', 'localhost', 'string', 'smtp', null, false),
            new SettingDTO('smtp_port', '587', 'int', 'smtp', null, false),
            new SettingDTO('app_name', 'Komorebi', 'string', 'app', null, true),
        ]);
        $service = new SettingsService($this->repoStub);

        $stats = $service->getStats();

        $this->assertSame(3, $stats['total']);
        $this->assertSame(2, $stats['groups']['smtp']);
        $this->assertSame(1, $stats['groups']['app']);
    }

    // --- Tests de resetToDefault() ---

    public function testResetToDefaultThrowsConfigurationExceptionForUnknownKey(): void
    {
        $service = new SettingsService($this->repoStub);

        $this->expectException(ConfigurationException::class);

        $service->resetToDefault('this_key_does_not_exist_xyz');
    }
}
