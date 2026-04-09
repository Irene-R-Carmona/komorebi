// Componente Alpine loadingState externo y helper para lazy-image
(function () {
  // Lazy image loaded helper
  document.addEventListener('DOMContentLoaded', () => {
    try {
      const lazyImages = document.querySelectorAll('img[loading="lazy"]');
      lazyImages.forEach(img => {
        if (img.complete) img.classList.add('loaded');
        else img.addEventListener('load', () => img.classList.add('loaded'));
      });
    } catch (e) { /* ignore */ }
  });

  document.addEventListener('alpine:init', () => {
    Alpine.data('loadingState', () => ({
      loading: false,
      loadingMessage: '',

      async withLoading(callback, message = 'Cargando...') {
        this.loading = true; this.loadingMessage = message;
        try { await callback(); } finally { this.loading = false; this.loadingMessage = ''; }
      },

      startLoading(message = 'Cargando...') { this.loading = true; this.loadingMessage = message; },
      stopLoading() { this.loading = false; this.loadingMessage = ''; }
    }));
  });
})();
