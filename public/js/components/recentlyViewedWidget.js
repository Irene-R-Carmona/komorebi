// Implementación del widget de cafes recientemente visitados (movido desde la vista PHP inline)
(function () {
  function makeWidget() {
    return {
      cafes: [],
      loading: false,

      init: function () {
        this.loadCafes();
        try {
          globalThis.addEventListener('recently-viewed-updated', () => {
            this.loadCafes();
          });
        } catch (e) {
          // ignore
        }
      },

      loadCafes: async function () {
        if (typeof CookieHelper === 'undefined' || !CookieHelper.hasConsent || !CookieHelper.hasConsent('functional')) {
          this.cafes = [];
          return;
        }

        this.loading = true;
        try {
          const res = await fetch('/api/v1/cookies/recently-viewed/data');
          const data = await res.json();
          if (data && data.success) {
            this.cafes = data.cafes || [];
          }
        } catch (err) {
          console.warn('recentlyViewedWidget load error', err);
          this.cafes = [];
        } finally {
          this.loading = false;
        }
      },

      clearAll: async function () {
        if (!confirm('¿Estás seguro de que quieres limpiar el historial?')) return;
        try {
          const res = await fetch('/api/v1/cookies/recently-viewed/clear', { method: 'DELETE' });
          const data = await res.json();
          if (data && data.success) this.cafes = [];
        } catch (err) {
          console.warn('recentlyViewedWidget clear error', err);
        }
      }
    };
  }

  // Exponer como factory global esperado por las plantillas
  if (globalThis.window !== undefined) {
    globalThis.recentlyViewedWidget = function () { return makeWidget(); };
  }
})();
