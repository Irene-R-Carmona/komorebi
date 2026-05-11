<?php

/**
 * Componente: Modal de Confirmación de Eliminación
 * Uso: <?= View::componentToString('components/admin/delete-confirmation-modal') ?>
 */
?>

<!-- Delete Confirmation Modal -->
<div x-data="window.deleteModal"
    x-show="isOpen"
    x-cloak
    class="modal fade"
    :class="{'show': isOpen, 'd-block': isOpen}"
    tabindex="-1"
    role="dialog"
    aria-modal="true"
    aria-labelledby="deleteModalTitle"
    @keydown.escape.window="if (isOpen && !isDeleting) close()"
    style="background-color: var(--overlay-backdrop, rgba(0,0,0,0.5));">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content" x-trap="isOpen">
            <div class="modal-header modal-header--danger">
                <h5 id="deleteModalTitle" class="modal-title">
                    <i class="bi bi-exclamation-triangle-fill me-2" aria-hidden="true"></i>
                    <span x-text="title">Confirmar eliminación</span>
                </h5>
                <button type="button"
                    class="btn-close btn-close-white"
                    @click="close()"
                    :disabled="isDeleting"
                    aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning d-flex align-items-start" role="alert">
                    <i class="bi bi-exclamation-circle-fill me-2 fs-4"></i>
                    <div>
                        <p class="mb-2 fw-bold" x-text="message"></p>
                        <p class="mb-0 text-muted" x-show="itemName">
                            Elemento: <strong class="text-dark" x-text="itemName"></strong>
                        </p>
                    </div>
                </div>

                <div class="form-check">
                    <input class="form-check-input"
                        type="checkbox"
                        id="confirmDeleteCheckbox"
                        :disabled="isDeleting">
                    <label class="form-check-label" for="confirmDeleteCheckbox">
                        Entiendo que esta acción es irreversible
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button"
                    class="btn btn-secondary"
                    @click="close()"
                    :disabled="isDeleting">
                    <i class="bi bi-x-circle me-1"></i>
                    Cancelar
                </button>
                <button type="button"
                    class="btn btn-danger"
                    @click="confirmDelete()"
                    :disabled="isDeleting || !document.getElementById('confirmDeleteCheckbox')?.checked">
                    <span x-show="!isDeleting">
                        <i class="bi bi-trash me-1"></i>
                        Eliminar definitivamente
                    </span>
                    <span x-show="isDeleting">
                        <span class="spinner-border spinner-border-sm me-1" role="status"></span>
                        Eliminando...
                    </span>
                </button>
            </div>
        </div>
    </div>
</div>
