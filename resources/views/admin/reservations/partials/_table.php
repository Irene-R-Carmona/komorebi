<?php

/**
 * Partial: Tabla de reservas
 */
?>

<div class="card-admin">
    <div class="table-responsive">
        <table class="table table-admin table-hover align-middle mb-0 reservation-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Cliente</th>
                    <th>Café</th>
                    <th>Fecha</th>
                    <th>Personas</th>
                    <th>Estado</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <template x-for="reservation in filteredReservations" :key="reservation.id">
                    <tr>
                        <!-- ID -->
                        <td>
                            <span class="font-monospace text-muted">#<span x-text="reservation.id"></span></span>
                        </td>

                        <!-- Cliente -->
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="avatar avatar-sm bg-primary bg-opacity-10 text-primary">
                                    <span x-text="(reservation.customer_name || 'U')[0].toUpperCase()"></span>
                                </div>
                                <span x-text="reservation.customer_name || 'Invitado'"></span>
                            </div>
                        </td>

                        <!-- Café -->
                        <td>
                            <div class="reservation-table__cafe">
                                <img
                                    :src="reservation.cafe_image || '/images/ui/placeholder.jpg'"
                                    class="reservation-table__cafe-icon"
                                    @error="$event.target.src='/images/ui/placeholder.jpg'"
                                    alt="">
                                <span x-text="reservation.cafe_name"></span>
                            </div>
                        </td>

                        <!-- Fecha -->
                        <td>
                            <div class="reservation-table__date" x-text="formatDate(reservation.reservation_date)"></div>
                            <div class="reservation-table__time" x-text="reservation.reservation_time"></div>
                        </td>

                        <!-- Personas -->
                        <td>
                            <span class="reservation-table__guests">
                                <i class="bi bi-people text-muted"></i>
                                <span x-text="reservation.guest_count || 1"></span>
                            </span>
                        </td>

                        <!-- Estado -->
                        <td>
                            <span
                                class="badge-reservation"
                                :class="'badge-reservation--' + reservation.status"
                                x-text="getStatusLabel(reservation.status)"></span>
                        </td>

                        <!-- Acciones -->
                        <td class="text-end">
                            <div class="table-actions">
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-success"
                                    @click="confirmReservation(reservation.id)"
                                    x-show="reservation.status === 'pending'"
                                    title="Confirmar reserva">
                                    <i class="bi bi-check-lg"></i>
                                </button>
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-primary"
                                    @click="openModal(reservation)"
                                    title="Ver detalles">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-danger"
                                    @click="cancelReservation(reservation.id)"
                                    x-show="['confirmed', 'pending'].includes(reservation.status)"
                                    title="Cancelar">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                </template>

                <!-- Empty State -->
                <template x-if="filteredReservations.length === 0">
                    <tr>
                        <td colspan="7">
                            <?= \App\Core\View::componentToString('components/admin/empty-state', [
                                'icon' => 'calendar-x',
                                'title' => 'No hay reservas aquí',
                                'message' => 'Ajusta los filtros o espera nuevas reservas',
                                'compact' => true,
                            ]) ?>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>
</div>
