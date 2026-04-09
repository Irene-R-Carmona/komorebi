/**
 * Mejoras de Experiencia de Usuario (UX)
 *
 * Funcionalidades:
 * - Detección de navegación por teclado
 * - Efectos de scroll en header
 * - Smooth scroll para enlaces internos
 * - Lazy loading de imágenes
 * - Animaciones de entrada
 * - Gestión de modales
 *
 * @version 1.0
 */

(function () {
  'use strict';

  // 1. Detectar navegación por teclado
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Tab') {
      document.body.classList.add('keyboard-navigation');
    }
  });

  document.addEventListener('mousedown', function () {
    document.body.classList.remove('keyboard-navigation');
  });

  // 2. Header scroll effect
  let lastScroll = 0;
  const header = document.getElementById('header');

  window.addEventListener('scroll', function () {
    const currentScroll = window.pageYOffset;

    if (currentScroll > 50) {
      if (header) header.classList.add('header--scrolled');
    } else {
      if (header) header.classList.remove('header--scrolled');
    }

    lastScroll = currentScroll;
  });

  // 3. Smooth scroll para anchor links
  document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
      const href = this.getAttribute('href');
      if (href === '#' || !href) return;

      e.preventDefault();
      const target = document.querySelector(href);

      if (target) {
        target.scrollIntoView({
          behavior: 'smooth',
          block: 'start'
        });

        // Focus en el elemento para accesibilidad
        target.setAttribute('tabindex', '-1');
        target.focus();
      }
    });
  });

  // 4. Mejorar validación de formularios
  const forms = document.querySelectorAll('form[data-validate]');

  forms.forEach(form => {
    const inputs = form.querySelectorAll('input, select, textarea');

    inputs.forEach(input => {
      // Validación on blur
      input.addEventListener('blur', function () {
        validateInput(this);
      });

      // Limpiar error on focus
      input.addEventListener('focus', function () {
        clearError(this);
      });
    });

    // Validación en submit
    form.addEventListener('submit', function (e) {
      let isValid = true;

      inputs.forEach(input => {
        if (!validateInput(input)) {
          isValid = false;
        }
      });

      if (!isValid) {
        e.preventDefault();
        // Focus en el primer campo con error
        const firstError = form.querySelector('.form-input--error, .form-select--error, .form-textarea--error');
        if (firstError) {
          firstError.focus();
        }
      }
    });
  });

  /**
   * Valida un campo de formulario
   * @param {HTMLElement} input - Elemento input a validar
   * @returns {boolean} True si es válido
   */
  function validateInput(input) {
    const value = input.value.trim();
    const type = input.type;
    const required = input.hasAttribute('required');
    let isValid = true;
    let errorMessage = '';

    // Limpiar estado previo
    clearError(input);

    // Validar requerido
    if (required && !value) {
      isValid = false;
      errorMessage = 'Este campo es obligatorio';
    }
    // Validar email
    else if (type === 'email' && value) {
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (!emailRegex.test(value)) {
        isValid = false;
        errorMessage = 'Introduce un email válido';
      }
    }
    // Validar mínimo
    else if (input.hasAttribute('minlength')) {
      const min = parseInt(input.getAttribute('minlength'));
      if (value.length < min) {
        isValid = false;
        errorMessage = `Mínimo ${min} caracteres`;
      }
    }

    if (!isValid) {
      showError(input, errorMessage);
    } else if (value) {
      showSuccess(input);
    }

    return isValid;
  }

  /**
   * Muestra error en un campo
   * @param {HTMLElement} input - Elemento input
   * @param {string} message - Mensaje de error
   */
  function showError(input, message) {
    input.classList.add('form-input--error', 'form-select--error', 'form-textarea--error');
    input.classList.remove('form-input--success', 'form-select--success', 'form-textarea--success');
    input.setAttribute('aria-invalid', 'true');

    const group = input.closest('.form-group');
    if (group) {
      let errorEl = group.querySelector('.form-error');
      if (!errorEl) {
        errorEl = document.createElement('p');
        errorEl.className = 'form-error';
        errorEl.setAttribute('role', 'alert');
        group.appendChild(errorEl);
      }
      errorEl.textContent = message;
    }
  }

  /**
   * Muestra éxito en un campo
   * @param {HTMLElement} input - Elemento input
   */
  function showSuccess(input) {
    input.classList.add('form-input--success', 'form-select--success', 'form-textarea--success');
    input.classList.remove('form-input--error', 'form-select--error', 'form-textarea--error');
    input.setAttribute('aria-invalid', 'false');

    const group = input.closest('.form-group');
    if (group) {
      const errorEl = group.querySelector('.form-error');
      if (errorEl) {
        errorEl.remove();
      }
    }
  }

  /**
   * Limpia errores de un campo
   * @param {HTMLElement} input - Elemento input
   */
  function clearError(input) {
    input.classList.remove('form-input--error', 'form-select--error', 'form-textarea--error');
    input.classList.remove('form-input--success', 'form-select--success', 'form-textarea--success');
    input.removeAttribute('aria-invalid');

    const group = input.closest('.form-group');
    if (group) {
      const errorEl = group.querySelector('.form-error');
      if (errorEl) {
        errorEl.remove();
      }
    }
  }

  // 5. Toast auto-dismiss
  function setupToasts() {
    const toasts = document.querySelectorAll('.toast');

    toasts.forEach(toast => {
      // Añadir botón de cierre si no existe
      if (!toast.querySelector('.toast__close')) {
        const closeBtn = document.createElement('button');
        closeBtn.className = 'toast__close';
        closeBtn.innerHTML = '×';
        closeBtn.setAttribute('aria-label', 'Cerrar mensaje');
        closeBtn.addEventListener('click', () => dismissToast(toast));
        toast.appendChild(closeBtn);
      }

      // Auto dismiss después de 5 segundos
      setTimeout(() => {
        dismissToast(toast);
      }, 5000);
    });
  }

  function dismissToast(toast) {
    toast.style.animation = 'slideOutRight 0.3s ease-out';
    setTimeout(() => {
      toast.remove();
    }, 300);
  }

  // 6. Loading state para botones
  function addButtonLoadingState() {
    const buttons = document.querySelectorAll('[data-loading]');

    buttons.forEach(button => {
      button.addEventListener('click', function () {
        if (this.form && !this.form.checkValidity()) {
          return; // No mostrar loading si el form no es válido
        }

        this.classList.add('btn--loading');
        this.disabled = true;

        // Si no hay evento que lo quite, quitarlo después de 10 seg
        setTimeout(() => {
          this.classList.remove('btn--loading');
          this.disabled = false;
        }, 10000);
      });
    });
  }

  // 7. Mejorar accesibilidad de modales
  function setupModals() {
    const modals = document.querySelectorAll('[role="dialog"]');

    modals.forEach(modal => {
      // Trap focus dentro del modal
      modal.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
          closeModal(modal);
        }

        if (e.key === 'Tab') {
          trapFocus(modal, e);
        }
      });
    });
  }

  function trapFocus(element, event) {
    const focusableElements = element.querySelectorAll(
      'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
    );

    const firstElement = focusableElements[0];
    const lastElement = focusableElements[focusableElements.length - 1];

    if (event.shiftKey && document.activeElement === firstElement) {
      event.preventDefault();
      lastElement.focus();
    } else if (!event.shiftKey && document.activeElement === lastElement) {
      event.preventDefault();
      firstElement.focus();
    }
  }

  function closeModal(modal) {
    modal.style.display = 'none';
    modal.setAttribute('aria-hidden', 'true');
  }

  // Inicializar mejoras cuando el DOM esté listo
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  function init() {
    setupToasts();
    addButtonLoadingState();
    setupModals();
  }

  // Anunciar cambios dinámicos para lectores de pantalla
  window.announceToScreenReader = function (message, priority = 'polite') {
    let announcer = document.getElementById('screen-reader-announcer');

    if (!announcer) {
      announcer = document.createElement('div');
      announcer.id = 'screen-reader-announcer';
      announcer.className = 'sr-only';
      announcer.setAttribute('role', 'status');
      announcer.setAttribute('aria-live', priority);
      document.body.appendChild(announcer);
    }

    announcer.textContent = message;

    // Limpiar después de anunciar
    setTimeout(() => {
      announcer.textContent = '';
    }, 1000);
  };

})();

// Animación de slideOutRight para toasts
const style = document.createElement('style');
style.textContent = `
    @keyframes slideOutRight {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);
