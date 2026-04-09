<?php
/**
 * Partial: Modal para rechazar reseña con motivo
 */
?>

<div
    class="modal fade"
    id="rejectModal"
    tabindex="-1"
    aria-labelledby="rejectModalLabel"
    aria-hidden="true"
    data-bs-backdrop="static"
>
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form @submit.prevent="reject">
                <div class="modal-header">
                    <h5 class="modal-title" id="rejectModalLabel">
                        <i class="bi bi-x-circle text-danger me-2"></i>
                        Rechazar Reseña
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <div class="modal-body">
                    <template x-if="selectedReview">
                        <p class="text-muted mb-3">
                            Estás rechazando la reseña de <strong x-text="selectedReview.author"></strong>.
                        </p>
                    </template>

                    <div class="mb-3">
                        <label class="form-label" for="rejectReason">
                            Motivo del rechazo <span class="text-danger">*</span>
                        </label>
                        <textarea
                            id="rejectReason"
                            class="form-control reject-reason__textarea"
                            x-model="rejectReason"
                            placeholder="Explica brevemente por qué rechazas esta reseña (mínimo 5 caracteres)..."
                            minlength="5"
                            maxlength="500"
                            required
                        ></textarea>
                        <div class="reject-reason__help">
                            <span>Entre 5 y 500 caracteres</span>
                            <span
                                class="reject-reason__counter"
                                :class="{
                                    'reject-reason__counter--warning': getReasonLength() > 400,
                                    'reject-reason__counter--error': getReasonLength() > 500
                                }"
                                x-text="getReasonLength() + '/500'"
                            ></span>
                        </div>
                    </div>

                    <div class="alert alert-warning py-2">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <small>El usuario será notificado del rechazo y podrá modificar su reseña.</small>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger" :disabled="processing.includes(selectedReview?.id) || getReasonLength() < 5">
                        <span x-show="!processing.includes(selectedReview?.id)">
                            <i class="bi bi-x-lg me-1"></i>
                            Confirmar Rechazo
                        </span>
                        <span x-show="processing.includes(selectedReview?.id)">
                            <span class="spinner-border spinner-border-sm me-1"></span>
                            Procesando...
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>