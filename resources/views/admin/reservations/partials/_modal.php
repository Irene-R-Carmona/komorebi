<?php

/**
 * Partial: Modal de detalle de reserva
 * selectedReservation viene de PHP-embedded JSON por fila (ver _table.php)
 */
?>

<div
    class="modal fade"
    id="reservationModal"
    tabindex="-1"
    aria-labelledby="reservationModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <template x-if="selectedReservation">
                <div>
                    <div class="modal-header">
                        <h5 class="modal-title" id="reservationModalLabel">
                            <i class="bi bi-calendar-check me-2"></i>
                            Reserva #<span x-text="selectedReservation.id"></span>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>

                    <div class="modal-body">
                        <div class="reservation-detail">
                            <!-- Estado -->
                            <div class="reservation-detail__section text-center">
                                <span
                                    class="badge-reservation"
                                    :class="'badge-reservation--' + selectedReservation.status"
                                    style="font-size: 1rem; padding: 0.5rem 1rem;"
                                    x-text="getStatusLabel(selectedReservation.status)">
                                </span>
                            </div>

                            <!-- Grid fecha/hora -->
                            <div class="reservation-detail__grid">
                                <div class="reservation-detail__section">
                                    <div class="reservation-detail__label">Fecha</div>
                                    <div class="reservation-detail__value" x-text="selectedReservation.date"></div>
                                </div>
                                <div class="reservation-detail__section">
                                    <div class="reservation-detail__label">Hora</div>
                                    <div class="reservation-detail__value" x-text="selectedReservation.time"></div>
                                </div>
                            </div>

                            <div class="reservation-detail__section">
                                <div class="reservation-detail__label">Cliente</div>
                                <div class="reservation-detail__value reservation-detail__value--large" x-text="selectedReservation.customer_name || 'Invitado'"></div>
                                <div class="text-muted small" x-text="selectedReservation.customer_email"></div>
                            </div>

                            <div class="reservation-detail__section">
                                <div class="reservation-detail__label">Café</div>
                                <div class="reservation-detail__value" x-text="selectedReservation.cafe_name"></div>
                            </div>

                            <div class="reservation-detail__grid">
                                <div class="reservation-detail__section">
                                    <div class="reservation-detail__label">Personas</div>
                                    <div class="reservation-detail__value">
                                        <i class="bi bi-people"></i>
                                        <span x-text="selectedReservation.guest_count || 1"></span>
                                    </div>
                                </div>
                                <div class="reservation-detail__section">
                                    <div class="reservation-detail__label">Creada el</div>
                                    <div class="reservation-detail__value small" x-text="selectedReservation.created_at"></div>
                                </div>
                            </div>

                            <!-- Notas -->
                            <div class="reservation-detail__section" x-show="selectedReservation.notes">
                                <div class="reservation-detail__label">Notas</div>
                                <p class="mb-0" x-text="selectedReservation.notes"></p>
                            </div>

                            <!-- Pre-comanda -->
                            <template x-if="selectedReservation.pre_order_items && selectedReservation.pre_order_items.length > 0">
                                <div class="reservation-detail__section">
                                    <div class="reservation-detail__label">
                                        <i class="bi bi-bag-check me-1"></i>Pre-comanda
                                    </div>
                                    <table class="table table-sm mb-0 mt-2" style="font-size:0.85rem;">
                                        <thead>
                                            <tr>
                                                <th class="fw-semibold text-muted">Producto</th>
                                                <th class="fw-semibold text-muted text-center">Cant.</th>
                                                <th class="fw-semibold text-muted">Categoría</th>
                                                <th class="fw-semibold text-muted text-end">Precio</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <template x-for="item in selectedReservation.pre_order_items" :key="item.id">
                                                <tr>
                                                    <td x-text="item.name"></td>
                                                    <td class="text-center" x-text="item.quantity"></td>
                                                    <td x-text="item.category_name"></td>
                                                    <td class="text-end" x-text="item.price !== null && item.price !== undefined ? (Number(item.price)/100).toFixed(2).replace('.',',') + '\u00a0€' : '—'"></td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                            </template>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                        <button
                            type="button"
                            class="btn btn-danger"
                            x-show="['confirmed', 'pending'].includes(selectedReservation.status)"
                            @click="cancelReservation(selectedReservation.id)">
                            <i class="bi bi-x-lg me-1"></i>Cancelar Reserva
                        </button>
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>
