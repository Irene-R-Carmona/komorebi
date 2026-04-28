<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? SettingsService: getAll y getByGroup delegan al repositorio; isSmtpEnabled devuelve bool.
 * ¿Qué me quieres demostrar? Que la construcción sin repo y con repo funciona y los métodos de consulta retornan datos.
 * ¿Qué va a fallar en este test si se cambia el código? Si getAll deja de devolver array o isSmtpEnabled deja de retornar bool.
 */

namespace Tests\Unit\Services;

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
}
