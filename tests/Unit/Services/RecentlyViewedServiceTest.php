<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * RecentlyViewedService: getAll (con y sin cookie), getMaxItems y la lógica
 * de decodificación JSON de la cookie, vía $_COOKIE.
 *
 * ¿Qué me quieres demostrar?
 * Que getAll parsea correctamente la cookie JSON en array de enteros, que maneja
 * JSON inválido de forma segura y que getMaxItems siempre devuelve 10.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si MAX_ITEMS cambia de 10, si se cambia el nombre de la cookie RECENTLY_VIEWED,
 * o si la decodificación JSON deja de filtrar tipos no-array.
 */

namespace Tests\Unit\Services;

use App\Core\CookieManager;
use App\Services\RecentlyViewedService;
use PHPUnit\Framework\TestCase;

final class RecentlyViewedServiceTest extends TestCase
{
    private RecentlyViewedService $service;

    protected function setUp(): void
    {
        $_COOKIE = [];
        $this->service = new RecentlyViewedService();
    }

    protected function tearDown(): void
    {
        $_COOKIE = [];
    }

    // ──────────────────────────────────────────────
    // getMaxItems
    // ──────────────────────────────────────────────

    public function testGetMaxItemsRetorna10(): void
    {
        $this->assertSame(10, $this->service->getMaxItems());
    }

    // ──────────────────────────────────────────────
    // getAll — sin cookie
    // ──────────────────────────────────────────────

    public function testGetAllRetornaArrayVacioCuandoNoCookie(): void
    {
        $result = $this->service->getAll();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    // ──────────────────────────────────────────────
    // getAll — con cookie válida
    // ──────────────────────────────────────────────

    public function testGetAllRetornaArrayDeEnterosCuandoCookieValida(): void
    {
        $_COOKIE[CookieManager::RECENTLY_VIEWED] = \json_encode([3, 7, 1]);

        $result = $this->service->getAll();

        $this->assertIsArray($result);
        $this->assertCount(3, $result);
        $this->assertContains(3, $result);
        $this->assertContains(7, $result);
    }

    // ──────────────────────────────────────────────
    // getAll — cookie con JSON inválido
    // ──────────────────────────────────────────────

    public function testGetAllRetornaArrayVacioCuandoCookieConJsonInvalido(): void
    {
        $_COOKIE[CookieManager::RECENTLY_VIEWED] = 'no-es-json-valido';

        $result = $this->service->getAll();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testGetAllRetornaArrayVacioCuandoCookieEsStringNoArray(): void
    {
        $_COOKIE[CookieManager::RECENTLY_VIEWED] = \json_encode('solo-un-string');

        $result = $this->service->getAll();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    // ──────────────────────────────────────────────
    // getAll — garantía de tipos enteros
    // ──────────────────────────────────────────────

    public function testGetAllConvierteValoresAEnteros(): void
    {
        // La cookie puede contener strings que deben convertirse a int
        $_COOKIE[CookieManager::RECENTLY_VIEWED] = \json_encode(['5', '12', '3']);

        $result = $this->service->getAll();

        foreach ($result as $id) {
            $this->assertIsInt($id);
        }
    }
}
