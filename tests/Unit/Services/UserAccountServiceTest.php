<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * UserAccountService: cambio de contraseña, eliminación de cuenta, verificación de email
 * y ciclo de activación/desactivación.
 *
 * ¿Qué me quieres demostrar?
 * Que los requisitos de complejidad de contraseña se validan antes de cualquier acceso a BD,
 * que la contraseña actual se verifica antes de actualizarla, y que la cuenta solo se anonimiza
 * cuando la contraseña de confirmación es correcta.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se relajan las validaciones de complejidad (longitud, mayúscula, número), si se elimina
 * la verificación de contraseña actual en changePassword, si deleteAccount no verifica la
 * contraseña antes de anonimizar o si deactivateAccount/reactivateAccount dejan de reflejar
 * el booleano devuelto por setActive.
 */

namespace Tests\Unit\Services;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\UserAccountService;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(UserAccountService::class)]
final class UserAccountServiceTest extends TestCase
{
    /** @var UserRepositoryInterface&Stub */
    private UserRepositoryInterface $userRepoStub;

    protected function setUp(): void
    {
        $this->userRepoStub = $this->createStub(UserRepositoryInterface::class);
    }

    // ─────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────

    /**
     * Crea un User con PDO stub cuyo fetch() devuelve $fetchResult.
     * Úsalo para controlar la respuesta de findById / findByEmail.
     *
     * @param array<string, mixed>|false $fetchResult
     */
    private function makeUser(array|false $fetchResult = false): User
    {
        $stmtStub = $this->createStub(PDOStatement::class);
        $stmtStub->method('execute')->willReturn(true);
        $stmtStub->method('fetch')->willReturn($fetchResult);

        $pdoStub = $this->createStub(PDO::class);
        $pdoStub->method('prepare')->willReturn($stmtStub);

        return new User($pdoStub);
    }

    /**
     * Crea un User cuyo PDO prepare() lanza RuntimeException.
     * Útil para simular errores de BD.
     */
    private function makeUserWithDbError(): User
    {
        $pdoStub = $this->createStub(PDO::class);
        $pdoStub->method('prepare')->willThrowException(new RuntimeException('DB error'));

        return new User($pdoStub);
    }

    private function makeService(User $userModel): UserAccountService
    {
        return new UserAccountService($this->userRepoStub, $userModel);
    }

    // ─────────────────────────────────────────────────────────────
    // changePassword — Validaciones de contraseña (sin acceso a BD)
    // ─────────────────────────────────────────────────────────────

    #[TestDox('Retorna fallo cuando la confirmación de nueva contraseña no coincide')]
    public function testChangePasswordWithMismatchedConfirmationReturnsFailure(): void
    {
        $result = $this->makeService($this->makeUser())
            ->changePassword(1, 'Current1A', 'NewPass1A', 'DifferentPass1A');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('no coinciden', $result->error ?? '');
    }

    #[TestDox('Retorna fallo cuando la nueva contraseña tiene menos de 8 caracteres')]
    public function testChangePasswordWithTooShortPasswordReturnsFailure(): void
    {
        $result = $this->makeService($this->makeUser())
            ->changePassword(1, 'Current1A', 'Abc1');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('8 caracteres', $result->error ?? '');
    }

    #[TestDox('Retorna fallo cuando la nueva contraseña no contiene letras mayúsculas')]
    public function testChangePasswordWithoutUppercaseLetterReturnsFailure(): void
    {
        $result = $this->makeService($this->makeUser())
            ->changePassword(1, 'Current1A', 'lowercase1234');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('mayúscula', $result->error ?? '');
    }

    #[TestDox('Retorna fallo cuando la nueva contraseña no contiene números')]
    public function testChangePasswordWithoutNumberReturnsFailure(): void
    {
        $result = $this->makeService($this->makeUser())
            ->changePassword(1, 'Current1A', 'NoNumbersHere');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('número', $result->error ?? '');
    }

    // ─────────────────────────────────────────────────────────────
    // changePassword — Acceso a BD y verificación de contraseña actual
    // ─────────────────────────────────────────────────────────────

    #[TestDox('Retorna fallo cuando el usuario no existe en ningún repositorio')]
    public function testChangePasswordWithNonExistentUserReturnsFailure(): void
    {
        $this->userRepoStub->method('findById')->willReturn(null);

        // userModel.findById devuelve null (fetch = false), userRepo.findById devuelve null
        $result = $this->makeService($this->makeUser(false))
            ->changePassword(999, 'Current1A', 'NewValid1A');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('no encontrado', $result->error ?? '');
    }

    #[TestDox('Retorna fallo cuando la contraseña actual es incorrecta')]
    public function testChangePasswordWithIncorrectCurrentPasswordReturnsFailure(): void
    {
        $realHash = \password_hash('CorrectPass1A', PASSWORD_ARGON2ID);
        $userData = ['id' => 1, 'email' => 'u@example.com', 'password' => $realHash];

        $result = $this->makeService($this->makeUser($userData))
            ->changePassword(1, 'WrongPass1A', 'NewValid1A');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('incorrecta', $result->error ?? '');
    }

