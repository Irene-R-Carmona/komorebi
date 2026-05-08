<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Env;
use App\Services\Contracts\InvoicePDFServiceInterface;
use tFPDF;
use Throwable;

/**
 * Servicio para generar billetes de reserva en PDF
 *
 * Genera documentos profesionales con toda la información necesaria
 * para la visita del cliente al café de animales.
 */
final class InvoicePDFService implements InvoicePDFServiceInterface
{
    private static function invoiceDir(): string
    {
        return Env::get('STORAGE_PATH', '/app/storage') . '/invoices/';
    }

    /**
     * Genera un PDF de comprobante de reserva
     *
     * @param array $reservation Datos completos de la reserva
     * @param array $user Datos del usuario
     * @return string Ruta al archivo PDF generado
     */
    public function generateReservationInvoice(array $reservation, array $user): string
    {
        // Crear directorio si no existe
        if (!\is_dir(self::invoiceDir())) {
            \mkdir(self::invoiceDir(), 0o755, true);
        }

        // Inicializar tFPDF con soporte UTF-8
        $pdf = new tFPDF();
        $pdf->AddPage();
        $pdf->AddFont('DejaVu', '', 'DejaVuSans.ttf', true);
        $pdf->AddFont('DejaVu', 'B', 'DejaVuSans-Bold.ttf', true);
        $pdf->SetFont('DejaVu', '', 10);

        // Código de reserva
        $reservationCode = 'RES-' . \str_pad((string) $reservation['id'], 6, '0', STR_PAD_LEFT);

        // Generar QR code
        $qrPath = $this->generateQRCode($reservationCode);

        // ===== HEADER =====
        $pdf->SetFont('DejaVu', 'B', 20);
        $pdf->Cell(0, 12, 'KOMOREBI CAFE', 0, 1, 'C');

        $pdf->SetFont('DejaVu', '', 10);
        $pdf->Cell(0, 6, 'Cafe de Animales - Madrid, España', 0, 1, 'C');
        $pdf->Ln(3);

        // Banner de confirmación
        $pdf->SetFillColor(34, 139, 34); // Verde
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('DejaVu', 'B', 14);
        $pdf->Cell(0, 10, 'RESERVA CONFIRMADA', 0, 1, 'C', true);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Ln(5);

        // ===== COLUMNA IZQUIERDA: QR + Código =====
        $pdf->SetX(15);

        // QR Code (si existe)
        if (\file_exists($qrPath)) {
            $pdf->Image($qrPath, 15, $pdf->GetY(), 40, 40);
        }

        $pdf->SetXY(15, $pdf->GetY() + 42);
        $pdf->SetFont('DejaVu', 'B', 12);
        $pdf->Cell(40, 6, $reservationCode, 0, 1, 'C');
        $pdf->SetX(15);
        $pdf->SetFont('DejaVu', '', 8);
        $pdf->Cell(40, 4, 'Codigo de reserva', 0, 1, 'C');

        // ===== COLUMNA DERECHA: Detalles =====
        $rightColumnX = 65;
        $currentY = 58; // Alineado con QR

        $pdf->SetXY($rightColumnX, $currentY);
        $pdf->SetFont('DejaVu', 'B', 11);
        $pdf->Cell(0, 6, 'Detalles de tu visita');
        $currentY += 8;

        // Datos de la reserva
        $this->addDetailRow($pdf, $rightColumnX, $currentY, 'Cafe:', $reservation['cafe_name'] ?? 'No especificado');
        $currentY += 6;

        $this->addDetailRow(
            $pdf,
            $rightColumnX,
            $currentY,
            'Fecha:',
            \date('d/m/Y', \strtotime($reservation['reservation_date'] ?? 'now'))
        );
        $currentY += 6;

        $this->addDetailRow(
            $pdf,
            $rightColumnX,
            $currentY,
            'Hora:',
            \substr($reservation['reservation_time'] ?? '00:00:00', 0, 5)
        );
        $currentY += 6;

        $this->addDetailRow(
            $pdf,
            $rightColumnX,
            $currentY,
            'Duracion:',
            ($reservation['pass_duration_minutes'] ?? 60) . ' minutos'
        );
        $currentY += 6;

        $this->addDetailRow(
            $pdf,
            $rightColumnX,
            $currentY,
            'Personas:',
            (string) ($reservation['guest_count'] ?? 1)
        );
        $currentY += 6;

        $this->addDetailRow(
            $pdf,
            $rightColumnX,
            $currentY,
            'Pase:',
            $reservation['pass_name'] ?? 'Pase estandar'
        );

        // Línea separadora
        $pdf->SetY($pdf->GetY() + 10);
        $pdf->SetDrawColor(200, 200, 200);
        $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
        $pdf->Ln(8);

        // ===== DATOS DEL CLIENTE =====
        $pdf->SetFont('DejaVu', 'B', 11);
        $pdf->Cell(0, 6, 'Titular de la reserva');
        $pdf->Ln(6);

        $pdf->SetFont('DejaVu', '', 10);
        $pdf->Cell(0, 5, $user['name']);
        $pdf->Ln(5);
        $pdf->Cell(0, 5, $user['email']);
        $pdf->Ln(8);

        // ===== INFORMACION DEL CAFE =====
        $pdf->SetFont('DejaVu', 'B', 11);
        $pdf->Cell(0, 6, 'Informacion del establecimiento');
        $pdf->Ln(6);

        $pdf->SetFont('DejaVu', '', 9);

        // Dirección (si está disponible)
        if (!empty($reservation['cafe_location'])) {
            $pdf->Cell(30, 5, 'Ubicacion:', 0, 0);
            $pdf->SetFont('DejaVu', 'B', 9);
            $pdf->Cell(0, 5, $reservation['cafe_location']);
            $pdf->SetFont('DejaVu', '', 9);
            $pdf->Ln(5);
        }

        // Horario
        if (!empty($reservation['opening_time']) && !empty($reservation['closing_time'])) {
            $pdf->Cell(30, 5, 'Horario:', 0, 0);
            $openTime = \substr($reservation['opening_time'], 0, 5);
            $closeTime = \substr($reservation['closing_time'], 0, 5);
            $pdf->SetFont('DejaVu', 'B', 9);
            $pdf->Cell(0, 5, "$openTime - $closeTime");
            $pdf->SetFont('DejaVu', '', 9);
            $pdf->Ln(5);
        }

        // Contacto
        $pdf->Cell(30, 5, 'Web:', 0, 0);
        $pdf->SetFont('DejaVu', 'B', 9);
        $pdf->Cell(0, 5, 'www.komorebi.cafe');
        $pdf->SetFont('DejaVu', '', 9);
        $pdf->Ln(5);

        $pdf->Cell(30, 5, 'Email:', 0, 0);
        $pdf->SetFont('DejaVu', 'B', 9);
        $pdf->Cell(0, 5, 'info@komorebi.cafe');
        $pdf->SetFont('DejaVu', '', 9);
        $pdf->Ln(10);

        // ===== INSTRUCCIONES =====
        $pdf->SetFont('DejaVu', 'B', 11);
        $pdf->Cell(0, 6, 'Instrucciones importantes');
        $pdf->Ln(6);

        $pdf->SetFont('DejaVu', '', 9);
        $instructions = [
            'Por favor, llegue 10 minutos antes de su hora reservada.',
            'Muestre este codigo QR en recepcion para confirmar su entrada.',
            'Use ropa comoda y calcetines limpios (sin zapatos en area de animales).',
            'No utilice flash al fotografiar a los animales.',
            'Siga las indicaciones del personal en todo momento.',
        ];

        foreach ($instructions as $instruction) {
            $pdf->Cell(5, 5, '-', 0, 0);
            $pdf->MultiCell(0, 5, $instruction);
        }

        $pdf->Ln(5);

        // ===== POLÍTICA DE CANCELACIÓN =====
        $pdf->SetFont('DejaVu', 'B', 10);
        $pdf->Cell(0, 6, 'Politica de cancelacion');
        $pdf->Ln(5);

        $pdf->SetFont('DejaVu', '', 8);
        $pdf->MultiCell(
            0,
            4,
            'Puede cancelar o modificar su reserva sin coste hasta 24 horas antes de la ' .
                'fecha programada. Para cancelaciones tardias, contacte con nosotros.'
        );
        $pdf->Ln(5);

        // ===== FOOTER =====
        $pdf->SetY(-25);
        $pdf->SetDrawColor(200, 200, 200);
        $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
        $pdf->Ln(3);

        $pdf->SetFont('DejaVu', '', 8);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->Cell(0, 4, 'Documento generado el ' . \date('d/m/Y H:i'), 0, 1, 'C');
        $pdf->Cell(0, 4, 'Komorebi Cafe - Todos los derechos reservados', 0, 1, 'C');

        // Generar nombre de archivo
        $filename = \sprintf(
            'reserva_%s_%s.pdf',
            $reservationCode,
            \date('Ymd_His')
        );
        $filePath = self::invoiceDir() . $filename;

        // Guardar PDF
        $pdf->Output('F', $filePath);

        // Limpiar QR temporal
        if (\file_exists($qrPath)) {
            @\unlink($qrPath);
        }

        return $filePath;
    }

