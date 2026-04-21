<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * UserProfileService: lectura, actualización de perfil y avatar del usuario.
 *
 * ¿Qué me quieres demostrar?
 * Que getCurrentProfile lanza AuthenticationException cuando no hay sesión activa,
 * que getProfile lanza NotFoundException cuando el usuario no existe, que updateProfile
 * valida nombre y email antes de llamar al repositorio, y que updateAvatar delega en
 * el modelo User con la configuración correcta.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se eliminan las validaciones de nombre/email en updateProfile, si getProfile deja
 * de lanzar NotFoundException, si getCurrentProfile deja de verificar la sesión, o si
 * se modifica el contrato Result del servicio.
 */

namespace Tests\Unit\Services;

use App\Core\Result;
use App\Exceptions\AuthenticationException;
use App\Exceptions\NotFoundException;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\UserProfileService;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(UserProfileService::class)]
final class UserProfileServiceTest extends TestCase
{
    private UserProfileService $service;
    private UserRepositoryInterface&Stub $userRepoStub;
    private User $userModel;

    protected function setUp(): void
    {
        $this->userRepoStub = $this->createStub(UserRepositoryInterface::class);

        // User es final: se instancia con un stub de PDO
        $pdoStub  = $this->createStub(PDO::class);
        $stmtStub = $this->createStub(PDOStatement::class);
        $stmtStub->method('execute')->willReturn(true);
        $stmtStub->method('fetch')->willReturn(false);
        $pdoStub->method('prepare')->willReturn($stmtStub);

        $this->userModel = new User($pdoStub);

        $this->service = new UserProfileService(
            $this->userRepoStub,
            $this->userModel,
        );
    }

    #[TestDox('getCurrentProfile lanza AuthenticationException cuando no hay sesión activa')]
    public function testGetCurrentProfileThrowsWhenNoSession(): void
    {
        $this->expectException(AuthenticationException::class);

        $this->service->getCurrentProfile();
    }

    #[TestDox('getProfile retorna los datos del usuario con sus roles')]
    public function testGetProfileReturnsUserDataWithRoles(): void
    {
        $this->userRepoStub
            ->method('findById')
            ->willReturn([
                'id'          => 1,
                'uuid'        => 'abc-uuid',
                'name'        => 'María García',
                'email'       => 'maria@example.com',
                'is_active'   => true,
                'cafe_id'     => null,
                'avatar'      => null,
                'preferences' => null,
                'created_at'  => '2024-01-15 10:00:00',
            ]);

        $this->userRepoStub
            ->method('getRoles')
            ->willReturn([['slug' => 'user'], ['slug' => 'manager']]);

        $profile = $this->service->getProfile(1);

        $this->assertSame(1, $profile['id']);
        $this->assertSame('María García', $profile['name']);
        $this->assertContains('user', $profile['roles']);
        $this->assertContains('manager', $profile['roles']);
        $this->assertTrue($profile['is_active']);
    }

    #[TestDox('getProfile lanza NotFoundException cuando el usuario no existe')]
    public function testGetProfileThrowsNotFoundExceptionWhenUserDoesNotExist(): void
    {
        $this->userRepoStub
            ->method('findById')
            ->willReturn(null);

        $this->expectException(NotFoundException::class);

        $this->service->getProfile(404);
    }

    #[TestDox('updateProfile retorna fail cuando el nombre es una cadena vacía')]
    public function testUpdateProfileReturnsFailWhenNameIsEmpty(): void
    {
        $result = $this->service->updateProfile(1, '');

        $this->assertFalse($result->ok);
        $this->assertNotEmpty($result->error);
    }

    #[TestDox('updateProfile retorna fail cuando el nombre supera 100 caracteres')]
    public function testUpdateProfileReturnsFailWhenNameTooLong(): void
    {
        $result = $this->service->updateProfile(1, \str_repeat('a', 101));

        $this->assertFalse($result->ok);
        $this->assertNotEmpty($result->error);
    }

    #[TestDox('updateProfile retorna fail cuando el email tiene formato inválido')]
    public function testUpdateProfileReturnsFailWhenEmailIsInvalid(): void
    {
        $result = $this->service->updateProfile(1, ['email' => 'no-es-un-email']);

        $this->assertFalse($result->ok);
        $this->assertNotEmpty($result->error);
    }

    #[TestDox('updateProfile retorna fail cuando el array no contiene campos válidos')]
    public function testUpdateProfileReturnsFailWhenNoFieldsProvided(): void
    {
        $result = $this->service->updateProfile(1, []);

        $this->assertFalse($result->ok);
        $this->assertNotEmpty($result->error);
    }

    #[TestDox('updateProfile retorna ok cuando se proporciona un nombre válido')]
    public function testUpdateProfileReturnsOkWithValidName(): void
    {
        $result = $this->service->updateProfile(1, 'Nombre Válido');

        $this->assertTrue($result->ok);
    }

    #[TestDox('updateProfile retorna ok cuando se proporciona nombre y email válidos en array')]
    public function testUpdateProfileReturnsOkWithValidArrayData(): void
    {
        $result = $this->service->updateProfile(1, [
            'name'  => 'Ana López',
            'email' => 'ana@example.com',
        ]);

        $this->assertTrue($result->ok);
    }

    #[TestDox('updateAvatar retorna ok con el nombre de fichero cuando la operación tiene éxito')]
    public function testUpdateAvatarReturnsOkWithFilename(): void
    {
        // El PDO stub en setUp retorna PDOStatement que ejecuta con éxito
        $result = $this->service->updateAvatar(1, 'avatar_abc123.jpg');

        $this->assertTrue($result->ok);
        $this->assertSame('avatar_abc123.jpg', $result->data);
    }

    #[TestDox('updateAvatar retorna fail cuando el modelo lanza RuntimeException')]
    public function testUpdateAvatarReturnsFailOnRuntimeException(): void
    {
        $pdoStub = $this->createStub(PDO::class);
        $pdoStub->method('prepare')->willThrowException(new RuntimeException('Error de base de datos'));

        $userModel = new User($pdoStub);
        $service   = new UserProfileService($this->userRepoStub, $userModel);

        $result = $service->updateAvatar(1, 'avatar.jpg');

        $this->assertFalse($result->ok);
        $this->assertNotEmpty($result->error);
    }
}
