// Componente centralizado: reviewForm
// Exporta la fábrica en window.reviewForm
(function () {
  'use strict';

  globalThis.reviewForm = function () {
    return {
      rating: 0,
      hoverRating: 0,
      title: '',
      body: '',
      titleValid: false,
      bodyValid: false,
      get titleLength() { return this.title.length; },
      get bodyLength() { return this.body.length; },
      get ratingLabel() { const labels = { 1: '⭐ Pobre', 2: '⭐⭐ Aceptable', 3: '⭐⭐⭐ Bueno', 4: '⭐⭐⭐⭐ Muy bueno', 5: '⭐⭐⭐⭐⭐ Excelente' }; return labels[this.rating] || ''; },
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
          .catch(err => { console.error('reviewForm submit error:', err); alert('Error al enviar la reseña. Intenta de nuevo.'); });
      }
    };
  };

})();
