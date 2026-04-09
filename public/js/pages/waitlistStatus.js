// Auto-refresh logic for waitlist status page
(function () {
  function init() {
    try {
      const meta = document.getElementById('waitlist-meta');
      const status = meta ? meta.dataset.status : null;
      if (status === 'waiting') {
        setTimeout(() => { location.reload(); }, 30000);
      }
    } catch (e) { console.warn('waitlistStatus init error', e); }
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
