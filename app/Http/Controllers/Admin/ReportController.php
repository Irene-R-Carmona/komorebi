<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Env;
use App\Core\ExceptionLogger;
use App\Core\Http\ResponseFactory;
use App\Core\View;
use App\Exceptions\ValidationException;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use App\Services\Contracts\AdminReportServiceInterface;
use App\Services\Contracts\AdminStatisticsServiceInterface;
use Error;
use Exception;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Random\RandomException;

/**
 * Controlador de Reportes y Estadísticas
 *
 * Responsabilidad única: Generación y exportación de reportes del sistema
 */
final class ReportController
{
    private AdminStatisticsServiceInterface $statisticsService;
    private AdminReportServiceInterface $reportService;
    private AuditLogRepositoryInterface $auditLogRepo;
    private ResponseFactory $response;

    public function __construct(
        ?AdminStatisticsServiceInterface $statisticsService = null,
        ?AdminReportServiceInterface $reportService = null,
        ?AuditLogRepositoryInterface $auditLogRepo = null,
        ?ResponseFactory $response = null
    ) {
        $this->statisticsService = $statisticsService ?? Container::make(AdminStatisticsServiceInterface::class);
        $this->reportService = $reportService ?? Container::make(AdminReportServiceInterface::class);
        $this->auditLogRepo = $auditLogRepo ?? Container::make(AuditLogRepositoryInterface::class);
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

        $statsResult = $this->statisticsService->getSystemStatistics();
        $systemStats = $statsResult->ok ? $statsResult->data : [];
        $monthlyResult = $this->statisticsService->getMonthlyStats((int) \date('n'), (int) \date('Y'));
        $monthlyStats = $monthlyResult->ok ? $monthlyResult->data : [];

        View::render('admin/reportes', [
            'titulo' => 'Reportes y Estadísticas',
            'csrf_token' => Csrf::token(),
            'stats' => [
                'total_users' => $systemStats['users'] ?? 0,
                'monthly_reservations' => $monthlyStats['reservations'] ?? 0,
                'total_reviews' => $systemStats['reviews'] ?? 0,
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

        $summaryResult = $this->reportService->getReportsSummary($dateFrom, $dateTo);
        $summary = $summaryResult->ok ? $summaryResult->data : [];
        $trendResult = $this->statisticsService->getReservationTrendStats($dateFrom, $dateTo);
        $reservationTrend = $trendResult->ok ? $trendResult->data : [];
        $cafeTypesResult = $this->statisticsService->getReservationsByCafeType($dateFrom, $dateTo);
        $cafeTypes = $cafeTypesResult->ok ? $cafeTypesResult->data : [];
        $cafePerformanceResult = $this->statisticsService->getCafePerformanceStats($dateFrom, $dateTo, 10);
        $cafePerformance = $cafePerformanceResult->ok ? $cafePerformanceResult->data : [];
        $userRolesResult = $this->statisticsService->getUserDistributionByRole();
        $userRoles = $userRolesResult->ok ? $userRolesResult->data : [];
        $topCafesResult = $this->statisticsService->getTopCafes($dateFrom, $dateTo, 10);
        $topCafes = $topCafesResult->ok ? $topCafesResult->data : [];

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
    public function exportReportes(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $queryParams = $request->getQueryParams();
            $dateFrom = $queryParams['date_from'] ?? \date('Y-m-d', \strtotime('-30 days'));
            $dateTo = $queryParams['date_to'] ?? \date('Y-m-d');
            $format = $queryParams['format'] ?? 'csv';

            if ($format !== 'csv') {
                throw ValidationException::withMessage('Formato no soportado', 400);
            }

            $topCafesResult = $this->statisticsService->getTopCafes($dateFrom, $dateTo, 50);
            $topCafes = $topCafesResult->ok ? $topCafesResult->data : [];
            $cafePerformanceResult = $this->statisticsService->getCafePerformanceStats($dateFrom, $dateTo, 50);
            $cafePerformance = $cafePerformanceResult->ok ? $cafePerformanceResult->data : [];
            $summaryResult = $this->reportService->getReportsSummary($dateFrom, $dateTo);
            $summary = $summaryResult->ok ? $summaryResult->data : [];

            $tmp = \fopen('php://temp', 'rw+');

            if ($tmp === false) {
                throw ValidationException::withMessage('No se pudo abrir el stream temporal', 500);
            }

            /** @var resource $tmp */

            // BOM para UTF-8
            \fwrite($tmp, "\xEF\xBB\xBF");

            // Sección: Resumen
            \fputcsv($tmp, ['RESUMEN DEL PERÍODO'], ',', '"');
            \fputcsv($tmp, ['Fecha Desde', $dateFrom], ',', '"');
            \fputcsv($tmp, ['Fecha Hasta', $dateTo], ',', '"');
            \fputcsv($tmp, ['Total Reservas', $summary['total_reservations']], ',', '"');
            \fputcsv($tmp, ['Total Invitados', $summary['total_guests']], ',', '"');
            \fputcsv($tmp, ['Rating Promedio', $summary['avg_rating']], ',', '"');
            \fputcsv($tmp, ['Usuarios Activos', $summary['active_users']], ',', '"');
            \fputcsv($tmp, []);

            // Sección: Top Cafés
            \fputcsv($tmp, ['TOP CAFÉS'], ',', '"');
            \fputcsv($tmp, ['#', 'Nombre', 'Tipo', 'Ubicación', 'Reservas', 'Invitados', 'Rating', 'Reviews'], ',', '"');

            foreach ($topCafes as $index => $cafe) {
                \fputcsv($tmp, [
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

            \fputcsv($tmp, [], ',', '"');

            // Sección: Rendimiento por Café
            \fputcsv($tmp, ['RENDIMIENTO POR CAFÉS'], ',', '"');
            \fputcsv($tmp, ['Café', 'Tipo', 'Reservas', 'Invitados', 'Completadas', 'Canceladas', 'Tasa Completitud %'], ',', '"');

            foreach ($cafePerformance as $cafe) {
                \fputcsv($tmp, [
                    $cafe['name'],
                    $cafe['type'],
                    $cafe['total_reservations'],
                    $cafe['total_guests'],
                    $cafe['completed'],
                    $cafe['cancelled'],
                    $cafe['completion_rate'],
                ], ',', '"');
            }

            \rewind($tmp);
            $csvContent = \stream_get_contents($tmp);
            \fclose($tmp);

            $this->auditLogRepo->log(
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

            $response = $this->response->createResponse(200)
                ->withHeader('Content-Type', 'text/csv; charset=utf-8')
                ->withHeader('Content-Disposition', 'attachment; filename="reporte_' . \date('Y-m-d') . '.csv"')
                ->withHeader('Pragma', 'no-cache')
                ->withHeader('Expires', '0');
            $response->getBody()->write((string) $csvContent);

            return $response;
        } catch (Exception $e) {
            ExceptionLogger::log($e, 'Admin\\ReportController::exportReportes');
            $isDebug = Env::get('APP_DEBUG', '') ?: (Env::get('APP_ENV', '') !== 'production');
            $response = $this->response->createResponse(500);
            View::render('errors/500', [
                'message' => $isDebug ? $e->getMessage() : 'Error al generar el reporte',
                'show_details' => $isDebug,
            ]);

            return $response;
        } catch (Error $e) {
            ExceptionLogger::log($e, 'Admin\\ReportController::exportReportes');
            $isDebug = Env::get('APP_DEBUG', '') ?: (Env::get('APP_ENV', '') !== 'production');
            $response = $this->response->createResponse(500);
            View::render('errors/500', [
                'message' => $isDebug ? $e->getMessage() : 'Error al generar el reporte',
                'show_details' => $isDebug,
            ]);

            return $response;
        }
    }
}
