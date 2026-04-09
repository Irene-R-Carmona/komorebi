<?php

declare(strict_types=1);

namespace App\Services\Contracts;

interface InvoicePDFServiceInterface
{
    /**
     * Generar factura PDF de una reserva con datos completos
     *
     * @param array<string, mixed> $reservation Datos de la reserva con detalles del café
     * @param array<string, mixed> $user Datos del usuario
     * @return string Ruta al PDF generado
     */
    public function generateReservationInvoice(array $reservation, array $user): string;
}
