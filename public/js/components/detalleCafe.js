// Componente centralizado: detalleCafe
// Exporta la fábrica en window.detalleCafe
(function () {
  'use strict';

  globalThis.detalleCafe = function (listaAnimales = []) {
    return {
      animales: listaAnimales,
      animalActivo: null,
      modalOpen: false,

      abrirModal(index) { this.animalActivo = this.animales[index]; this.modalOpen = true; document.body.style.overflow = 'hidden'; },

      cerrarModal() { this.modalOpen = false; setTimeout(() => { this.animalActivo = null; }, 300); document.body.style.overflow = ''; }
    };
  };

})();
