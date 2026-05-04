<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Env;
use App\Core\ExceptionLogger;
use App\Core\Http\ResponseFactory;
use App\Core\Raw;
use App\Core\View;
use App\Exceptions\ValidationException;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use App\Services\Contracts\AdminReportServiceInterface;
use App\Services\Contracts\AdminStatisticsServiceInterface;
use Error;
use Exception;
use JsonException;
use tFPDF;
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

        $chartDateFrom = \date('Y-m-d', \strtotime('-30 days'));
        $chartDateTo = \date('Y-m-d');
        $trendResult = $this->statisticsService->getReservationTrendStats($chartDateFrom, $chartDateTo);
        $trendData = $trendResult->ok ? $trendResult->data : [];
        $cafeResult = $this->statisticsService->getCafePerformanceStats($chartDateFrom, $chartDateTo, 10);
        $cafeData = $cafeResult->ok ? $cafeResult->data : [];

        View::render('admin/reportes', [
            'titulo' => 'Reportes y Estadísticas',
            'csrf_token' => Csrf::token(),
            'stats' => [
                'total_users' => $systemStats['users'] ?? 0,
                'monthly_reservations' => $monthlyStats['reservations'] ?? 0,
                'total_reviews' => $systemStats['reviews'] ?? 0,
                'monthly_revenue' => 0,
            ],
            'chartData' => Raw::json(['trend' => $trendData]),
            'cafeData' => Raw::json($cafeData),
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

            if ($format !== 'csv' && $format !== 'pdf') {
                throw ValidationException::withMessage('Formato no soportado', 400);
            }

            $topCafesResult = $this->statisticsService->getTopCafes($dateFrom, $dateTo, 50);
            $topCafes = $topCafesResult->ok ? $topCafesResult->data : [];
            $cafePerformanceResult = $this->statisticsService->getCafePerformanceStats($dateFrom, $dateTo, 50);
            $cafePerformance = $cafePerformanceResult->ok ? $cafePerformanceResult->data : [];
            $summaryResult = $this->reportService->getReportsSummary($dateFrom, $dateTo);
            $summary = $summaryResult->ok ? $summaryResult->data : [];

            $this->auditLogRepo->log(
                'export_reports',
                'report',
                null,
                null,
                ['date_from' => $dateFrom, 'date_to' => $dateTo, 'format' => $format]
            );

            if ($format === 'pdf') {
                $pdf = new tFPDF('P', 'mm', 'A4');
                $pdf->AddPage();
                $pdf->AddFont('DejaVu', '', 'DejaVuSans.ttf', true);
                $pdf->AddFont('DejaVu', 'B', 'DejaVuSans-Bold.ttf', true);

                // Cabecera
                $pdf->SetFont('DejaVu', 'B', 18);
                $pdf->Cell(0, 12, 'KOMOREBI CAFE', 0, 1, 'C');
                $pdf->SetFont('DejaVu', '', 11);
                $pdf->Cell(0, 6, 'Reporte de Estadísticas', 0, 1, 'C');
                $pdf->SetFont('DejaVu', '', 9);
                $pdf->Cell(0, 5, 'Período: ' . $dateFrom . ' – ' . $dateTo, 0, 1, 'C');
                $pdf->Ln(3);
                $pdf->SetDrawColor(180, 180, 180);
                $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
                $pdf->Ln(5);

                // Resumen
                $pdf->SetFont('DejaVu', 'B', 12);
                $pdf->Cell(0, 7, 'Resumen del período', 0, 1);
                $pdf->SetFont('DejaVu', '', 10);
                foreach (
                    [
                        'Total Reservas'  => (string) ($summary['total_reservations'] ?? 0),
                        'Total Invitados' => (string) ($summary['total_guests'] ?? 0),
                        'Rating Promedio' => (string) ($summary['avg_rating'] ?? 0),
                        'Usuarios Activos' => (string) ($summary['active_users'] ?? 0),
                    ] as $label => $value
                ) {
                    $pdf->Cell(80, 6, $label . ':', 0, 0);
                    $pdf->Cell(0, 6, $value, 0, 1);
                }
                $pdf->Ln(5);

                // Top Cafés
                $pdf->SetFont('DejaVu', 'B', 12);
                $pdf->Cell(0, 7, 'Top Cafés', 0, 1);
                $pdf->SetFont('DejaVu', 'B', 8);
                $pdf->SetFillColor(60, 60, 60);
                $pdf->SetTextColor(255, 255, 255);
                $pdf->Cell(8, 6, '#', 1, 0, 'C', true);
                $pdf->Cell(52, 6, 'Nombre', 1, 0, 'L', true);
                $pdf->Cell(28, 6, 'Tipo', 1, 0, 'L', true);
                $pdf->Cell(24, 6, 'Ubicación', 1, 0, 'L', true);
                $pdf->Cell(20, 6, 'Reservas', 1, 0, 'C', true);
                $pdf->Cell(20, 6, 'Invitados', 1, 0, 'C', true);
                $pdf->Cell(16, 6, 'Rating', 1, 0, 'C', true);
                $pdf->Cell(12, 6, 'Rev.', 1, 1, 'C', true);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetFont('DejaVu', '', 8);
                foreach ($topCafes as $i => $cafe) {
                    $fill = ($i % 2 === 0);
                    $pdf->SetFillColor(245, 245, 245);
                    $pdf->Cell(8, 5, (string) ($i + 1), 1, 0, 'C', $fill);
                    $pdf->Cell(52, 5, (string) ($cafe['name'] ?? ''), 1, 0, 'L', $fill);
                    $pdf->Cell(28, 5, (string) ($cafe['type'] ?? ''), 1, 0, 'L', $fill);
                    $pdf->Cell(24, 5, (string) ($cafe['location'] ?? ''), 1, 0, 'L', $fill);
                    $pdf->Cell(20, 5, (string) ($cafe['total_reservations'] ?? 0), 1, 0, 'C', $fill);
                    $pdf->Cell(20, 5, (string) ($cafe['total_guests'] ?? 0), 1, 0, 'C', $fill);
                    $pdf->Cell(16, 5, (string) ($cafe['avg_rating'] ?? 0), 1, 0, 'C', $fill);
                    $pdf->Cell(12, 5, (string) ($cafe['review_count'] ?? 0), 1, 1, 'C', $fill);
                }
                $pdf->Ln(5);

                // Rendimiento por Café
                if ($pdf->GetY() > 220) {
                    $pdf->AddPage();
                }
                $pdf->SetFont('DejaVu', 'B', 12);
                $pdf->Cell(0, 7, 'Rendimiento por Café', 0, 1);
                $pdf->SetFont('DejaVu', 'B', 8);
                $pdf->SetFillColor(60, 60, 60);
                $pdf->SetTextColor(255, 255, 255);
                $pdf->Cell(52, 6, 'Café', 1, 0, 'L', true);
                $pdf->Cell(28, 6, 'Tipo', 1, 0, 'L', true);
                $pdf->Cell(22, 6, 'Reservas', 1, 0, 'C', true);
                $pdf->Cell(22, 6, 'Invitados', 1, 0, 'C', true);
                $pdf->Cell(22, 6, 'Completas', 1, 0, 'C', true);
                $pdf->Cell(20, 6, 'Canceladas', 1, 0, 'C', true);
                $pdf->Cell(14, 6, '% Comp.', 1, 1, 'C', true);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetFont('DejaVu', '', 8);
                foreach ($cafePerformance as $i => $cafe) {
                    $fill = ($i % 2 === 0);
                    $pdf->SetFillColor(245, 245, 245);
                    $pdf->Cell(52, 5, (string) ($cafe['name'] ?? ''), 1, 0, 'L', $fill);
                    $pdf->Cell(28, 5, (string) ($cafe['type'] ?? ''), 1, 0, 'L', $fill);
                    $pdf->Cell(22, 5, (string) ($cafe['total_reservations'] ?? 0), 1, 0, 'C', $fill);
                    $pdf->Cell(22, 5, (string) ($cafe['total_guests'] ?? 0), 1, 0, 'C', $fill);
                    $pdf->Cell(22, 5, (string) ($cafe['completed'] ?? 0), 1, 0, 'C', $fill);
                    $pdf->Cell(20, 5, (string) ($cafe['cancelled'] ?? 0), 1, 0, 'C', $fill);
                    $pdf->Cell(14, 5, (string) ($cafe['completion_rate'] ?? 0), 1, 1, 'C', $fill);
                }

                $pdfContent = $pdf->Output('S');
                $pdfResponse = $this->response->createResponse(200)
                    ->withHeader('Content-Type', 'application/pdf')
                    ->withHeader('Content-Disposition', 'attachment; filename="reporte_' . \date('Y-m-d') . '.pdf"')
                    ->withHeader('Content-Length', (string) \strlen($pdfContent))
                    ->withHeader('Pragma', 'no-cache')
                    ->withHeader('Expires', '0');
                $pdfResponse->getBody()->write($pdfContent);
                return $pdfResponse;
            }

            // Formato CSV
            $tmp = \fopen('php://temp', 'rw+');

            if ($tmp === false) {
                throw ValidationException::withMessage('No se pudo abrir el stream temporal', 500);
            }

            /** @var resource $tmp */

            // BOM para UTF-8
            \fwrite($tmp, "\xEF\xBB\xBF");

            // Sección: Resumen
            \fputcsv($tmp, ['RESUMEN DEL PERÍODO'], ',', '"', '\\');
            \fputcsv($tmp, ['Fecha Desde', $dateFrom], ',', '"', '\\');
            \fputcsv($tmp, ['Fecha Hasta', $dateTo], ',', '"', '\\');
            \fputcsv($tmp, ['Total Reservas', $summary['total_reservations']], ',', '"', '\\');
            \fputcsv($tmp, ['Total Invitados', $summary['total_guests']], ',', '"', '\\');
            \fputcsv($tmp, ['Rating Promedio', $summary['avg_rating']], ',', '"', '\\');
            \fputcsv($tmp, ['Usuarios Activos', $summary['active_users']], ',', '"', '\\');
            \fputcsv($tmp, [], ',', '"', '\\');

            // Sección: Top Cafés
            \fputcsv($tmp, ['TOP CAFÉS'], ',', '"', '\\');
            \fputcsv($tmp, ['#', 'Nombre', 'Tipo', 'Ubicación', 'Reservas', 'Invitados', 'Rating', 'Reviews'], ',', '"', '\\');

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
                ], ',', '"', '\\');
            }

            \fputcsv($tmp, [], ',', '"', '\\');

            // Sección: Rendimiento por Café
            \fputcsv($tmp, ['RENDIMIENTO POR CAFÉS'], ',', '"', '\\');
            \fputcsv($tmp, ['Café', 'Tipo', 'Reservas', 'Invitados', 'Completadas', 'Canceladas', 'Tasa Completitud %'], ',', '"', '\\');

            foreach ($cafePerformance as $cafe) {
                \fputcsv($tmp, [
                    $cafe['name'],
                    $cafe['type'],
                    $cafe['total_reservations'],
                    $cafe['total_guests'],
                    $cafe['completed'],
                    $cafe['cancelled'],
                    $cafe['completion_rate'],
                ], ',', '"', '\\');
            }

            \rewind($tmp);
            $csvContent = \stream_get_contents($tmp);
            \fclose($tmp);

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
            ], [], 'errors');

            return $response;
        } catch (Error $e) {
            ExceptionLogger::log($e, 'Admin\\ReportController::exportReportes');
            $isDebug = Env::get('APP_DEBUG', '') ?: (Env::get('APP_ENV', '') !== 'production');
            $response = $this->response->createResponse(500);
            View::render('errors/500', [
                'message' => $isDebug ? $e->getMessage() : 'Error al generar el reporte',
                'show_details' => $isDebug,
            ], [], 'errors');

            return $response;
        }
    }
}
