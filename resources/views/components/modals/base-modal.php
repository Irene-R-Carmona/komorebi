<?php

/**
 * Componente: Modal Base Reutilizable
 *
 * Componente Alpine.js reutilizable para modales Bootstrap 5.
 * Se puede extender para crear modales específicos.
 *
 * @param string $modalId - ID único del modal
 * @param string $modalTitle - Título del modal
 * @param string $modalSize - Tamaño del modal (sm, lg, xl, full)
 * @param bool $closeButton - Mostrar botón cerrar
 * @param string $contentFile - Archivo PHP con el contenido del modal
 * @param array $contentData - Datos para pasar al contenido
 */

$modalId ??= 'baseModal';
$modalTitle ??= 'Modal';
$modalSize ??= ''; // '', 'sm', 'lg', 'xl', 'fullscreen'
$closeButton ??= true;
$contentFile ??= null;
$contentData ??= [];
$showFooter ??= true;
$footerContent ??= null;

$modalDialogClass = $modalSize ? "modal-dialog-{$modalSize}" : '';
?>

<!-- Modal -->
<div class="modal fade"
    id="<?= $modalId ?>"
    tabindex="-1"
    aria-labelledby="<?= $modalId ?>Label"
    aria-hidden="true"
    data-bs-backdrop="static"
    data-bs-keyboard="false">
    <div class="modal-dialog <?= $modalDialogClass ?> modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <!-- Header -->
            <div class="modal-header">
                <h5 class="modal-title" id="<?= $modalId ?>Label">
                    <?= $modalTitle ?>
                </h5>
                <?php if ($closeButton): ?>
                    <button type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Cerrar"></button>
                <?php endif; ?>
            </div>

            <!-- Body -->
            <div class="modal-body">
                <?php if ($contentFile): ?>
                    <?php extract($contentData); ?>
                    <?php include $contentFile; ?>
                <?php else: ?>
                    <!-- Contenido dinámico será insertado aquí -->
                    <div id="<?= $modalId ?>-content">
                        <!-- Content placeholder -->
                    </div>
                <?php endif; ?>
            </div>

            <!-- Footer -->
            <?php if ($showFooter): ?>
                <div class="modal-footer">
                    <?php if ($footerContent): ?>
                        <?= $footerContent ?>
                    <?php else: ?>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            Cancelar
                        </button>
                        <button type="button" class="btn btn-primary" id="<?= $modalId ?>-submit">
                            Guardar
                        </button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
