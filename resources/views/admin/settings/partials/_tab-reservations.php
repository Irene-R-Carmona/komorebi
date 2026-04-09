<?php
/**
 * Partial: Tab Reservas - Configuración del sistema de reservas
 */
?>

<div class="settings-section">
    <div class="settings-section__header">
        <h2 class="settings-section__title">
            <i class="bi bi-calendar-check"></i>
            Configuración de Reservas
        </h2>
    </div>
    <div class="settings-section__body">
        <!-- Enable Reservations -->
        <div class="form-check form-switch form-switch-lg mb-4">
            <input
                class="form-check-input"
                type="checkbox"
                role="switch"
                id="reservationsEnabled"
                x-model="settings.reservations_enabled"
            >
            <label class="form-check-label" for="reservationsEnabled">
                <strong>Sistema de Reservas Activo</strong>
                <span class="d-block text-muted small">
                    Permite a los usuarios crear reservas online
                </span>
            </label>
        </div>

        <div class="row g-3" x-show="settings.reservations_enabled" x-transition>
            <!-- Max Advance Days -->
            <div class="col-md-6">
                <label class="form-label" for="maxAdvanceDays">Antelación Máxima</label>
                <div class="range-input">
                    <input
                        type="range"
                        class="form-range range-input__slider"
                        id="maxAdvanceDays"
                        x-model.number="settings.max_advance_days"
                        min="1"
                        max="90"
                    >
                    <div class="range-input__value">
                        <span x-text="settings.max_advance_days"></span>
                        <span class="range-input__unit">días</span>
                    </div>
                </div>
                <p class="form-hint">Días máximos para reservar con antelación</p>
            </div>

            <!-- Min Advance Hours -->
            <div class="col-md-6">
                <label class="form-label" for="minAdvanceHours">Antelación Mínima</label>
                <div class="range-input">
                    <input
                        type="range"
                        class="form-range range-input__slider"
                        id="minAdvanceHours"
                        x-model.number="settings.min_advance_hours"
                        min="0"
                        max="48"
                        step="1"
                    >
                    <div class="range-input__value">
                        <span x-text="settings.min_advance_hours"></span>
                        <span class="range-input__unit">horas</span>
                    </div>
                </div>
                <p class="form-hint">Horas mínimas de antelación requeridas</p>
            </div>

            <!-- Cancellation Hours -->
            <div class="col-md-6">
                <label class="form-label" for="cancellationHours">Cancelación Gratuita</label>
                <div class="range-input">
                    <input
                        type="range"
                        class="form-range range-input__slider"
                        id="cancellationHours"
                        x-model.number="settings.cancellation_hours"
                        min="1"
                        max="72"
                    >
                    <div class="range-input__value">
                        <span x-text="settings.cancellation_hours"></span>
                        <span class="range-input__unit">horas</span>
                    </div>
                </div>
                <p class="form-hint">Tiempo mínimo para cancelar sin penalización</p>
            </div>

            <!-- Max Guests -->
            <div class="col-md-6">
                <label class="form-label" for="maxGuests">Máximo de Personas</label>
                <input
                    type="number"
                    class="form-control"
                    id="maxGuests"
                    x-model.number="settings.max_guests_per_reservation"
                    min="1"
                    max="50"
                >
                <p class="form-hint">Máximo número de personas por reserva</p>
            </div>

            <!-- Duration -->
            <div class="col-md-6">
                <label class="form-label" for="reservationDuration">Duración por Defecto</label>
                <div class="range-input">
                    <input
                        type="range"
                        class="form-range range-input__slider"
                        id="reservationDuration"
                        x-model.number="settings.reservation_duration"
                        min="30"
                        max="240"
                        step="15"
                    >
                    <div class="range-input__value">
                        <span x-text="settings.reservation_duration"></span>
                        <span class="range-input__unit">min</span>
                    </div>
                </div>
            </div>

            <!-- Require Deposit -->
            <div class="col-12">
                <div class="form-check form-switch">
                    <input
                        class="form-check-input"
                        type="checkbox"
                        role="switch"
                        id="requireDeposit"
                        x-model="settings.require_deposit"
                    >
                    <label class="form-check-label" for="requireDeposit">
                        <strong>Requerir Depósito</strong>
                        <span class="d-block text-muted small">
                            Solicitar pago anticipado al hacer reserva
                        </span>
                    </label>
                </div>

                <!-- Deposit Percentage (conditional) -->
                <div
                    class="deposit-section"
                    :class="{ 'deposit-section--visible': settings.require_deposit }"
                >
                    <label class="form-label" for="depositPercentage">
                        Porcentaje de Depósito: <strong x-text="settings.deposit_percentage + '%'"></strong>
                    </label>
                    <input
                        type="range"
                        class="form-range"
                        id="depositPercentage"
                        x-model.number="settings.deposit_percentage"
                        min="10"
                        max="100"
                        step="5"
                    >
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="d-flex justify-content-end gap-2 mt-4">
            <button
                type="button"
                class="btn btn-outline-secondary"
                @click="resetGroup('reservations')"
                :disabled="saving"
            >
                <i class="bi bi-arrow-counterclockwise me-1"></i>
                Restaurar
            </button>
            <button
                type="button"
                class="btn btn-primary"
                @click="saveGroup('reservations')"
                :disabled="saving"
            >
                <span x-show="!saving">
                    <i class="bi bi-save me-1"></i>
                    Guardar Cambios
                </span>
                <span x-show="saving">
                    <span class="spinner-border spinner-border-sm me-1"></span>
                    Guardando...
                </span>
            </button>
        </div>
    </div>
</div>