// Rastreador externo para 'recientemente visitados' para evitar scripts inline y problemas CSP.
(function () {
  function getCafeId() {
    try {
      let el = document.getElementById('komorebi-page-meta');
      if (!el) return null;
      let v = el.dataset.cafeId;
      return v ? Number(v) : null;
    } catch (e) {
      return null;
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    let cafeId = getCafeId();
    if (!cafeId) return;

    if (typeof CookieHelper !== 'undefined' && CookieHelper.hasConsent && CookieHelper.hasConsent('functional')) {
      fetch('/api/v1/cookies/recently-viewed/add', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ cafeId: cafeId })
      }).then(function (r) { return r.json(); }).then(function (data) {
        if (data && data.success) {
          try { globalThis.dispatchEvent(new CustomEvent('recently-viewed-updated')); } catch (e) { }
        }
      }).catch(function (err) { console.warn('recentlyViewedTracker error', err); });
    }
  });
})();
