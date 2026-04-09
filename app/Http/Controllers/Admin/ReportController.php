<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Env;
use App\Core\ExceptionLogger;
use App\Core\Http\ResponseFactory;
use App\Core\View;
use App\Exceptions\ValidationException;
use App\Models\AuditLog;
use App\Services\AdminService;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Random\RandomException;

/**
 * Controlador de Reportes y Estadísticas
 *
 * Responsabilidad única: Generación y exportación de reportes del sistema
 */
final class ReportController
{
    private AdminService $adminService;
    private ResponseFactory $response;

    public function __construct(?AdminService $adminService = null, ?ResponseFactory $response = null)
    {
        $this->adminService = $adminService ?? new AdminService();
        $this->response = $response ?? new ResponseFactory();
    }

    /**
     * GET /admin/reportes
     * Vista principal de reportes
     * @throws RandomException
     * @throws JsonException
     */
    public function index(): ?ResponseInterface
    {
        $isAjax = (
            (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && \strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
            (!empty($_SERVER['HTTP_ACCEPT']) && \str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))
        );

        if ($isAjax) {
            return $this->getReportesData();
        }

        View::render('admin/reportes', [
            'titulo' => 'Reportes y Estadísticas',
            'csrf_token' => Csrf::token(),
            'stats' => [
                'total_users' => 0,
                'monthly_reservations' => 0,
                'total_reviews' => 0,
                'monthly_revenue' => 0,
            ],
            'extraJs' => ['admin/admin-reports.js'],
        ], ['admin/admin-reports.css'], 'backoffice');

        return null;
    }

    /**
     * GET /admin/reportes/data (AJAX)
     * Obtiene datos de reportes para el período especificado
     * @throws JsonException
     */
    private function getReportesData(): ResponseInterface
    {
        $dateFrom = $_GET['date_from'] ?? \date('Y-m-d', \strtotime('-30 days'));
        $dateTo = $_GET['date_to'] ?? \date('Y-m-d');

        $summary = $this->adminService->getReportsSummary($dateFrom, $dateTo);
        $reservationTrend = $this->adminService->getReservationTrendStats($dateFrom, $dateTo);
        $cafeTypes = $this->adminService->getReservationsByCafeType($dateFrom, $dateTo);
        $cafePerformance = $this->adminService->getCafePerformanceStats($dateFrom, $dateTo, 10);
        $userRoles = $this->adminService->getUserDistributionByRole();
        $topCafes = $this->adminService->getTopCafes($dateFrom, $dateTo, 10);

        return $this->response->json(['ok' => true, 'data' => [
            'summary' => $summary,
            'reservation_trend' => $reservationTrend,
            'cafe_types' => $cafeTypes,
            'cafe_performance' => $cafePerformance,
            'user_roles' => $userRoles,
            'top_cafes' => $topCafes,
        ]]);
    }

    /**
     * GET /admin/reportes/export
     * Exporta datos de reportes en CSV
     */
    public function exportReportes(): void
    {
        try {
            $dateFrom = $_GET['date_from'] ?? \date('Y-m-d', \strtotime('-30 days'));
            $dateTo = $_GET['date_to'] ?? \date('Y-m-d');
            $format = $_GET['format'] ?? 'csv';

            if ($format !== 'csv') {
                throw ValidationException::withMessage('Formato no soportado', 400);
            }

            $topCafes = $this->adminService->getTopCafes($dateFrom, $dateTo, 50);
            $cafePerformance = $this->adminService->getCafePerformanceStats($dateFrom, $dateTo, 50);
            $summary = $this->adminService->getReportsSummary($dateFrom, $dateTo);

            // Configurar headers para descarga
            \header('Content-Type: text/csv; charset=utf-8');
            \header('Content-Disposition: attachment; filename="reporte_' . \date('Y-m-d') . '.csv"');
            \header('Pragma: no-cache');
            \header('Expires: 0');

            $output = \fopen('php://output', 'wb');

            if ($output === false) {
                throw ValidationException::withMessage('No se pudo abrir el stream de salida', 500);
            }

            /** @var resource $output */

            // BOM para UTF-8
            \fwrite($output, "\xEF\xBB\xBF");

            // Sección: Resumen
            \fputcsv($output, ['RESUMEN DEL PERÍODO'], ',', '"');
            \fputcsv($output, ['Fecha Desde', $dateFrom], ',', '"');
            \fputcsv($output, ['Fecha Hasta', $dateTo], ',', '"');
            \fputcsv($output, ['Total Reservas', $summary['total_reservations']], ',', '"');
            \fputcsv($output, ['Total Invitados', $summary['total_guests']], ',', '"');
            \fputcsv($output, ['Rating Promedio', $summary['avg_rating']], ',', '"');
            \fputcsv($output, ['Usuarios Activos', $summary['active_users']], ',', '"');
            \fputcsv($output, []);

            // Sección: Top Cafés
            \fputcsv($output, ['TOP CAFÉS'], ',', '"');
            \fputcsv($output, ['#', 'Nombre', 'Tipo', 'Ubicación', 'Reservas', 'Invitados', 'Rating', 'Reviews'], ',', '"');

            foreach ($topCafes as $index => $cafe) {
                \fputcsv($output, [
                    $index + 1,
                    $cafe['name'],
                    $cafe['type'],
                    $cafe['location'],
                    $cafe['total_reservations'],
                    $cafe['total_guests'],
                    $cafe['avg_rating'],
                    $cafe['review_count'],
                ], ',', '"');
            }

            \fputcsv($output, [], ',', '"');

            // Sección: Rendimiento por Café
            \fputcsv($output, ['RENDIMIENTO POR CAFÉS'], ',', '"');
            \fputcsv($output, ['Café', 'Tipo', 'Reservas', 'Invitados', 'Completadas', 'Canceladas', 'Tasa Completitud %'], ',', '"');

            foreach ($cafePerformance as $cafe) {
                \fputcsv($output, [
                    $cafe['name'],
                    $cafe['type'],
                    $cafe['total_reservations'],
                    $cafe['total_guests'],
                    $cafe['completed'],
                    $cafe['cancelled'],
                    $cafe['completion_rate'],
                ], ',', '"');
            }

            \fclose($output);

            AuditLog::log(
                'export_reports',
                'report',
                null,
                null,
                [
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                    'format' => $format,
                ]
            );

            exit;
        } catch (\Exception $e) {
            ExceptionLogger::log($e, 'Admin\\ReportController::exportReportes');
            $isDebug = Env::get('APP_DEBUG', '') ?: (Env::get('APP_ENV', '') !== 'production');
            @\http_response_code(500);
            View::render('errors/500', [
                'message' => $isDebug ? $e->getMessage() : 'Error al generar el reporte',
                'show_details' => $isDebug,
            ]);
            exit;
        } catch (\Error $e) {
            ExceptionLogger::log($e, 'Admin\\ReportController::exportReportes');
            $isDebug = Env::get('APP_DEBUG', '') ?: (Env::get('APP_ENV', '') !== 'production');
            @\http_response_code(500);
            View::render('errors/500', [
                'message' => $isDebug ? $e->getMessage() : 'Error al generar el reporte',
                'show_details' => $isDebug,
            ]);
            exit;
        }
    }
}
