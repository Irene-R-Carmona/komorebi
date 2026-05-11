<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Core\Http\ResponseFactory;
use App\Http\Controllers\Api\AbstractApiController;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use App\Repositories\Contracts\AuthLogRepositoryInterface;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * API REST: Logs de auditoría y autenticación (Admin)
 *
 * Rutas:
 * - GET /api/v1/admin/logs/audit            → auditLogs()
 * - GET /api/v1/admin/logs/audit/stats      → auditStats()
 * - GET /api/v1/admin/logs/audit/export     → auditExport()
 * - GET /api/v1/admin/logs/auth             → authLogs()
 * - GET /api/v1/admin/logs/auth/stats       → authStats()
 * - GET /api/v1/admin/logs/auth/suspicious  → authSuspicious()
 * - GET /api/v1/admin/logs/auth/suspicious-count → authSuspiciousCount()
 * - GET /api/v1/admin/logs/auth/export      → authExport()
 * - POST /api/v1/admin/security/block-ip    → blockIp()
 */
final class LogApiController extends AbstractApiController
{
    public function __construct(
        ResponseFactory $response,
        private readonly AuditLogRepositoryInterface $auditLogRepo,
        private readonly AuthLogRepositoryInterface $authLogRepo,
    ) {
        parent::__construct($response);
    }

    // ─── Audit logs ──────────────────────────────────────────────────────────

    /**
     * GET /api/v1/admin/logs/audit → 200
     *
     * @throws JsonException
     */
    public function auditLogs(ServerRequestInterface $request): ResponseInterface
    {
        $q = $request->getQueryParams();
        $page = \max(1, (int) ($q['page'] ?? 1));
        $limit = \max(10, \min(100, (int) ($q['perPage'] ?? $q['limit'] ?? 50)));
        $offset = ($page - 1) * $limit;

        $filters = \array_filter([
            'user_id' => !empty($q['user_id']) ? (int) $q['user_id'] : null,
            'action' => $q['action'] ?? null,
            'resource_type' => $q['resource_type'] ?? null,
            'date_from' => $q['date_from'] ?? null,
            'date_to' => $q['date_to'] ?? null,
            'ip_address' => $q['ip_address'] ?? null,
        ], static fn ($v) => $v !== null);

        $result = $this->auditLogRepo->findFiltered($filters, $limit, $offset);

        return $this->success([
            'logs' => $result['data'],
            'total' => $result['total'],
        ]);
    }

    /**
     * GET /api/v1/admin/logs/audit/stats → 200
     *
     * @throws JsonException
     */
    public function auditStats(ServerRequestInterface $request): ResponseInterface
    {
        $q = $request->getQueryParams();

        $filters = \array_filter([
            'date_from' => $q['date_from'] ?? null,
            'date_to' => $q['date_to'] ?? null,
        ], static fn ($v) => $v !== null && $v !== '');

        $stats = $this->auditLogRepo->getStats($filters);

        return $this->success(['stats' => $stats]);
    }

    /**
     * GET /api/v1/admin/logs/audit/export → CSV download
     */
    public function auditExport(ServerRequestInterface $request): ResponseInterface
    {
        $q = $request->getQueryParams();

        $filters = \array_filter([
            'user_id' => !empty($q['user_id']) ? (int) $q['user_id'] : null,
            'action' => $q['action'] ?? null,
            'resource_type' => $q['resource_type'] ?? null,
            'date_from' => $q['date_from'] ?? null,
            'date_to' => $q['date_to'] ?? null,
            'ip_address' => $q['ip_address'] ?? null,
        ], static fn ($v) => $v !== null && $v !== '');

        $result = $this->auditLogRepo->findFiltered($filters, 10000, 0);

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
        $csv = (string) \stream_get_contents($tmp);
        \fclose($tmp);

        $response = $this->response->createResponse(200)
            ->withHeader('Content-Type', 'text/csv; charset=utf-8')
            ->withHeader('Content-Disposition', 'attachment; filename="audit_logs_' . \date('Y-m-d_His') . '.csv"');
        $response->getBody()->write($csv);

        return $response;
    }

    // ─── Auth logs ───────────────────────────────────────────────────────────

