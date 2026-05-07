<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? UserPreferenceService: obtención y actualización de preferencias de usuario.
 * ¿Qué me quieres demostrar? Que getPreferences delega al repositorio y updatePreferences retorna bool.
 * ¿Qué va a fallar en este test si se cambia el código? Si los métodos dejan de delegar al repositorio.
 */

namespace Tests\Unit\Services;

use App\Domain\DTO\UserDTO;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\UserPreferenceService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(UserPreferenceService::class)]
final class UserPreferenceServiceTest extends TestCase
{
    private UserRepositoryInterface $userRepoStub;
    private UserPreferenceService $service;

    protected function setUp(): void
    {
        $this->userRepoStub = $this->createStub(UserRepositoryInterface::class);
        $this->service = new UserPreferenceService($this->userRepoStub);
    }

    public function testGetPreferencesReturnsArray(): void
    {
        $this->userRepoStub->method('findById')->willReturn(new UserDTO(id: 1, uuid: '', name: 'Test', email: 'test@test.com', avatar: null, roles: [], is_active: true, cafe_id: null, created_at: '', preferences: \json_encode(['theme' => 'dark', 'lang' => 'es'])));

        $result = $this->service->getPreferences(1);

        $this->assertIsArray($result);
    }

    public function testUpdatePreferencesReturnsBool(): void
    {
        $this->userRepoStub->method('updatePreferences')->willReturn(true);

        $result = $this->service->updatePreferences(1, ['theme' => 'light']);

        $this->assertIsBool($result);
    }

    public function testUpdatePreferencesReturnsFalseWhenFails(): void
    {
        $this->userRepoStub->method('updatePreferences')->willReturn(false);

        $result = $this->service->updatePreferences(1, ['theme' => 'light']);

        $this->assertFalse($result);
    }
}
