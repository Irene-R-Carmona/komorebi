<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Env;
use App\Core\ExceptionLogger;
use App\Core\Http\ResponseFactory;
use App\Core\View;
use App\Models\AuditLog;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Random\RandomException;

/**
 * Controlador de Logs de Auditoría
 *
 * Responsabilidad única: Visualización y exportación de logs de auditoría
 *
 * Métodos:
 * - index() - Vista principal de logs
 * - getAuditLogsData() - API lista logs con filtros
 * - stats() - Estadísticas de auditoría
 * - export() - Exportar a CSV
 */
final class AuditLogController
{
    private ResponseFactory $response;

    public function __construct(?ResponseFactory $response = null)
    {
        $this->response = $response ?? new ResponseFactory();
    }
    /**
     * GET /admin/logs/audit
     * Vista de logs de auditoría
     * @throws JsonException
     * @throws RandomException
     */
    public function index(): ?ResponseInterface
    {
        // Si es petición AJAX, devolver datos JSON
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && \strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            return $this->getAuditLogsData();
        }

        // Renderizar vista
        $auditLogModel = new AuditLog();
        $rawStats = $auditLogModel->getStats();
        View::render('admin/logs/audit', [
            'titulo' => 'Logs de Auditoría',
            'csrf_token' => Csrf::token(),
            'stats' => [
                'total_logs'       => (int) ($rawStats['totals']['total_actions'] ?? 0),
                'last_24h'         => (int) ($rawStats['totals']['last_24h'] ?? 0),
                'critical_actions' => (int) ($rawStats['totals']['critical_actions'] ?? 0),
                'active_users'     => (int) ($rawStats['totals']['unique_users'] ?? 0),
            ],
            'extraJs' => ['admin/admin-logs.js'],
        ], ['admin/admin-logs.css'], 'backoffice');

        return null;
    }

    /**
     * GET /admin/logs/audit (AJAX)
     * Obtener datos de logs de auditoría con filtros
     * @throws JsonException
     */
    private function getAuditLogsData(): ResponseInterface
    {
        $filters = [
            'user_id' => !empty($_GET['user_id']) ? (int) $_GET['user_id'] : null,
            'action' => $_GET['action'] ?? null,
            'resource_type' => $_GET['resource_type'] ?? null,
            'date_from' => $_GET['date_from'] ?? null,
            'date_to' => $_GET['date_to'] ?? null,
            'ip_address' => $_GET['ip_address'] ?? null,
        ];

        // Remover valores nulos
        $filters = \array_filter($filters, static fn($v) => $v !== null);

        $page = \max(1, (int) ($_GET['page'] ?? 1));
        $limit = \max(10, \min(100, (int) ($_GET['limit'] ?? 50)));
        $offset = ($page - 1) * $limit;

        $auditLogModel = new AuditLog();
        $result = $auditLogModel->findAll($filters, $limit, $offset);

        return $this->response->json(['ok' => true, 'data' => [
            'logs' => $result['data'],
            'total' => $result['total'],
        ]]);
    }

    /**
     * GET /admin/logs/audit/stats
     * Obtener estadísticas de auditoría
     * @throws JsonException
     */
    public function stats(): ResponseInterface
    {
        $filters = [
            'date_from' => $_GET['date_from'] ?? null,
            'date_to' => $_GET['date_to'] ?? null,
        ];

        $filters = \array_filter($filters, static fn($v) => $v !== null && $v !== '');

        $auditLogModel = new AuditLog();
        $stats = $auditLogModel->getStats($filters);

        return $this->response->json(['ok' => true, 'data' => ['stats' => $stats]]);
    }

    /**
     * GET /admin/logs/audit/export
     * Exportar logs de auditoría a CSV
     */
    public function export(): void
    {
        try {
            $filters = [
                'user_id' => !empty($_GET['user_id']) ? (int) $_GET['user_id'] : null,
                'action' => $_GET['action'] ?? null,
                'resource_type' => $_GET['resource_type'] ?? null,
                'date_from' => $_GET['date_from'] ?? null,
                'date_to' => $_GET['date_to'] ?? null,
                'ip_address' => $_GET['ip_address'] ?? null,
            ];

            $filters = \array_filter($filters, static fn($v) => $v !== null && $v !== '');

            $auditLogModel = new AuditLog();
            $result = $auditLogModel->findAll($filters, 10000, 0);

            // Headers para descarga CSV
            \header('Content-Type: text/csv; charset=utf-8');
            \header('Content-Disposition: attachment; filename="audit_logs_' . \date('Y-m-d_His') . '.csv"');

            $output = \fopen('php://output', 'wb');

            // BOM para UTF-8
            \fprintf($output, \chr(0xEF) . \chr(0xBB) . \chr(0xBF));

            // Encabezados
            \fputcsv($output, ['ID', 'Timestamp', 'Usuario', 'Acción', 'Tipo Recurso', 'ID Recurso', 'IP', 'User Agent'], ',', '"');

            // Datos
            foreach ($result['data'] as $log) {
                \fputcsv($output, [
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

            \fclose($output);
            exit;
        } catch (\Exception $e) {
            ExceptionLogger::log($e, 'Admin\\AuditLogController::export');
            $isDebug = Env::get('APP_DEBUG', '') ?: (Env::get('APP_ENV', '') !== 'production');
            @\http_response_code(500);
            View::render('errors/500', [
                'message' => $isDebug ? $e->getMessage() : 'Error al generar el archivo de exportación',
                'show_details' => $isDebug,
            ]);
            exit;
        } catch (\Error $e) {
            ExceptionLogger::log($e, 'Admin\\AuditLogController::export');
            $isDebug = Env::get('APP_DEBUG', '') ?: (Env::get('APP_ENV', '') !== 'production');
            @\http_response_code(500);
            View::render('errors/500', [
                'message' => $isDebug ? $e->getMessage() : 'Error al generar el archivo de exportación',
                'show_details' => $isDebug,
            ]);
            exit;
        }
    }
}
