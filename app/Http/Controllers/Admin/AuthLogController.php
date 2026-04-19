<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\ExceptionLogger;
use App\Core\Http\ResponseFactory;
use App\Core\View;
use App\Repositories\Contracts\AuthLogRepositoryInterface;
use Error;
use Exception;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Random\RandomException;

/**
 * Controlador de Logs de Autenticación
 *
 * Responsabilidad única: Visualización y análisis de logs de autenticación
 *
 * Métodos:
 * - index() - Vista principal de logs
 * - getAuthLogsData() - API lista logs con filtros
 * - stats() - Estadísticas de autenticación
 * - suspicious() - Detectar actividad sospechosa
 * - export() - Exportar a CSV
 */
final class AuthLogController
{
    private ResponseFactory $response;
    private AuthLogRepositoryInterface $authLogRepo;

    public function __construct(?ResponseFactory $response = null, ?AuthLogRepositoryInterface $authLogRepo = null)
    {
        $this->response = $response ?? new ResponseFactory();
        $this->authLogRepo = $authLogRepo ?? Container::make(AuthLogRepositoryInterface::class);
    }

    /**
     * GET /admin/logs/auth
     * Vista de logs de autenticación
     * @throws JsonException
     * @throws RandomException
     */
    public function index(): ?ResponseInterface
    {
        // Si es petición AJAX, devolver datos JSON
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && \strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            return $this->getAuthLogsData();
        }

        // Renderizar vista
        View::render('admin/logs/auth', [
            'titulo' => 'Logs de Autenticación',
            'csrf_token' => Csrf::token(),
            'stats' => [
                'successful_logins' => 0,
                'failed_attempts' => 0,
                'suspicious_activity' => 0,
                'active_today' => 0,
            ],
            'extraJs' => ['admin/admin-logs.js'],
        ], ['admin/admin-logs.css'], 'backoffice');

        return null;
    }

    /**
     * GET /admin/logs/auth (AJAX)
     * Obtener datos de logs de autenticación con filtros
     * @throws JsonException
     */
    private function getAuthLogsData(): ResponseInterface
    {
        $filters = [
            'user_id' => !empty($_GET['user_id']) ? (int) $_GET['user_id'] : null,
            'event_type' => $_GET['event_type'] ?? null,
            'success' => isset($_GET['success']) ? (bool) $_GET['success'] : null,
            'date_from' => $_GET['date_from'] ?? null,
            'date_to' => $_GET['date_to'] ?? null,
            'ip_address' => $_GET['ip_address'] ?? null,
        ];

        // Remover valores nulos
        $filters = \array_filter($filters, static fn ($v) => $v !== null);

        $page = \max(1, (int) ($_GET['page'] ?? 1));
        $limit = \max(10, \min(100, (int) ($_GET['limit'] ?? 50)));
        $offset = ($page - 1) * $limit;

        $result = $this->authLogRepo->findFiltered($filters, $limit, $offset);

        return $this->response->json(['ok' => true, 'data' => [
            'logs' => $result['data'],
            'total' => $result['total'],
        ]]);
    }

    /**
     * GET /admin/logs/auth/stats
     * Obtener estadísticas de autenticación
     * @throws JsonException
     */
    public function stats(): ResponseInterface
    {
        $filters = [
            'date_from' => $_GET['date_from'] ?? null,
            'date_to' => $_GET['date_to'] ?? null,
        ];

        $filters = \array_filter($filters, static fn ($v) => $v !== null && $v !== '');

        $stats = $this->authLogRepo->getStats($filters);

        return $this->response->json(['ok' => true, 'data' => ['stats' => $stats]]);
    }

    /**
     * GET /admin/logs/auth/suspicious
     * Detectar actividad sospechosa (múltiples fallos de login)
     * @throws JsonException
     */
    public function suspicious(): ResponseInterface
    {
        $suspicious = $this->authLogRepo->findSuspiciousActivity(15, 5);

        return $this->response->json(['ok' => true, 'data' => ['suspicious' => $suspicious]]);
    }

    /**
     * GET /admin/logs/auth/suspicious-count
     * Número de IPs con actividad sospechosa (para badge en el panel)
     * @throws JsonException
     */
    public function suspiciousCount(): ResponseInterface
    {
        $suspicious = $this->authLogRepo->findSuspiciousActivity(15, 5);

        return $this->response->json(['ok' => true, 'count' => \count($suspicious)]);
    }

    /**
     * POST /admin/security/block-ip
     * Stub — funcionalidad de bloqueo IP pendiente de implementar
     * @throws JsonException
     */
    public function blockIpStub(): ResponseInterface
    {
        return $this->response->json(
            ['ok' => false, 'message' => 'El bloqueo de IP no está disponible todavía.'],
            501
        );
    }

    /**
     * Helper: escribir una fila CSV en el handler dado (quita el escape deprecated)
     *
     * @param false|resource $output
     */
    private function writeCsvLine($output, array $row): void
    {
        // Usar comilla doble como enclosure, dejar escape por defecto
        \fputcsv($output, $row, ',', '"');
    }

    /**
     * GET /admin/logs/auth/export
     * Exportar logs de autenticación a CSV
     */
    public function export(): ResponseInterface
    {
        try {
            $filters = [
                'user_id' => !empty($_GET['user_id']) ? (int) $_GET['user_id'] : null,
                'event_type' => $_GET['event_type'] ?? null,
                'success' => isset($_GET['success']) ? (bool) $_GET['success'] : null,
                'date_from' => $_GET['date_from'] ?? null,
                'date_to' => $_GET['date_to'] ?? null,
                'ip_address' => $_GET['ip_address'] ?? null,
            ];

            $filters = \array_filter($filters, static fn ($v) => $v !== null && $v !== '');

            $result = $this->authLogRepo->findFiltered($filters, 10000, 0);

            $output = \fopen('php://memory', 'wb');

            // BOM para UTF-8
            \fprintf($output, \chr(0xEF) . \chr(0xBB) . \chr(0xBF));

            // Encabezados
            $this->writeCsvLine($output, ['ID', 'Timestamp', 'Usuario', 'Evento', 'Dispositivo', 'IP', 'Éxito', 'Razón']);

            // Datos
            foreach ($result['data'] as $log) {
                $this->writeCsvLine($output, [
                    $log['id'],
                    $log['created_at'],
                    $log['user_name'] ?? 'N/A',
                    $log['event_type'],
                    $log['device_name'] ?? 'Desconocido',
                    $log['ip_address'],
                    $log['success'] ? 'Sí' : 'No',
                    $log['reason'] ?? '-',
                ]);
            }

            \rewind($output);
            $csv = (string) \stream_get_contents($output);
            \fclose($output);

            $filename = 'auth_logs_' . \date('Y-m-d_His') . '.csv';

            return $this->response->html($csv, 200)
                ->withHeader('Content-Type', 'text/csv; charset=utf-8')
                ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"');
        } catch (Exception $e) {
            ExceptionLogger::log($e, 'Admin\\AuthLogController::export');
            throw $e;
        } catch (Error $e) {
            ExceptionLogger::log($e, 'Admin\\AuthLogController::export');
            throw $e;
        }
    }
}
