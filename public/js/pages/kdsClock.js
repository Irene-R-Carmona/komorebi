// KDS clock updater + card time updater (countdown from prep_time)
(function () {
  function padZ(n) { return String(n).padStart(2, '0'); }

  function countdownLabel(createdTs, prepMins) {
    var elapsed = Math.max(0, Math.floor((Date.now() / 1000) - createdTs));
    var remaining = (prepMins * 60) - elapsed;
    if (remaining > 0) {
      return padZ(Math.floor(remaining / 60)) + ':' + padZ(remaining % 60);
    }
    var over = -remaining;
    return '+' + padZ(Math.floor(over / 60)) + ':' + padZ(over % 60);
  }

  function updateCardTimes() {
    document.querySelectorAll('[data-started-ts]').forEach(function (card) {
      var ts = parseInt(card.dataset.startedTs, 10);
      var prepMins = parseInt(card.dataset.prepTime, 10) || 5;
      var el = card.querySelector('.kds-card__time');

      // Si el cocinero aún no ha pulsado INICIAR (ts === 0), mostrar -- sin urgencia
      if (!ts) {
        if (el) el.textContent = '--:--';
        card.classList.remove('kds-card--late', 'kds-card--warn');
        return;
      }

      var elapsed = Math.max(0, Math.floor((Date.now() / 1000) - ts));
      var remaining = (prepMins * 60) - elapsed;

      if (el) el.textContent = countdownLabel(ts, prepMins);

      card.classList.remove('kds-card--late', 'kds-card--warn');
      if (remaining <= 0) {
        card.classList.add('kds-card--late');
      } else if (remaining <= 120) {
        card.classList.add('kds-card--warn');
      }
    });
  }

  function init() {
    try {
      setInterval(function () {
        var el = document.getElementById('kdsClock');
        if (el) el.innerText = new Date().toLocaleTimeString('en-GB');
      }, 1000);
      setInterval(updateCardTimes, 1000);
      updateCardTimes();
    } catch (e) { console.warn('kdsClock init error', e); }
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
