// Registrar la fábrica centralizada `window.detalleCafe` con Alpine.
document.addEventListener('alpine:init', () => {
  try {
    if (globalThis.detalleCafe) {
      Alpine.data('detalleCafe', globalThis.detalleCafe);
    } else {
      console.warn('detalleCafe no encontrada en window; verificar que /js/components/detalleCafe.js se cargue antes.');
    }
  } catch (e) {
    console.warn('detalleCafe registration failed:', e);
  }
});
