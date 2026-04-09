/**
 * Componente unificado Alpine.js: Quiz Filosófico
 * - Acepta `preguntas` como parámetro (desde PHP via x-data)
 * - Si no se pasan preguntas, usa un conjunto interno de fallback
 * - Normaliza la estructura de preguntas para asegurar compatibilidad
 */

// Registrar la fábrica centralizada `window.quizFilosofico` con Alpine.
document.addEventListener('alpine:init', () => {
  try {
    if (globalThis.quizFilosofico) {
      Alpine.data('quizFilosofico', globalThis.quizFilosofico);
    } else {
      console.warn('quizFilosofico no encontrada en window; verificar que /js/components/quizFilosofico.js se cargue antes.');
    }
  } catch (e) {
    console.warn('quizFilosofico registration failed:', e);
  }
});