    /**
     * Añade una fila de detalle (label + valor)
     */
    private function addDetailRow(tFPDF $pdf, float $x, float $y, string $label, string $value): void
    {
        $pdf->SetXY($x, $y);
        $pdf->SetFont('DejaVu', '', 9);
        $pdf->Cell(25, 5, $label, 0, 0);

        $pdf->SetFont('DejaVu', 'B', 9);
        $pdf->Cell(0, 5, $value);
    }

    /**
     * Genera un código QR para check-in
     */
    private function generateQRCode(string $code): string
    {
        $qrPath = \sys_get_temp_dir() . '/qr_' . $code . '.png';

        try {
            $options = new \chillerlan\QRCode\QROptions([
                'version' => 5,
                'outputType' => \chillerlan\QRCode\QRCode::OUTPUT_IMAGE_PNG,
                'eccLevel' => \chillerlan\QRCode\QRCode::ECC_L,
                'scale' => 10,
                'imageBase64' => false,
            ]);

            $qrcode = new \chillerlan\QRCode\QRCode($options);
            $qrcode->render($code, $qrPath);

            return $qrPath;
        } catch (Throwable $e) {
            // Si falla QR, devolver string vacío
            return '';
        }
    }

    /**
     * Elimina facturas antiguas (más de 30 días)
     */
    public function cleanOldInvoices(): int
    {
        if (!\is_dir(self::invoiceDir())) {
            return 0;
        }

        $deleted = 0;
        $files = \glob(self::invoiceDir() . '*.pdf');
        $cutoff = \time() - (30 * 24 * 60 * 60); // 30 días

        foreach ($files as $file) {
            if (\is_file($file) && \filemtime($file) < $cutoff) {
                \unlink($file);
                $deleted++;
            }
        }

        return $deleted;
    }
}
