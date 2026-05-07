// Script externalizado para waitlist confirm
(function () {
  function init() {
    try {
      const rootStyle = getComputedStyle(document.documentElement);
      const colorError = rootStyle.getPropertyValue('--color-error').trim() || '#9B2335';
      const colorWarning = rootStyle.getPropertyValue('--color-warning').trim() || '#C9A959';
      const meta = document.getElementById('waitlist-meta');
      const expiresAtRaw = meta ? meta.dataset.expiresAt : null;
      const expiresAt = expiresAtRaw ? new Date(expiresAtRaw.replace(' ', 'T') + 'Z').getTime() : null;
      const countdownEl = document.getElementById('countdown');
      const confirmBtn = document.getElementById('confirmBtn');
      const confirmForm = document.getElementById('confirmForm');
      const errorMessage = document.getElementById('errorMessage');

      function updateCountdown() {
        if (!expiresAt) return;
        const now = Date.now();
        const distance = expiresAt - now;
        if (distance < 0) {
          countdownEl.textContent = '⏰ Tiempo Expirado';
          countdownEl.style.color = colorError;
          confirmBtn.disabled = true;
          confirmBtn.textContent = '❌ Promoción Expirada';
          return;
        }
        const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((distance % (1000 * 60)) / 1000);
        countdownEl.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
        if (distance < 60000) countdownEl.style.color = colorError;
        else if (distance < 300000) countdownEl.style.color = colorWarning;
      }

      updateCountdown();
      setInterval(updateCountdown, 1000);

      if (confirmForm) {
        confirmForm.addEventListener('submit', async (e) => {
          e.preventDefault();
          confirmBtn.disabled = true;
          confirmBtn.textContent = '⏳ Procesando...';
          errorMessage.style.display = 'none';
          try {
            const response = await fetch(globalThis.location.pathname, { method: 'POST', headers: { 'Content-Type': 'application/json' } });
            if (response.ok) {
              globalThis.location.href = globalThis.location.pathname.replace('/confirm/', '/status/') + '?confirmed=1';
            } else {
              const data = await response.json();
              errorMessage.textContent = data.message || 'Error al confirmar reserva';
              errorMessage.style.display = 'block';
              confirmBtn.disabled = false;
              confirmBtn.textContent = '✅ Confirmar Reserva';
            }
          } catch (err) {
            errorMessage.textContent = 'Error de conexión. Por favor, intenta de nuevo.';
            errorMessage.style.display = 'block';
            confirmBtn.disabled = false;
            confirmBtn.textContent = '✅ Confirmar Reserva';
          }
        });
      }
    } catch (e) { console.warn('waitlistConfirm init error', e); }
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
