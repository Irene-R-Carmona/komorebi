// Implementación externa del factory newsletterPopup (movido desde la vista inline)
(function () {
  function make() {
    return {
      showPopup: false,
      email: '',
      loading: false,
      message: '',
      messageType: 'success',

      init: function () {
        if (window.location.pathname !== '/') return;
        let self = this;
        setTimeout(function () { self.checkShouldShow(); }, 15000);
      },

      checkShouldShow: async function () {
        if (sessionStorage.getItem('newsletter_prompted') === '1') return;
        try {
          let res = await fetch('/api/v1/cookies/newsletter-prompted');
          let data = await res.json();
          if (!data.prompted) this.showPopup = true;
        } catch (err) {
          console.warn('newsletterPopup check error', err);
        }
      },

      closePopup: async function (permanent) {
        if (permanent === undefined) permanent = false;
        this.showPopup = false;
        sessionStorage.setItem('newsletter_prompted', '1');
        if (permanent) {
          try {
            await fetch('/api/v1/cookies/newsletter-prompted', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ permanent: true })
            });
          } catch (err) { console.warn('newsletterPopup save error', err); }
        }
      },

      subscribe: async function () {
        if (!this.email) return;
        this.loading = true;
        this.message = '';
        try {
          let res = await fetch('/api/v1/newsletter/subscribe', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email: this.email })
          });
          let data = await res.json();
          if (data && data.success) {
            this.messageType = 'success';
            this.message = 'Suscripción exitosa. Revisa tu correo para confirmar.';
            this.email = '';
            let self = this;
            setTimeout(function () { self.closePopup(true); }, 3000);
          } else {
            this.messageType = 'error';
            this.message = (data && data.message) ? data.message : 'Error al suscribirse. Intenta de nuevo.';
          }
        } catch (err) {
          this.messageType = 'error';
          this.message = 'Error de conexión. Por favor, intenta más tarde.';
        } finally {
          this.loading = false;
        }
      }
    };
  }

  if (globalThis.window !== undefined) {
    globalThis.newsletterPopup = function () { return make(); };
  }
})();
