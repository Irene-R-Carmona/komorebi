// Componente Alpine cookiePreferences externo
(function () {
  document.addEventListener('alpine:init', () => {
    Alpine.data('cookiePreferences', (initialPreferences) => ({
      preferences: initialPreferences || { essential: true, functional: false, analytics: false },
      showSaved: false,

      async onToggle() {
        try {
          const response = await fetch('/api/cookies/save-preferences', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(this.preferences)
          });
          const data = await response.json();
          if (data.success) {
            this.showSaved = true;
            setTimeout(() => { this.showSaved = false; }, 3000);
          } else {
            alert('Error al guardar preferencias');
          }
        } catch (err) {
          console.warn('Error saving preferences:', err);
          alert('Error de red al guardar preferencias');
        }
      },

      async deleteAllCookies() {
        if (!confirm('¿Eliminar todas las cookies funcionales? Perderás tus filtros guardados, productos vistos y preferencias dietéticas.')) return;
        try {
          const response = await fetch('/api/cookies/reject-optional', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
          const data = await response.json();
          if (data.success) {
            this.preferences.functional = false;
            this.preferences.analytics = false;
            alert('✅ Cookies eliminadas correctamente');
          } else {
            alert('Error al eliminar cookies');
          }
        } catch (err) {
          console.warn('Error deleting cookies:', err);
          alert('Error de red al eliminar cookies');
        }
      }
    }));
  });
})();
