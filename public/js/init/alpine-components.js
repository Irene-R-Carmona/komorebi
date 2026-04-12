/* Central Alpine components registry
 * - Registra componentes usados en vistas mediante `Alpine.data(name, fn)`
 * - Expone funciones en `window` como fallback para usos directos en plantillas
 * - Diseñado para cargarse ANTES de alpine.min.js en las plantillas
 */
(function () {
  'use strict';

  function reviewForm() {
    return {
      rating: 0,
      hoverRating: 0,
      title: '',
      body: '',
      titleValid: false,
      bodyValid: false,
      get titleLength() { return this.title.length; },
      get bodyLength() { return this.body.length; },
      get ratingLabel() {
        const labels = { 1: '⭐ Pobre', 2: '⭐⭐ Aceptable', 3: '⭐⭐⭐ Bueno', 4: '⭐⭐⭐⭐ Muy bueno', 5: '⭐⭐⭐⭐⭐ Excelente' };
        return labels[this.rating] || '';
      },
      get isFormValid() { return this.rating > 0 && this.titleValid && this.bodyValid; },
      validateTitle() { this.titleValid = this.title.trim().length >= 3 && this.title.length <= 100; },
      validateBody() { this.bodyValid = this.body.trim().length >= 10 && this.body.length <= 5000; },
      submitForm() {
        if (!this.isFormValid) return;
        const formData = new FormData();
        const cafeInput = document.querySelector('input[name="cafe_id"]');
        const csrfInput = document.querySelector('input[name="csrf_token"]');
        if (!cafeInput) return alert('Formulario mal configurado (cafe_id)');
        formData.append('cafe_id', cafeInput.value);
        formData.append('rating', this.rating);
        formData.append('title', this.title);
        formData.append('body', this.body);
        if (csrfInput) formData.append('csrf_token', csrfInput.value);

        fetch('/reviews', { method: 'POST', body: formData })
          .then(response => {
            if (!response.ok) throw new Error('Network response was not ok');
            window.location.href = response.url || window.location.href;
          })
          .catch(err => {
            console.error('reviewForm submit error:', err);
            alert('Error al enviar la reseña. Intenta de nuevo.');
          });
      }
    };
  }

  function loyaltyRewards() {
    return {
      async redeemReward(rewardType) {
        if (!confirm('¿Confirmas que deseas canjear esta recompensa?')) return;
        try {
          const csrfMeta = document.querySelector('meta[name="csrf-token"]');
          const response = await fetch('/api/v1/loyalty/redeem', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': (csrfMeta && csrfMeta.content) || '' },
            body: JSON.stringify({ reward_type: rewardType })
          });
          const result = await response.json();
          if (result && result.ok && result.data) {
            alert(`✨ ¡Recompensa canjeada!\n\nTu código: ${result.data.code}\n\nExpira: ${result.data.expires_at}`);
            window.location.reload();
          } else {
            alert('Error: ' + ((result && result.error) || 'No se pudo canjear'));
          }
        } catch (error) {
          console.error('redeemReward error:', error);
          alert('Error de conexión. Por favor, intenta de nuevo.');
        }
      }
    };
  }

  // Preferir fábricas centralizadas expuestas en window (public/js/components/*)
  // Si no existen, conservar las implementaciones internas como fallback.

  function quizFilosofico(initialPreguntas) {
    // Si existe la fábrica centralizada, utilízala
    if (window.quizFilosofico) return function (p) { return window.quizFilosofico(p); }(initialPreguntas);
    // Fallback conservador
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
  }

  // Pequeños dummies para componentes usados en múltiples vistas
  function emptyComponent() { return {}; }

  // Construir el registry preferiendo fábricas expuestas en window (centralizadas)
  const registry = {
    reviewForm: window.reviewForm || reviewForm,
    loyaltyRewards: window.loyaltyRewards || loyaltyRewards,
    // catalogoApp puede venir de public/js/components/catalogo.js o de los scripts de sección
    catalogoApp: window.catalogoApp || (typeof catalogoApp === 'function' ? catalogoApp : emptyComponent),
    quizFilosofico: window.quizFilosofico || quizFilosofico,
    detalleCafe: window.detalleCafe || emptyComponent,
    receptionApp: emptyComponent,
    kdsApp: emptyComponent,
    avatarUpload: window.avatarUpload || emptyComponent
  };

  function registerWithAlpine(Alpine) {
    if (!Alpine || !Alpine.data) return;
    Object.keys(registry).forEach(name => {
      try { Alpine.data(name, registry[name]); } catch (e) { console.warn('Alpine.register failed', name, e); }
    });
  }

  // No exponer fallbacks globales aquí para evitar sobrescribir implementaciones
  // específicas definidas en los scripts de sección. El registro con Alpine
  // se realiza en `registerWithAlpine` cuando Alpine esté listo.

  if (window.Alpine && window.Alpine.data) {
    registerWithAlpine(window.Alpine);
  } else {
    document.addEventListener('alpine:init', function () { registerWithAlpine(window.Alpine); });
    // some builds may use alpine:initializing event
    document.addEventListener('alpine:initializing', function () { registerWithAlpine(window.Alpine); });
  }

})();
