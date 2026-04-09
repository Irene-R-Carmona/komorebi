/**
 * Backoffice UX Enhancements
 *
 * Mejoras de experiencia de usuario para el panel de administración
 * Incluye: validación de formularios, loading states, keyboard navigation,
 * toasts automáticos, focus management y accesibilidad WCAG 2.1 AA
 */

(function () {
  'use strict';

  // ========================================================================
  // CONFIGURACIÓN
  // ========================================================================

  const CONFIG = {
    TOAST_DURATION: 5000,
    TOAST_AUTO_DISMISS: true,
    KEYBOARD_NAV_CLASS: 'keyboard-navigation',
    LOADING_CLASS: 'btn--loading',
    VALIDATION_DEBOUNCE: 300
  };

  // ========================================================================
  // DETECCIÓN DE NAVEGACIÓN POR TECLADO
  // ========================================================================

  /**
   * Detecta si el usuario está navegando con teclado
   * Aplica clase para mostrar focus rings solo con teclado
   */
  function initKeyboardNavigation() {
    let isKeyboardNav = false;

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Tab') {
        isKeyboardNav = true;
        document.body.classList.add(CONFIG.KEYBOARD_NAV_CLASS);
      }
    });

    document.addEventListener('mousedown', () => {
      isKeyboardNav = false;
      document.body.classList.remove(CONFIG.KEYBOARD_NAV_CLASS);
    });

    // Escuchar Escape para cerrar modales/dropdowns
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        closeActiveModals();
        closeActiveDropdowns();
      }
    });
  }

  function closeActiveModals() {
    const modals = document.querySelectorAll('.modal.show');
    modals.forEach(modal => {
      try {
        if (window.bootstrap && bootstrap.Modal && typeof bootstrap.Modal.getInstance === 'function') {
          const bsModal = bootstrap.Modal.getInstance(modal);
          if (bsModal && typeof bsModal.hide === 'function') bsModal.hide();
        }
      } catch (e) {
        // Evitar romper UX si bootstrap no está disponible o hay error
      }
    });
  }

  /**
   * Cierra todos los dropdowns activos
   */
  function closeActiveDropdowns() {
    const dropdowns = document.querySelectorAll('.dropdown.show');
    dropdowns.forEach(dropdown => {
      const toggle = dropdown.querySelector('[data-bs-toggle="dropdown"]');
      if (toggle) toggle.click();
    });
  }

  // ========================================================================
  // SISTEMA DE TOASTS
  // ========================================================================

  /**
   * Muestra un toast de notificación
   * @param {string} message - Mensaje a mostrar
   * @param {string} type - Tipo: success, error, warning, info
   * @param {number} duration - Duración en ms (0 = no auto-dismiss)
   */
  window.showToast = function (message, type = 'info', duration = CONFIG.TOAST_DURATION) {
    const container = getOrCreateToastContainer();

    const icons = {
      success: 'bi-check-circle-fill',
      error: 'bi-x-circle-fill',
      warning: 'bi-exclamation-triangle-fill',
      info: 'bi-info-circle-fill'
    };

    const titles = {
      success: 'Éxito',
      error: 'Error',
      warning: 'Advertencia',
      info: 'Información'
    };

    const toastId = `toast-${Date.now()}`;
    const icon = icons[type] || icons.info;
    const title = titles[type] || titles.info;

    const toastHTML = `
            <div class="toast toast-${type}" id="${toastId}" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="toast-header">
                    <i class="bi ${icon} me-2"></i>
                    <strong class="me-auto">${title}</strong>
                    <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Cerrar"></button>
                </div>
                <div class="toast-body">
                    ${escapeHtml(message)}
                </div>
            </div>
        `;

    container.insertAdjacentHTML('beforeend', toastHTML);

    const toastElement = document.getElementById(toastId);
    const toast = new bootstrap.Toast(toastElement, {
      autohide: CONFIG.TOAST_AUTO_DISMISS && duration > 0,
      delay: duration
    });

    toast.show();

    // Remover del DOM cuando se cierre
    toastElement.addEventListener('hidden.bs.toast', () => {
      toastElement.remove();
    });

    return toast;
  };

  function getOrCreateToastContainer() {
    let container = document.querySelector('.toast-container');
    if (!container) {
      container = document.createElement('div');
      container.className = 'toast-container';
      container.setAttribute('aria-live', 'polite');
      container.setAttribute('aria-label', 'Notificaciones');
      document.body.appendChild(container);
    }
    return container;
  }

  /**
   * Escapa caracteres HTML para prevenir XSS
   * @param {string} text - Texto a escapar
   * @returns {string} Texto escapado
   */
  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  // ========================================================================
  // VALIDACIÓN DE FORMULARIOS
  // ========================================================================

  /**
   * Inicializa validación en tiempo real para formularios
   */
  function initFormValidation() {
    const forms = document.querySelectorAll('form[data-validate]');

    forms.forEach(form => {
      const inputs = form.querySelectorAll('input, select, textarea');

      inputs.forEach(input => {
        // Validar al perder foco
        input.addEventListener('blur', () => validateInput(input));

        // Validar al escribir (con debounce)
        let debounceTimer;
        input.addEventListener('input', () => {
          clearTimeout(debounceTimer);
          debounceTimer = setTimeout(() => {
            if (input.classList.contains('is-invalid') || input.classList.contains('is-valid')) {
              validateInput(input);
            }
          }, CONFIG.VALIDATION_DEBOUNCE);
        });
      });

      // Validar al enviar
      form.addEventListener('submit', (e) => {
        let isValid = true;

        inputs.forEach(input => {
          if (!validateInput(input)) {
            isValid = false;
          }
        });

        if (!isValid) {
          e.preventDefault();
          e.stopPropagation();

          // Focus en primer campo inválido
          const firstInvalid = form.querySelector('.is-invalid');
          if (firstInvalid) {
            firstInvalid.focus();
            announceToScreenReader('Hay errores en el formulario. Por favor, corrígelos.');
          }
        }
      });
    });
  }

  /**
   * Valida un input individual
   * @param {HTMLElement} input - Input a validar
   * @returns {boolean} - True si es válido
   */
  function validateInput(input) {
    if (!input) return true;

    // Limpiar estados previos
    try {
      input.classList.remove('is-valid', 'is-invalid');
    } catch (e) {
      // input puede no ser un HTMLElement válido
    }

    const feedback = getOrCreateFeedback(input);
    if (feedback) feedback.textContent = '';

    // Si está vacío y no es requerido, es válido
    if (!input.value && !input.required) {
      return true;
    }

    // Validar requerido
    if (input.required && !input.value) {
      setInvalid(input, feedback, 'Este campo es obligatorio');
      return false;
    }

    // Validar email
    if (input.type === 'email' && input.value) {
      const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (!emailPattern.test(input.value)) {
        setInvalid(input, feedback, 'Introduce un email válido');
        return false;
      }
    }

    // Validar URL
    if (input.type === 'url' && input.value) {
      try {
        new URL(input.value);
      } catch {
        setInvalid(input, feedback, 'Introduce una URL válida');
        return false;
      }
    }

    // Validar longitud mínima
    const minLength = input.getAttribute('minlength');
    if (minLength && input.value.length < parseInt(minLength)) {
      setInvalid(input, feedback, `Mínimo ${minLength} caracteres`);
      return false;
    }

    // Validar longitud máxima
    const maxLength = input.getAttribute('maxlength');
    if (maxLength && input.value.length > parseInt(maxLength)) {
      setInvalid(input, feedback, `Máximo ${maxLength} caracteres`);
      return false;
    }

    // Validar números
    if (input.type === 'number' && input.value) {
      const min = input.getAttribute('min');
      const max = input.getAttribute('max');
      const value = parseFloat(input.value);

      if (min && value < parseFloat(min)) {
        setInvalid(input, feedback, `El valor mínimo es ${min}`);
        return false;
      }

      if (max && value > parseFloat(max)) {
        setInvalid(input, feedback, `El valor máximo es ${max}`);
        return false;
      }
    }

    // Validar pattern personalizado
    const pattern = input.getAttribute('pattern');
    if (pattern && input.value) {
      const regex = new RegExp(pattern);
      if (!regex.test(input.value)) {
        const errorMsg = input.getAttribute('data-pattern-error') || 'Formato inválido';
        setInvalid(input, feedback, errorMsg);
        return false;
      }
    }

    // Todo OK
    setValid(input, feedback);
    return true;
  }

  function setInvalid(input, feedback, message) {
    if (!input) return;
    try {
      input.classList.add('is-invalid');
      input.setAttribute('aria-invalid', 'true');
    } catch (e) {
      // ignore
    }
    if (feedback) {
      feedback.textContent = message;
      feedback.classList.remove('valid-feedback');
      feedback.classList.add('invalid-feedback');
    }
  }

  function setValid(input, feedback) {
    if (!input) return;
    try {
      input.classList.add('is-valid');
      input.setAttribute('aria-invalid', 'false');
    } catch (e) {
      // ignore
    }
    if (feedback) {
      feedback.textContent = '✓ Correcto';
      feedback.classList.remove('invalid-feedback');
      feedback.classList.add('valid-feedback');
    }
  }

  function getOrCreateFeedback(input) {
    if (!input) return null;
    const parent = input.parentElement || document.body;

    let feedback = null;
    try {
      feedback = parent.querySelector('.invalid-feedback, .valid-feedback');
    } catch (e) {
      feedback = null;
    }

    if (!feedback) {
      feedback = document.createElement('div');
      feedback.className = 'invalid-feedback';
      try {
        parent.appendChild(feedback);
      } catch (e) {
        document.body.appendChild(feedback);
      }
    }

    return feedback;
  }

  // ========================================================================
  // LOADING STATES EN BOTONES
  // ========================================================================

  /**
   * Añade loading state a un botón
   * @param {HTMLElement} button - Botón a modificar
   */
  window.setButtonLoading = function (button) {
    if (!button) return;

    button.disabled = true;
    button.classList.add(CONFIG.LOADING_CLASS);
    button.setAttribute('aria-busy', 'true');

    // Guardar texto original
    if (!button.hasAttribute('data-original-text')) {
      button.setAttribute('data-original-text', button.textContent);
    }
  };

  /**
   * Quita loading state de un botón
   * @param {HTMLElement} button - Botón a modificar
   */
  window.removeButtonLoading = function (button) {
    if (!button) return;

    button.disabled = false;
    button.classList.remove(CONFIG.LOADING_CLASS);
    button.setAttribute('aria-busy', 'false');

    // Restaurar texto original
    const originalText = button.getAttribute('data-original-text');
    if (originalText) {
      button.textContent = originalText;
    }
  };

  /**
   * Auto-detectar formularios con loading
   */
  function initFormLoadingStates() {
    const forms = document.querySelectorAll('form[data-loading]');

    forms.forEach(form => {
      form.addEventListener('submit', (e) => {
        const submitButton = form.querySelector('[type="submit"]');
        if (submitButton) {
          setButtonLoading(submitButton);

          // Si falla validación, quitar loading
          setTimeout(() => {
            if (!form.checkValidity()) {
              removeButtonLoading(submitButton);
            }
          }, 100);
        }
      });
    });
  }

  // ========================================================================
  // CONFIRMACIONES DE ACCIONES DESTRUCTIVAS
  // ========================================================================

  function initConfirmDialogs() {
    const confirmButtons = document.querySelectorAll('[data-confirm]');

    confirmButtons.forEach(button => {
      button.addEventListener('click', (e) => {
        const message = button.getAttribute('data-confirm');
        if (!confirm(message)) {
          e.preventDefault();
          e.stopPropagation();
        }
      });
    });
  }

  // ========================================================================
  // TABLAS INTERACTIVAS
  // ========================================================================

  /**
   * Mejora tablas con ordenación, búsqueda y acciones
   */
  function initInteractiveTables() {
    const tables = document.querySelectorAll('table[data-interactive]');

    tables.forEach(table => {
      // Hacer filas clicables si tienen data-href
      const rows = table.querySelectorAll('tbody tr[data-href]');
      rows.forEach(row => {
        row.style.cursor = 'pointer';
        row.addEventListener('click', (e) => {
          // No navegar si se clickeó un botón o enlace
          if (e.target.closest('button, a')) return;

          const href = row.getAttribute('data-href');
          if (href) {
            window.location.href = href;
          }
        });

        // Accesibilidad: Enter también navega
        row.setAttribute('tabindex', '0');
        row.addEventListener('keydown', (e) => {
          if (e.key === 'Enter' && !e.target.closest('button, a')) {
            const href = row.getAttribute('data-href');
            if (href) {
              window.location.href = href;
            }
          }
        });
      });
    });
  }

  // ========================================================================
  // FOCUS TRAP EN MODALES
  // ========================================================================

  function initModalFocusTrap() {
    const modals = document.querySelectorAll('.modal');

    modals.forEach(modal => {
      modal.addEventListener('shown.bs.modal', () => {
        trapFocus(modal);

        // Focus en primer elemento focusable
        const firstFocusable = getFocusableElements(modal)[0];
        if (firstFocusable) {
          setTimeout(() => firstFocusable.focus(), 100);
        }
      });
    });
  }

  function trapFocus(element) {
    const focusables = getFocusableElements(element);
    const firstFocusable = focusables[0];
    const lastFocusable = focusables[focusables.length - 1];

    element.addEventListener('keydown', (e) => {
      if (e.key !== 'Tab') return;

      if (e.shiftKey && document.activeElement === firstFocusable) {
        e.preventDefault();
        lastFocusable.focus();
      } else if (!e.shiftKey && document.activeElement === lastFocusable) {
        e.preventDefault();
        firstFocusable.focus();
      }
    });
  }

  function getFocusableElements(container) {
    return Array.from(
      container.querySelectorAll(
        'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
      )
    ).filter(el => !el.disabled && el.offsetParent !== null);
  }

  // ========================================================================
  // ANUNCIOS PARA LECTORES DE PANTALLA
  // ========================================================================

  function announceToScreenReader(message, priority = 'polite') {
    let announcer = document.getElementById('sr-announcer');

    if (!announcer) {
      announcer = document.createElement('div');
      announcer.id = 'sr-announcer';
      announcer.className = 'sr-only';
      announcer.setAttribute('role', 'status');
      announcer.setAttribute('aria-live', priority);
      document.body.appendChild(announcer);
    }

    announcer.setAttribute('aria-live', priority);
    announcer.textContent = message;

    // Limpiar después de 1 segundo
    setTimeout(() => {
      announcer.textContent = '';
    }, 1000);
  }

  window.announceToScreenReader = announceToScreenReader;

  // ========================================================================
  // AUTO-SUBMIT DE BÚSQUEDAS (CON DEBOUNCE)
  // ========================================================================

  function initSearchInputs() {
    const searchInputs = document.querySelectorAll('input[data-auto-submit]');

    searchInputs.forEach(input => {
      let debounceTimer;
      const delay = parseInt(input.getAttribute('data-debounce')) || 500;

      input.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
          const form = input.closest('form');
          if (form) {
            form.submit();
          }
        }, delay);
      });
    });
  }

  // ========================================================================
  // COPIAR AL PORTAPAPELES
  // ========================================================================

  function initCopyButtons() {
    const copyButtons = document.querySelectorAll('[data-copy]');

    copyButtons.forEach(button => {
      button.addEventListener('click', async () => {
        const text = button.getAttribute('data-copy');

        try {
          await navigator.clipboard.writeText(text);
          showToast('Copiado al portapapeles', 'success', 2000);

          // Feedback visual
          const originalText = button.textContent;
          button.textContent = '✓ Copiado';
          setTimeout(() => {
            button.textContent = originalText;
          }, 2000);
        } catch (err) {
          showToast('Error al copiar', 'error', 3000);
        }
      });
    });
  }

  // ========================================================================
  // PREVENIR DOBLE SUBMIT
  // ========================================================================

  function preventDoubleSubmit() {
    const forms = document.querySelectorAll('form');

    forms.forEach(form => {
      let isSubmitting = false;

      form.addEventListener('submit', (e) => {
        if (isSubmitting) {
          e.preventDefault();
          return false;
        }

        isSubmitting = true;

        // Reset después de 5 segundos por si falla
        setTimeout(() => {
          isSubmitting = false;
        }, 5000);
      });
    });
  }

  // ========================================================================
  // AUTO-RESIZE TEXTAREAS
  // ========================================================================

  function initAutoResizeTextareas() {
    const textareas = document.querySelectorAll('textarea[data-auto-resize]');

    textareas.forEach(textarea => {
      function resize() {
        textarea.style.height = 'auto';
        textarea.style.height = textarea.scrollHeight + 'px';
      }

      textarea.addEventListener('input', resize);
      resize(); // Initial resize
    });
  }

  // ========================================================================
  // TOOLTIPS DE BOOTSTRAP
  // ========================================================================

  function initTooltips() {
    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    [...tooltipTriggerList].map(el => new bootstrap.Tooltip(el));
  }

  // ========================================================================
  // POPOVERS DE BOOTSTRAP
  // ========================================================================

  function initPopovers() {
    const popoverTriggerList = document.querySelectorAll('[data-bs-toggle="popover"]');
    [...popoverTriggerList].map(el => new bootstrap.Popover(el));
  }

  // ========================================================================
  // INICIALIZACIÓN
  // ========================================================================

  document.addEventListener('DOMContentLoaded', () => {
    console.log('🚀 Inicializando UX Enhancements del Backoffice...');

    initKeyboardNavigation();
    initFormValidation();
    initFormLoadingStates();
    initConfirmDialogs();
    initInteractiveTables();
    initModalFocusTrap();
    initSearchInputs();
    initCopyButtons();
    preventDoubleSubmit();
    initAutoResizeTextareas();
    initTooltips();
    initPopovers();

    console.log('✅ UX Enhancements iniciados correctamente');

    // Anunciar que la página está lista
    announceToScreenReader('Página cargada y lista para interactuar');
  });

  // ========================================================================
  // HELPERS PÚBLICOS
  // ========================================================================

  window.BackofficeUX = {
    showToast,
    setButtonLoading,
    removeButtonLoading,
    announceToScreenReader,
    validateInput
  };

})();
