<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Env;
use App\Core\ExceptionLogger;
use App\Core\Http\ResponseFactory;
use App\Core\View;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use Error;
use Exception;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Random\RandomException;

final class AuditLogController
{
    private AuditLogRepositoryInterface $auditLogRepo;
    private ResponseFactory $response;

    public function __construct(
        ?AuditLogRepositoryInterface $auditLogRepo = null,
        ?ResponseFactory $response = null
    ) {
        $this->auditLogRepo = $auditLogRepo ?? Container::make(AuditLogRepositoryInterface::class);
        $this->response = $response ?? new ResponseFactory();
    }

    /**
     * GET /admin/logs/audit
     * @throws JsonException
     * @throws RandomException
     */
    public function index(): ?ResponseInterface
    {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && \strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            return $this->getAuditLogsData();
        }

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

    /**
     * GET /admin/logs/audit (AJAX)
     * @throws JsonException
     */
    private function getAuditLogsData(): ResponseInterface
    {
        $filters = \array_filter([
            'user_id' => !empty($_GET['user_id']) ? (int) $_GET['user_id'] : null,
            'action' => $_GET['action'] ?? null,
            'resource_type' => $_GET['resource_type'] ?? null,
            'date_from' => $_GET['date_from'] ?? null,
            'date_to' => $_GET['date_to'] ?? null,
            'ip_address' => $_GET['ip_address'] ?? null,
        ], static fn ($v) => $v !== null);

        $page = \max(1, (int) ($_GET['page'] ?? 1));
        $limit = \max(10, \min(100, (int) ($_GET['limit'] ?? 50)));
        $offset = ($page - 1) * $limit;

        $result = $this->auditLogRepo->findAll($filters, $limit, $offset);

        return $this->response->json(['ok' => true, 'data' => [
            'logs' => $result['data'],
            'total' => $result['total'],
        ]]);
    }

    /**
     * GET /admin/logs/audit/stats
     * @throws JsonException
     */
    public function stats(): ResponseInterface
    {
        $filters = \array_filter([
            'date_from' => $_GET['date_from'] ?? null,
            'date_to' => $_GET['date_to'] ?? null,
        ], static fn ($v) => $v !== null && $v !== '');

        $stats = $this->auditLogRepo->getStats($filters);

        return $this->response->json(['ok' => true, 'data' => ['stats' => $stats]]);
    }

    /**
     * GET /admin/logs/audit/export
     */
    public function export(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $queryParams = $request->getQueryParams();
            $filters = \array_filter([
                'user_id' => !empty($queryParams['user_id']) ? (int) $queryParams['user_id'] : null,
                'action' => $queryParams['action'] ?? null,
                'resource_type' => $queryParams['resource_type'] ?? null,
                'date_from' => $queryParams['date_from'] ?? null,
                'date_to' => $queryParams['date_to'] ?? null,
                'ip_address' => $queryParams['ip_address'] ?? null,
            ], static fn ($v) => $v !== null && $v !== '');

            $result = $this->auditLogRepo->findAll($filters, 10000, 0);

            $tmp = \fopen('php://temp', 'rw+');
            \fprintf($tmp, \chr(0xEF) . \chr(0xBB) . \chr(0xBF));
            \fputcsv($tmp, ['ID', 'Timestamp', 'Usuario', 'Acción', 'Tipo Recurso', 'ID Recurso', 'IP', 'User Agent'], ',', '"');

            foreach ($result['data'] as $log) {
                \fputcsv($tmp, [
                    $log['id'],
                    $log['created_at'],
                    $log['user_name'] ?? 'Sistema',
                    $log['action'],
                    $log['resource_type'],
                    $log['resource_id'],
                    $log['ip_address'],
                    $log['user_agent'],
                ], ',', '"');
            }

            \rewind($tmp);
            $csvContent = \stream_get_contents($tmp);
            \fclose($tmp);

            $response = $this->response->createResponse(200)
                ->withHeader('Content-Type', 'text/csv; charset=utf-8')
                ->withHeader('Content-Disposition', 'attachment; filename="audit_logs_' . \date('Y-m-d_His') . '.csv"');
            $response->getBody()->write((string) $csvContent);

            return $response;
        } catch (Exception | Error $e) {
            ExceptionLogger::log($e, 'Admin\\AuditLogController::export');
            $isDebug = Env::get('APP_DEBUG', '') ?: (Env::get('APP_ENV', '') !== 'production');
            $response = $this->response->createResponse(500);
            View::render('errors/500', [
                'message' => $isDebug ? $e->getMessage() : 'Error al generar el archivo de exportación',
                'show_details' => $isDebug,
            ]);

            return $response;
        }
    }
}
