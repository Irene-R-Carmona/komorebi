<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\View;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use Psr\Http\Message\ResponseInterface;
use Random\RandomException;

/**
 * Controlador de Logs de Auditoría (Admin) — SSR únicamente.
 * Las consultas JSON paginadas y exportación CSV están en Api\V1\Admin\LogApiController.
 */
final class AuditLogController
{
    private AuditLogRepositoryInterface $auditLogRepo;

    public function __construct(?AuditLogRepositoryInterface $auditLogRepo = null)
    {
        $this->auditLogRepo = $auditLogRepo ?? Container::make(AuditLogRepositoryInterface::class);
    }

    /**
     * GET /admin/logs/audit
     * @throws RandomException
     */
    public function index(): ?ResponseInterface
    {
        $rawStats = $this->auditLogRepo->getStats();
        View::render('admin/logs/audit', [
            'titulo' => 'Logs de Auditoría',
            'csrf_token' => Csrf::token(),
            'stats' => [
                'total_logs' => (int) ($rawStats['totals']['total_actions'] ?? 0),
                'last_24h' => (int) ($rawStats['totals']['last_24h'] ?? 0),
                'critical_actions' => (int) ($rawStats['totals']['critical_actions'] ?? 0),
                'active_users' => (int) ($rawStats['totals']['unique_users'] ?? 0),
            ],
            'extraJs' => ['admin/admin-logs.js'],
        ], ['admin/admin-logs.css'], 'backoffice');

        return null;
    }
}
