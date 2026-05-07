<?php

declare(strict_types=1);

namespace App\Http\Controllers\Keeper;

use App\Core\Database;
use App\Core\Session;
use App\Core\View;
use PDO;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Controlador de Turnos del Keeper
 *
 * Muestra los próximos turnos asignados al usuario autenticado
 * consultando la tabla staff_shifts.
 */
final class ScheduleController
{
    public function index(ServerRequestInterface $request): ?ResponseInterface
    {
        $userId = (int) Session::get('user_id');

        $db = Database::getConnection();
        $stmt = $db->prepare(
            'SELECT ss.shift_date, ss.shift_start, ss.shift_end, ss.notes,
                    c.name AS cafe_name
             FROM staff_shifts ss
             LEFT JOIN cafes c ON c.id = ss.cafe_id
             WHERE ss.user_id = :uid
               AND ss.shift_date >= CURDATE()
             ORDER BY ss.shift_date ASC, ss.shift_start ASC
             LIMIT 30'
        );
        $stmt->execute([':uid' => $userId]);
        $shifts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        View::render('backoffice/keeper/schedule/index', [
            'titulo' => 'Mis Turnos',
            'shifts' => $shifts,
        ], [], 'backoffice');

        return null;
    }
}