    #[TestDox('Cambia la contraseña y retorna éxito cuando todos los datos son válidos')]
    public function testChangePasswordWithValidDataReturnsSuccess(): void
    {
        $realHash = \password_hash('CurrentPass1A', PASSWORD_ARGON2ID);
        $userData = ['id' => 1, 'email' => 'u@example.com', 'password' => $realHash];

        $result = $this->makeService($this->makeUser($userData))
            ->changePassword(1, 'CurrentPass1A', 'NewValidPass1A');

        $this->assertTrue($result->ok);
    }

    // ─────────────────────────────────────────────────────────────
    // deleteAccount
    // ─────────────────────────────────────────────────────────────

    #[TestDox('Retorna fallo cuando el usuario a eliminar no existe')]
    public function testDeleteAccountWithNonExistentUserReturnsFailure(): void
    {
        $this->userRepoStub->method('findById')->willReturn(null);

        $result = $this->makeService($this->makeUser())->deleteAccount(999, 'anypassword');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('no encontrado', $result->error ?? '');
    }

    #[TestDox('Retorna fallo cuando la contraseña de confirmación al eliminar es incorrecta')]
    public function testDeleteAccountWithIncorrectPasswordReturnsFailure(): void
    {
        $realHash = \password_hash('CorrectPass1A', PASSWORD_ARGON2ID);
        $userRow  = ['id' => 1, 'email' => 'u@example.com', 'password' => $realHash];

        $this->userRepoStub->method('findById')->willReturn($userRow);

        // verifyPassword usa password_verify; no requiere PDO para el caso incorrecto
        $result = $this->makeService($this->makeUser())->deleteAccount(1, 'WrongPass1A');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('incorrecta', $result->error ?? '');
    }

    #[TestDox('Anonimiza la cuenta y retorna éxito cuando la contraseña es correcta')]
    public function testDeleteAccountWithCorrectPasswordReturnsSuccess(): void
    {
        $realHash = \password_hash('CorrectPass1A', PASSWORD_ARGON2ID);
        $userRow  = ['id' => 1, 'email' => 'u@example.com', 'password' => $realHash];

        $this->userRepoStub->method('findById')->willReturn($userRow);
        $this->userRepoStub->method('anonymize')->willReturn(true);

        $result = $this->makeService($this->makeUser())->deleteAccount(1, 'CorrectPass1A');

        $this->assertTrue($result->ok);
    }

    // ─────────────────────────────────────────────────────────────
    // verifyEmail
    // ─────────────────────────────────────────────────────────────

    #[TestDox('Marca el email como verificado y retorna éxito')]
    public function testVerifyEmailReturnsSuccess(): void
    {
        $result = $this->makeService($this->makeUser())->verifyEmail(1);

        $this->assertTrue($result->ok);
    }

    #[TestDox('Retorna fallo cuando el modelo lanza RuntimeException al verificar email')]
    public function testVerifyEmailWhenModelThrowsRuntimeExceptionReturnsFailure(): void
    {
        $result = $this->makeService($this->makeUserWithDbError())->verifyEmail(1);

        $this->assertFalse($result->ok);
        $this->assertNotEmpty($result->error);
    }

    // ─────────────────────────────────────────────────────────────
    // deactivateAccount
    // ─────────────────────────────────────────────────────────────

    #[TestDox('Desactiva la cuenta y retorna éxito cuando setActive tiene éxito')]
    public function testDeactivateAccountReturnsSuccess(): void
    {
        // El stmtStub tiene execute() = true → setActive devuelve true
        $result = $this->makeService($this->makeUser())->deactivateAccount(1);

        $this->assertTrue($result->ok);
    }

    #[TestDox('Retorna fallo al desactivar cuando setActive devuelve false')]
    public function testDeactivateAccountWhenSetActiveReturnsFalseReturnsFailure(): void
    {
        $stmtStub = $this->createStub(PDOStatement::class);
        $stmtStub->method('execute')->willReturn(false);

        $pdoStub = $this->createStub(PDO::class);
        $pdoStub->method('prepare')->willReturn($stmtStub);

        $result = $this->makeService(new User($pdoStub))->deactivateAccount(1);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('desactivar', $result->error ?? '');
    }

    // ─────────────────────────────────────────────────────────────
    // reactivateAccount
    // ─────────────────────────────────────────────────────────────

    #[TestDox('Reactiva la cuenta y retorna éxito cuando setActive tiene éxito')]
    public function testReactivateAccountReturnsSuccess(): void
    {
        $result = $this->makeService($this->makeUser())->reactivateAccount(1);

        $this->assertTrue($result->ok);
    }

    #[TestDox('Retorna fallo al reactivar cuando setActive devuelve false')]
    public function testReactivateAccountWhenSetActiveReturnsFalseReturnsFailure(): void
    {
        $stmtStub = $this->createStub(PDOStatement::class);
        $stmtStub->method('execute')->willReturn(false);

        $pdoStub = $this->createStub(PDO::class);
        $pdoStub->method('prepare')->willReturn($stmtStub);

        $result = $this->makeService(new User($pdoStub))->reactivateAccount(1);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('reactivar', $result->error ?? '');
    }
}
