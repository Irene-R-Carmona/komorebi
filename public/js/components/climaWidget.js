// Componente Alpine climaWidget externo y fallback
(function () {
  document.addEventListener('alpine:init', () => {
    Alpine.data('climaWidget', () => ({
      climaInstance: null,

      init() {
        try {
          this.climaInstance = new ClimaKomorebi('efectos-clima');
          const condicion = this.$el && this.$el.dataset && this.$el.dataset.clima;
          this.climaInstance.iniciarAnimacion(condicion);

          this.$watch('$el', () => {
            if (!this.$el) {
              try {
                if (this.climaInstance && typeof this.climaInstance.destruir === 'function') {
                  this.climaInstance.destruir();
                }
              } catch (e) { }
            }
          });
        } catch (e) {
          console.warn('climaWidget init error', e);
        }
      }
    }));
  });

  // Fallback sin Alpine.js
  if (typeof Alpine === 'undefined') {
    document.addEventListener('DOMContentLoaded', () => {
      const widget = document.querySelector('.clima-widget');
      if (widget) {
        const condicion = widget.dataset.clima;
        try {
          const climaInstance = new ClimaKomorebi('efectos-clima');
          climaInstance.iniciarAnimacion(condicion);
        } catch (e) { console.warn('clima fallback error', e); }
      }
    });
  }
})();
