/**
 * Inicialización de Alpine.js para el backoffice
 *
 * Asegura que Alpine.js se inicie solo una vez y maneja
 * componentes dinámicos en el backoffice.
 */
document.addEventListener('DOMContentLoaded', () => {
  if (!globalThis.Alpine) {
    return;
  }

  const hasInitializedComponent = Boolean(document.querySelector('[x-data]')?._x_dataStack);
  if (hasInitializedComponent) {
    return;
  }

  if (!globalThis.Alpine.__komorebiStarted) {
    globalThis.Alpine.__komorebiStarted = true;
    globalThis.Alpine.start();
  }
});
