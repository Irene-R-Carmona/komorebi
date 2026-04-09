// Lee datos serializados del nivel de página inyectados como data-attributes y los expone en window.
(function () {
  function init() {
    try {
      var el = document.getElementById('komorebi-page-meta');
      if (!el) return;

      var pd = el.dataset.productDict;
      if (pd) {
        try {
          window.productDict = JSON.parse(pd);
        } catch (e) {
          window.productDict = window.productDict || {};
        }
      }
    } catch (e) {
      // noop
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
