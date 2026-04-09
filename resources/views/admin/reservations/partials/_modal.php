<?php
/**
 * Partial: Modal de detalle de reserva
 */
?>

<div
    class="modal fade"
    id="reservationModal"
    tabindex="-1"
    aria-labelledby="reservationModalLabel"
    aria-hidden="true"
>
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
                                    x-text="getStatusLabel(selectedReservation.status)"
                                ></span>
                            </div>

                            <!-- Grid de detalles -->
                            <div class="reservation-detail__grid">
                                <div class="reservation-detail__section">
                                    <div class="reservation-detail__label">Fecha</div>
                                    <div class="reservation-detail__value" x-text="formatDate(selectedReservation.reservation_date)"></div>
                                </div>

                                <div class="reservation-detail__section">
                                    <div class="reservation-detail__label">Hora</div>
                                    <div class="reservation-detail__value" x-text="selectedReservation.reservation_time"></div>
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
                                    <div class="reservation-detail__value small" x-text="formatDate(selectedReservation.created_at)"></div>
                                </div>
                            </div>

                            <!-- Notas -->
                            <div class="reservation-detail__section" x-show="selectedReservation.notes">
                                <div class="reservation-detail__label">Notas</div>
                                <p class="mb-0" x-text="selectedReservation.notes"></p>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                        <button
                            type="button"
                            class="btn btn-danger"
                            x-show="['confirmed', 'pending'].includes(selectedReservation.status)"
                            @click="cancelReservation(selectedReservation.id); $refs.closeBtn.click()"
                        >
                            <i class="bi bi-x-lg me-1"></i>
                            Cancelar Reserva
                        </button>
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>