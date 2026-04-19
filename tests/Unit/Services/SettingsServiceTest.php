<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * SettingsService: estructura de los arrays devueltos por validate(), getSmtpConfig(),
 * getStats() y el filtrado por prefijo de getByGroup().
 *
 * ¿Qué me quieres demostrar?
 * Que la estructura de respuesta es siempre consistente independientemente
 * del contenido de la base de datos, y que getByGroup filtra correctamente.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si validate() deja de devolver las claves 'valid'/'issues', si getSmtpConfig()
 * pierde alguna clave requerida, o si getByGroup() deja de filtrar por prefijo.
 */

namespace Tests\Unit\Services;

use App\Services\SettingsService;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(SettingsService::class)]
final class SettingsServiceTest extends TestCase
{
    private SettingsService $service;

    protected function setUp(): void
    {
        $this->service = new SettingsService();
    }

    // ──────────────────────────────────────────────
    // validate — estructura de respuesta
    // ──────────────────────────────────────────────

    public function testValidateDevuelveArrayConClavesEsperadas(): void
    {
        $result = $this->service->validate();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('valid', $result);
        $this->assertArrayHasKey('issues', $result);
        $this->assertIsBool($result['valid']);
        $this->assertIsArray($result['issues']);
    }

    // ──────────────────────────────────────────────
    // getSmtpConfig — estructura siempre presente
    // ──────────────────────────────────────────────

    public function testGetSmtpConfigDevuelveTodasLasClavesRequeridas(): void
    {
        $config = $this->service->getSmtpConfig();

        $this->assertIsArray($config);
        $this->assertArrayHasKey('enabled', $config);
        $this->assertArrayHasKey('host', $config);
        $this->assertArrayHasKey('port', $config);
        $this->assertArrayHasKey('username', $config);
        $this->assertArrayHasKey('from_email', $config);
        $this->assertArrayHasKey('from_name', $config);
        $this->assertArrayHasKey('encryption', $config);
    }

    public function testGetSmtpConfigPortEsEntero(): void
    {
        $config = $this->service->getSmtpConfig();

        $this->assertIsInt($config['port']);
    }

    // ──────────────────────────────────────────────
    // getStats — estructura siempre presente
    // ──────────────────────────────────────────────

    public function testGetStatsDevuelveClavesEsperadas(): void
    {
        $stats = $this->service->getStats();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total', $stats);
        $this->assertArrayHasKey('groups', $stats);
        $this->assertArrayHasKey('smtp_enabled', $stats);
        $this->assertIsInt($stats['total']);
        $this->assertIsArray($stats['groups']);
        $this->assertIsBool($stats['smtp_enabled']);
    }

    // ──────────────────────────────────────────────
    // getByGroup — lógica de filtrado por prefijo
    // ──────────────────────────────────────────────

    public function testGetByGroupConPrefijoInexistenteDevuelveVacio(): void
    {
        // Un prefijo que no puede existir en settings reales
        $result = $this->service->getByGroup('zzz_inexistente_prefix_');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    // ──────────────────────────────────────────────
    // isSmtpEnabled — tipo de retorno
    // ──────────────────────────────────────────────

    public function testIsSmtpEnabledDevuelveBool(): void
    {
        $result = $this->service->isSmtpEnabled();

        $this->assertIsBool($result);
    }
}