    /**
     * GET /api/v1/admin/logs/auth → 200
     *
     * @throws JsonException
     */
    public function authLogs(ServerRequestInterface $request): ResponseInterface
    {
        $q = $request->getQueryParams();
        $page = \max(1, (int) ($q['page'] ?? 1));
        $limit = \max(10, \min(100, (int) ($q['perPage'] ?? $q['limit'] ?? 50)));
        $offset = ($page - 1) * $limit;

        $filters = \array_filter([
            'user_id' => !empty($q['user_id']) ? (int) $q['user_id'] : null,
            'event_type' => $q['event_type'] ?? null,
            'success' => isset($q['success']) ? (bool) $q['success'] : null,
            'date_from' => $q['date_from'] ?? null,
            'date_to' => $q['date_to'] ?? null,
            'ip_address' => $q['ip_address'] ?? null,
        ], static fn ($v) => $v !== null);

        $result = $this->authLogRepo->findFiltered($filters, $limit, $offset);

        return $this->success([
            'logs' => $result['data'],
            'total' => $result['total'],
        ]);
    }

    /**
     * GET /api/v1/admin/logs/auth/stats → 200
     *
     * @throws JsonException
     */
    public function authStats(ServerRequestInterface $request): ResponseInterface
    {
        $q = $request->getQueryParams();

        $filters = \array_filter([
            'date_from' => $q['date_from'] ?? null,
            'date_to' => $q['date_to'] ?? null,
        ], static fn ($v) => $v !== null && $v !== '');

        $stats = $this->authLogRepo->getStats($filters);

        return $this->success(['stats' => $stats]);
    }

    /**
     * GET /api/v1/admin/logs/auth/suspicious → 200
     *
     * @throws JsonException
     */
    public function authSuspicious(): ResponseInterface
    {
        $suspicious = $this->authLogRepo->findSuspiciousActivity(15, 5);

        return $this->success(['suspicious' => $suspicious]);
    }

    /**
     * GET /api/v1/admin/logs/auth/suspicious-count → 200
     *
     * @throws JsonException
     */
    public function authSuspiciousCount(): ResponseInterface
    {
        $suspicious = $this->authLogRepo->findSuspiciousActivity(15, 5);

        return $this->success(['count' => \count($suspicious)]);
    }

    /**
     * GET /api/v1/admin/logs/auth/export → CSV download
     */
    public function authExport(ServerRequestInterface $request): ResponseInterface
    {
        $q = $request->getQueryParams();

        $filters = \array_filter([
            'user_id' => !empty($q['user_id']) ? (int) $q['user_id'] : null,
            'event_type' => $q['event_type'] ?? null,
            'success' => isset($q['success']) ? (bool) $q['success'] : null,
            'date_from' => $q['date_from'] ?? null,
            'date_to' => $q['date_to'] ?? null,
            'ip_address' => $q['ip_address'] ?? null,
        ], static fn ($v) => $v !== null && $v !== '');

        $result = $this->authLogRepo->findFiltered($filters, 10000, 0);

        $output = \fopen('php://memory', 'wb');
        \fprintf($output, \chr(0xEF) . \chr(0xBB) . \chr(0xBF));
        \fputcsv($output, ['ID', 'Timestamp', 'Usuario', 'Evento', 'Dispositivo', 'IP', 'Éxito', 'Razón'], ',', '"');

        foreach ($result['data'] as $log) {
            \fputcsv($output, [
                $log['id'],
                $log['created_at'],
                $log['user_name'] ?? $log['user_id'] ?? 'Anónimo',
                $log['event_type'],
                $log['device_type'] ?? '',
                $log['ip_address'],
                $log['success'] ? 'Sí' : 'No',
                $log['failure_reason'] ?? '',
            ], ',', '"');
        }

        \rewind($output);
        $csv = (string) \stream_get_contents($output);
        \fclose($output);

        $response = $this->response->createResponse(200)
            ->withHeader('Content-Type', 'text/csv; charset=utf-8')
            ->withHeader('Content-Disposition', 'attachment; filename="auth_logs_' . \date('Y-m-d_His') . '.csv"');
        $response->getBody()->write($csv);

        return $response;
    }

    /**
     * POST /api/v1/admin/security/block-ip → 501
     *
     * @throws JsonException
     */
    public function blockIp(): ResponseInterface
    {
        return $this->response->json(
            ['ok' => false, 'message' => 'El bloqueo de IP no está disponible todavía.'],
            501
        );
    }
}
