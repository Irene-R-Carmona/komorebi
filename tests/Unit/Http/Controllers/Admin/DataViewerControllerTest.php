<?php

/**
 * ¿Qué pruebas aquí?
 * Verifica que Admin\DataViewerController::index() retorna null (View::render hace echo).
 *
 * ¿Qué me quieres demostrar?
 * Que index() no retorna ResponseInterface sino null, siguiendo el patrón de controladores
 * que renderizan vistas mediante echo.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si index() empieza a retornar una ResponseInterface en lugar de null,
 * o si la vista 'admin/data-viewer' deja de existir.
 */

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Admin;

use App\Http\Controllers\Admin\DataViewerController;
use Tests\Support\ControllerTestCase;

final class DataViewerControllerTest extends ControllerTestCase
{
    public function test_index_returns_null(): void
    {
        \ob_start();
        $result = new DataViewerController()->index($this->makeGetRequest('/admin/data-viewer'));
        \ob_end_clean();

        $this->assertNull($result);
    }

    public function test_class_has_index_method(): void
    {
        $this->assertTrue(\method_exists(DataViewerController::class, 'index'));
    }
}
