<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\View;
use App\Repositories\Contracts\AuthLogRepositoryInterface;
use Psr\Http\Message\ResponseInterface;
use Random\RandomException;

/**
 * Controlador de Logs de Autenticación (Admin) — SSR únicamente.
 * Las consultas JSON paginadas, suspicious-count y exportación CSV
 * están en Api\V1\Admin\LogApiController.
 */
final class AuthLogController
{
    private AuthLogRepositoryInterface $authLogRepo;

    public function __construct(?AuthLogRepositoryInterface $authLogRepo = null)
    {
        $this->authLogRepo = $authLogRepo ?? Container::make(AuthLogRepositoryInterface::class);
    }

    /**
     * GET /admin/logs/auth
     * @throws RandomException
     */
    public function index(): ?ResponseInterface
    {
        $rawStats = $this->authLogRepo->getStats();
        $suspicious = $this->authLogRepo->findSuspiciousActivity(15, 5);

        View::render('admin/logs/auth', [
            'titulo' => 'Logs de Autenticación',
            'csrf_token' => Csrf::token(),
            'stats' => [
                'successful_logins' => (int) ($rawStats['totals']['successful_logins'] ?? 0),
                'failed_attempts' => (int) ($rawStats['totals']['failed_logins'] ?? 0),
                'suspicious_activity' => \count($suspicious),
                'active_today' => 0,
            ],
            'extraJs' => ['admin/admin-logs.js'],
        ], ['admin/admin-logs.css'], 'backoffice');

        return null;
    }
}
