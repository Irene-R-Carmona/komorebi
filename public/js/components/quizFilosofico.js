// Componente centralizado: quizFilosofico
// Exporta la fábrica en window.quizFilosofico
(function () {
  'use strict';

  globalThis.quizFilosofico = function (initialPreguntas) {
    const preguntas = initialPreguntas || [];
    return {
      preguntas,
      step: 0,
      totalPreguntas: preguntas.length || 6,
      respuestas: [],
      enviando: false,
      animando: true,
      get preguntaActual() { return this.preguntas[this.step - 1] ?? null; },
      get respuestaActual() { return this.respuestas[this.step] ?? null; },
      set respuestaActual(v) { this.respuestas[this.step] = v; },
      get progress() { return ((this.step) / this.totalPreguntas) * 100; },
      iniciar() { this.step = 1; },
      responder(idx) { this.respuestaActual = idx; },
      avanzar() {
        if (this.step < this.totalPreguntas && this.respuestaActual !== null) {
          this.animando = false;
          this.$nextTick(() => { this.step++; this.animando = true; });
        }
      },
      retroceder() {
        if (this.step > 1) {
          this.animando = false;
          this.$nextTick(() => { this.step--; this.animando = true; });
        }
      },
      async enviar() {
        if (this.respuestaActual === null || this.enviando) return;
        this.enviando = true;
        try {
          const respuestas = {};
          this.preguntas.forEach((pregunta, idx) => {
            const resp = this.respuestas[idx + 1];
            if (resp !== undefined) respuestas[pregunta.id] = resp;
          });
          const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
          const response = await fetch('/quiz/resultado', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-Token': csrfToken,
            },
            body: JSON.stringify({ respuestas }),
          });
          if (!response.ok) throw new Error('Error del servidor: ' + response.status);
          if (response.redirected) {
            window.location.href = response.url;
          } else {
            window.location.href = '/quiz/resultado';
          }
        } catch (e) {
          console.error('quiz enviar error:', e);
          window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Error al procesar el quiz. Inténtalo más tarde.', type: 'error' } }));
        } finally { this.enviando = false; }
      }
    };
  };

})();
