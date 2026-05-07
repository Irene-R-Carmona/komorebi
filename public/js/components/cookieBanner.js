// Componente Alpine cookieBanner externo
(function () {
  document.addEventListener('alpine:init', () => {
    Alpine.data('cookieBanner', function () {
      return {
        show: false,
        showModal: false,
        preferences: {
          essential: true,
          functional: false,
          analytics: false
        },

        init() {
          try {
            const attr = this.$el && this.$el.getAttribute && this.$el.dataset.initialShow;
            this.show = (attr === 'true' || attr === true) ? true : false;
          } catch (e) {
            this.show = false;
          }

          const consent = this.getCookie('cookie_consent');
          if (consent) {
            try {
              const parsed = JSON.parse(consent);
              this.preferences.functional = parsed.functional || false;
              this.preferences.analytics = parsed.analytics || false;
              this.show = false;
            } catch (e) {
              console.warn('cookieBanner parse consent error', e);
            }
          }
        },

        async acceptAll() {
          this.preferences.functional = true;
          this.preferences.analytics = false;
          await this.saveConsent();
          this.show = false;
        },

        async rejectOptional() {
          this.preferences.functional = false;
          this.preferences.analytics = false;
          await this.saveConsent();
          this.show = false;
        },

        openModal() { this.showModal = true; },
        closeModal() { this.showModal = false; },

        async saveCustom() {
          await this.saveConsent();
          this.showModal = false;
          this.show = false;
        },

        async saveConsent() {
          try {
            const response = await fetch('/api/v1/cookies', {
              method: 'PATCH',
              headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
              body: JSON.stringify({
                consent: 'custom',
                essential: this.preferences.essential,
                functional: this.preferences.functional,
                analytics: this.preferences.analytics
              })
            });
            const data = await response.json();
            if (!data.success) console.error('Error saving cookie preferences:', data.message);
          } catch (err) {
            console.warn('Network error saving cookie preferences', err);
          }
        },

        getCookie(name) {
          const value = '; ' + document.cookie;
          const parts = value.split('; ' + name + '=');
          if (parts.length === 2) return decodeURIComponent(parts.pop().split(';').shift());
          return null;
        }
      };
    });
  });
})();
