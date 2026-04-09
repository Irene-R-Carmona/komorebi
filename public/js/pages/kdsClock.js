// KDS clock updater
(function () {
  function init() {
    try {
      setInterval(() => {
        const el = document.getElementById('kdsClock');
        if (el) el.innerText = new Date().toLocaleTimeString('en-GB');
      }, 1000);
    } catch (e) { console.warn('kdsClock init error', e); }
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
