// Delegación de eventos ligera para reemplazar handlers inline
(function () {
  const Delegation = {
    handlers: {},
    register(name, fn) { this.handlers[name] = fn },
    handleClick(e) {
      const el = e.target.closest('[data-action]');
      if (!el) return;
      const name = el.dataset.action;
      const fn = this.handlers[name];
      if (typeof fn === 'function') {
        fn.call(el, e);
      }
    },
    handleSubmit(e) {
      const form = e.target.closest('form[data-action]');
      if (!form) return;
      const name = form.dataset.action;
      const fn = this.handlers[name];
      if (typeof fn === 'function') {
        e.preventDefault();
        fn.call(form, e);
      }
    },
    init() {
      document.addEventListener('click', this.handleClick.bind(this), true);
      document.addEventListener('submit', this.handleSubmit.bind(this), true);
    }
    ,
    attachLoadObservers() {
      // Añadir listener load a imgs con data-delegate-load
      const imgs = document.querySelectorAll('img[data-delegate-load]');
      imgs.forEach(img => {
        const markLoaded = () => img.classList.add('loaded');
        if (img.complete) {
          markLoaded();
        } else {
          img.addEventListener('load', markLoaded, { once: true });
        }
      });
    }
  };

  // Handlers comunes
  Delegation.register('confirm', function (e) {
    const msg = this.dataset.confirm || '¿Continuar?';
    const proceed = confirm(msg);
    if (!proceed) {
      e.preventDefault();
      return;
    }
    // si es botón dentro de form, submit el form más cercano
    const form = this.closest('form');
    if (form) form.submit();
  });

  Delegation.register('openWindow', function (e) {
    const url = this.dataset.url;
    const opts = this.dataset.features || 'width=550,height=420';
    if (!url) return;
    window.open(url, '_blank', opts);
  });

  Delegation.register('scrollTo', function (e) {
    const target = this.dataset.target;
    if (!target) return;
    const el = document.getElementById(target);
    if (el) el.scrollIntoView({ behavior: 'smooth' });
  });

  globalThis.Delegation = Delegation;
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
      Delegation.init();
      Delegation.attachLoadObservers();
    });
  } else {
    Delegation.init();
    Delegation.attachLoadObservers();
  }
})();
