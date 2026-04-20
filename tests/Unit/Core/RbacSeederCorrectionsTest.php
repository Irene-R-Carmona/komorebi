<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * Verifica la configuración de permisos y asignaciones de roles en RbacSeeder
 * inspeccionando el código fuente del archivo (sin instanciar la clase, que
 * requiere conexión a base de datos).
 *
 * ¿Qué me quieres demostrar?
 * Que el seeder contiene las correcciones RBAC necesarias:
 * - waitlist.view_own existe en createPermissions()
 * - reception no tiene review.moderate
 * - reception tiene cafe.animals.view
 * - user tiene waitlist.view_own
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Cualquier regresión que elimine o cambie estos permisos/asignaciones
 * en RbacSeeder.php hará fallar los tests inmediatamente.
 */

use App\Core\Seeders\RbacSeeder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RbacSeeder::class)]
final class RbacSeederCorrectionsTest extends TestCase
{
    private string $seederSource;

    protected function setUp(): void
    {
        $path = dirname(__DIR__, 3) . '/app/Core/Seeders/RbacSeeder.php';
        $this->assertFileExists($path, 'RbacSeeder.php debe existir');
        $source = file_get_contents($path);
        $this->assertIsString($source);
        $this->seederSource = $source;
    }

    // ─────────────────────────────────────────────────────────────
    // Permiso waitlist.view_own debe existir en createPermissions()
    // ─────────────────────────────────────────────────────────────

    public function testWaitlistViewOwnPermissionIsDeclared(): void
    {
        $this->assertStringContainsString(
            "'waitlist.view_own'",
            $this->seederSource,
            'El permiso waitlist.view_own debe estar declarado en RbacSeeder::createPermissions()'
        );
    }

    // ─────────────────────────────────────────────────────────────
    // Reception: review.moderate debe estar AUSENTE
    // ─────────────────────────────────────────────────────────────

    public function testReceptionBlockDoesNotContainReviewModerate(): void
    {
        // Extrae el bloque del rol 'reception' hasta el siguiente rol
        $receptionBlock = $this->extractRoleBlock('reception');

        $this->assertNotEmpty($receptionBlock, "No se encontró el bloque 'reception' en assignPermissionsToRoles()");

        $this->assertStringNotContainsString(
            "'review.moderate'",
            $receptionBlock,
            "El rol 'reception' NO debe tener 'review.moderate' — solo supervisor y manager moderan reseñas"
        );
    }

    // ─────────────────────────────────────────────────────────────
    // Reception: cafe.animals.view debe estar PRESENTE
    // ─────────────────────────────────────────────────────────────

    public function testReceptionBlockContainsCafeAnimalsView(): void
    {
        $receptionBlock = $this->extractRoleBlock('reception');

        $this->assertStringContainsString(
            "'cafe.animals.view'",
            $receptionBlock,
            "El rol 'reception' debe tener 'cafe.animals.view'"
        );
    }

    // ─────────────────────────────────────────────────────────────
    // User: waitlist.view_own debe estar PRESENTE
    // ─────────────────────────────────────────────────────────────

    public function testUserBlockContainsWaitlistViewOwn(): void
    {
        $userBlock = $this->extractRoleBlock('user');

        $this->assertStringContainsString(
            "'waitlist.view_own'",
            $userBlock,
            "El rol 'user' debe tener 'waitlist.view_own'"
        );
    }

    // ─────────────────────────────────────────────────────────────
    // Helper: extrae el bloque de un rol dentro de $rolePermissions
    // ─────────────────────────────────────────────────────────────

    /**
     * Extrae el fragmento de código del rol dentro de $rolePermissions
     * en el método assignPermissionsToRoles().
     */
    private function extractRoleBlock(string $role): string
    {
        // Acotar al contexto de $rolePermissions, no createRoles()
        $start = strpos($this->seederSource, '$rolePermissions = [');

        if ($start === false) {
            return '';
        }

        $context = substr($this->seederSource, $start);

        // Busca "'<role>' => [" seguido de contenido hasta el cierre "],"
        $pattern = "/'" . preg_quote($role, '/') . "'\s*=>\s*\[(.+?)\n\s+\],/s";

        if (preg_match($pattern, $context, $matches)) {
            return $matches[1];
        }

        return '';
    }
}
