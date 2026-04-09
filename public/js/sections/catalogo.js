// Registrar la fábrica centralizada `window.catalogoApp` con Alpine.
document.addEventListener('alpine:init', () => {
  try {
    if (globalThis.catalogoApp) {
      Alpine.data('catalogoApp', globalThis.catalogoApp);
    } else {
      console.warn('catalogoApp no encontrada en window; verificar que /js/components/catalogo.js se cargue antes.');
    }
  } catch (e) {
    console.warn('catalogoApp registration failed:', e);
  }
});
