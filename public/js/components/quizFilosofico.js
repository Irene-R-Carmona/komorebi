// Componente centralizado: quizFilosofico
// Exporta la fábrica en window.quizFilosofico
(function () {
  'use strict';

  globalThis.quizFilosofico = function (initialPreguntas) {
    const preguntas = initialPreguntas || [];
    return {
      preguntas,
      step: 0,
      totalPreguntas: preguntas.length || 5,
      respuestas: [],
      enviando: false,
      get preguntaActual() { return this.preguntas[this.step - 1] ?? null; },
      get respuestaActual() { return this.respuestas[this.step] ?? null; },
      set respuestaActual(v) { this.respuestas[this.step] = v; },
      get progress() { return ((this.step) / this.totalPreguntas) * 100; },
      iniciar() { this.step = 1; },
      responder(idx) { this.respuestaActual = idx; },
      avanzar() { if (this.step < this.totalPreguntas && this.respuestaActual !== null) this.step++; },
      retroceder() { if (this.step > 1) this.step--; },
      async enviar() {
        if (this.respuestaActual === null || this.enviando) return;
        this.enviando = true;
        try {
          await new Promise(r => setTimeout(r, 600));
          window.location.href = '/cafes';
        } catch (e) { console.error('quiz enviar error:', e); alert('Error al procesar el quiz. Intenta más tarde.'); } finally { this.enviando = false; }
      }
    };
  };

})();
