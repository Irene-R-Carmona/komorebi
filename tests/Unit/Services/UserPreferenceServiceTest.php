<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * UserPreferenceService: lectura y escritura de preferencias de usuario persistidas en el perfil.
 *
 * ¿Qué me quieres demostrar?
 * Que getPreferences devuelve array vacío cuando el usuario no existe o no tiene preferencias,
 * que decodifica correctamente el campo JSON cuando viene como string, y que updatePreferences
 * delega directamente en el repositorio devolviendo su resultado.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se elimina la decodificación JSON de getPreferences, si se deja de retornar []
 * para usuarios inexistentes, o si updatePreferences introduce validaciones adicionales
 * que cambien su comportamiento de delegación directa.
 */

namespace Tests\Unit\Services;

use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\UserPreferenceService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

#[CoversClass(UserPreferenceService::class)]
final class UserPreferenceServiceTest extends TestCase
{
    private UserPreferenceService $service;
    private UserRepositoryInterface&Stub $userRepoStub;

    protected function setUp(): void
    {
        $this->userRepoStub = $this->createStub(UserRepositoryInterface::class);
        $this->service      = new UserPreferenceService($this->userRepoStub);
    }

    #[TestDox('getPreferences retorna array vacío cuando el usuario no existe')]
    public function testGetPreferencesReturnsEmptyArrayWhenUserNotFound(): void
    {
        $this->userRepoStub
            ->method('findById')
            ->willReturn(null);

        $result = $this->service->getPreferences(99);

        $this->assertSame([], $result);
    }

    #[TestDox('getPreferences retorna array vacío cuando el campo preferences está vacío')]
    public function testGetPreferencesReturnsEmptyArrayWhenPreferencesFieldIsEmpty(): void
    {
        $this->userRepoStub
            ->method('findById')
            ->willReturn(['id' => 1, 'name' => 'Test', 'preferences' => '']);

        $result = $this->service->getPreferences(1);

        $this->assertSame([], $result);
    }

    #[TestDox('getPreferences retorna array vacío cuando el campo preferences es null')]
    public function testGetPreferencesReturnsEmptyArrayWhenPreferencesIsNull(): void
    {
        $this->userRepoStub
            ->method('findById')
            ->willReturn(['id' => 1, 'name' => 'Test', 'preferences' => null]);

        $result = $this->service->getPreferences(1);

        $this->assertSame([], $result);
    }

    #[TestDox('getPreferences decodifica el campo preferences cuando es un string JSON')]
    public function testGetPreferencesDecodesJsonStringPreferences(): void
    {
        $preferencesArray = ['theme' => 'dark', 'language' => 'es', 'notifications' => true];

        $this->userRepoStub
            ->method('findById')
            ->willReturn([
                'id'          => 2,
                'name'        => 'Ana',
                'preferences' => \json_encode($preferencesArray),
            ]);

        $result = $this->service->getPreferences(2);

        $this->assertSame('dark', $result['theme']);
        $this->assertSame('es', $result['language']);
        $this->assertTrue($result['notifications']);
    }

    #[TestDox('getPreferences devuelve el array de preferencias directamente cuando ya es array')]
    public function testGetPreferencesReturnsArrayDirectlyWhenAlreadyArray(): void
    {
        $preferencesArray = ['newsletter' => false, 'locale' => 'es-ES'];

        $this->userRepoStub
            ->method('findById')
            ->willReturn([
                'id'          => 3,
                'name'        => 'Carlos',
                'preferences' => $preferencesArray,
            ]);

        $result = $this->service->getPreferences(3);

        $this->assertSame(false, $result['newsletter']);
        $this->assertSame('es-ES', $result['locale']);
    }

    #[TestDox('updatePreferences retorna true cuando el repositorio confirma la actualización')]
    public function testUpdatePreferencesReturnsTrueWhenRepoSucceeds(): void
    {
        /** @var UserRepositoryInterface&MockObject $userRepoMock */
        $userRepoMock = $this->createMock(UserRepositoryInterface::class);
        $userRepoMock
            ->expects($this->once())
            ->method('updatePreferences')
            ->with(5, ['theme' => 'light'])
            ->willReturn(true);

        $service = new UserPreferenceService($userRepoMock);
        $result  = $service->updatePreferences(5, ['theme' => 'light']);

        $this->assertTrue($result);
    }

    #[TestDox('updatePreferences retorna false cuando el repositorio falla')]
    public function testUpdatePreferencesReturnsFalseWhenRepoFails(): void
    {
        /** @var UserRepositoryInterface&Stub $userRepoMock */
        $userRepoMock = $this->createStub(UserRepositoryInterface::class);
        $userRepoMock
            ->method('updatePreferences')
            ->willReturn(false);

        $service = new UserPreferenceService($userRepoMock);
        $result  = $service->updatePreferences(5, ['theme' => 'light']);

        $this->assertFalse($result);
    }

    #[TestDox('updatePreferences acepta array vacío de preferencias')]
    public function testUpdatePreferencesAcceptsEmptyArray(): void
    {
        /** @var UserRepositoryInterface&MockObject $userRepoMock */
        $userRepoMock = $this->createMock(UserRepositoryInterface::class);
        $userRepoMock
            ->expects($this->once())
            ->method('updatePreferences')
            ->with(1, [])
            ->willReturn(true);

        $service = new UserPreferenceService($userRepoMock);

        $this->assertTrue($service->updatePreferences(1, []));
    }
}
