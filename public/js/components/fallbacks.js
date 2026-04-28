/* Minimal safe fallbacks for globals used by Alpine expressions.
   These avoid ReferenceError in pages that reference these symbols.
   We only define them when they don't already exist to avoid overwriting real implementations.
*/
(function () {
  if (typeof window.getQty === 'undefined') {
    window.getQty = function (id) {
      return 0;
    };
  }

  if (typeof window.cart === 'undefined') {
    window.cart = {
      total_qty: 0,
      totalPrice: 0,
      items: [],
    };
  }

  if (typeof window.selectedCafeType === 'undefined') {
    // keep as simple primitive so expressions like `selectedCafeType === null` work
    window.selectedCafeType = null;
  }

  if (typeof window.showComanda === 'undefined') {
    window.showComanda = false;
  }

  if (typeof window.recentlyViewedWidget === 'undefined') {
    // function expected in templates
    window.recentlyViewedWidget = function () {
      return [];
    };
  }

  if (typeof window.init === 'undefined') {
    window.init = function () { };
  }

  if (typeof window.cafes === 'undefined') {
    window.cafes = [];
  }

  if (typeof window.loading === 'undefined') {
    window.loading = false;
  }

  // default product dictionary used by menu pages (if server didn't inject inline)
  if (typeof window.productDict === 'undefined') {
    window.productDict = {};
  }

  // register a no-op Alpine data entry as a safe guard if Alpine looks for them
  if (typeof window.Alpine !== 'undefined' && typeof window.Alpine.data === 'function') {
    try {
      if (!window.Alpine._registeredFallbacks) {
        window.Alpine.data('cart', function () { return window.cart || {}; });
        window.Alpine.data('selectedCafeType', function () { return { selectedCafeType: window.selectedCafeType }; });
        window.Alpine._registeredFallbacks = true;
      }
    } catch (e) {
      // fail silently — just best-effort
    }
  }
})();
