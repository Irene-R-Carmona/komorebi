<?php
/**
 * Componente: Toast Container
 *
 * Contenedor para notificaciones toast con Alpine.js.
 * Se usa junto con el store Alpine 'toast' definido en admin-common.js.
 *
 * Nota: Este componente es opcional ya que KomorebiToast.init()
 * crea el contenedor automáticamente. Se incluye para casos donde
 * se necesita control declarativo del HTML.
 *
 * @var bool $alpine - Si true, usa Alpine.js para toasts reactivos
 *
 * Uso con JavaScript global:
 * KomorebiToast.success('Operación exitosa');
 * KomorebiToast.error('Algo salió mal');
 *
 * Uso desde Alpine.js:
 * $store.toast.success('Guardado correctamente');
 */

$alpine ??= false;
?>

<?php if ($alpine): ?>
    <!--
        Versión Alpine.js (reactiva)
        Útil cuando se manejan toasts desde componentes Alpine
    -->
    <div class="toast-container-admin"
         x-data="{ toasts: [] }"
         @toast.window="
        const id = Date.now();
        toasts.push({ id, ...$event.detail, visible: true });
        setTimeout(() => {
            const idx = toasts.findIndex(t => t.id === id);
            if (idx > -1) {
                toasts[idx].visible = false;
                setTimeout(() => toasts.splice(idx, 1), 300);
            }
        }, $event.detail.duration || 5000);
     ">

        <template x-for="toast in toasts" :key="toast.id">
            <div class="toast align-items-center border-0"
                 :class="{
                 'text-bg-success': toast.type === 'success',
                 'text-bg-danger': toast.type === 'error',
                 'text-bg-warning': toast.type === 'warning',
                 'text-bg-info': toast.type === 'info',
                 'toast-enter': toast.visible,
                 'toast-exit': !toast.visible
             }"
                 x-show="toast.visible"
                 role="alert">
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="bi me-2"
                           :class="{
                           'bi-check-circle-fill': toast.type === 'success',
                           'bi-exclamation-circle-fill': toast.type === 'error',
                           'bi-exclamation-triangle-fill': toast.type === 'warning',
                           'bi-info-circle-fill': toast.type === 'info'
                       }"></i>
                        <span x-text="toast.message"></span>
                    </div>
                    <button type="button"
                            class="btn-close btn-close-white me-2 m-auto"
                            @click="
                            toast.visible = false;
                            setTimeout(() => {
                                const idx = toasts.findIndex(t => t.id === toast.id);
                                if (idx > -1) toasts.splice(idx, 1);
                            }, 300);
                        "
                            aria-label="Cerrar">
                    </button>
                </div>
            </div>
        </template>
    </div>

<?php else: ?>
    <!--
        Versión estática
        El contenedor real se crea dinámicamente con KomorebiToast.init()
        Este placeholder sirve como documentación
    -->
    <!-- Toast container is managed by KomorebiToast.init() in admin-common.js -->
<?php endif; ?>