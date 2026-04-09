/**
 * Componente: Cookie Consent Banner
 * Responsabilidad: Gestión de consentimiento de cookies (GDPR)
 * Dependencias: Alpine.js, localStorage
 */

function cookieConsent() {
  return {
    // Estado
    accepted: false,

    // Inicialización
    init() {
      // Verificar si ya aceptó previamente
      const consent = localStorage.getItem('cookie_consent');
      this.accepted = consent === 'accepted';
    },

    // Aceptar cookies
    accept() {
      localStorage.setItem('cookie_consent', 'accepted');
      localStorage.setItem('cookie_consent_date', new Date().toISOString());
      this.accepted = true;

      // Opcional: Enviar evento analytics
      this.trackConsent('accepted');
    },

    // Rechazar cookies no esenciales
    reject() {
      localStorage.setItem('cookie_consent', 'rejected');
      localStorage.setItem('cookie_consent_date', new Date().toISOString());
      this.accepted = true; // Ocultar banner

      this.trackConsent('rejected');
    },

    // Verificar si debe mostrarse el banner
    shouldShow() {
      return !this.accepted;
    },

    // Track consent (opcional - solo con cookies esenciales)
    trackConsent(action) {
      // Registrar en servidor si es necesario
      // fetch('/api/v1/consent', { ... });
      console.log(`Cookie consent: ${action}`);
    }
  };
}

// Exportar para uso global
globalThis.cookieConsent = cookieConsent;
