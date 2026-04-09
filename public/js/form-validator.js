/**
 * Componente Alpine.js de Validación de Formularios
 *
 * Proporciona validación en tiempo real para formularios.
 * Uso: <form x-data="formValidator(rules)" @submit.prevent="submitForm">
 */

document.addEventListener('alpine:init', () => {

  /**
   * Componente principal de validación de formularios
   */
  Alpine.data('formValidator', (rules = {}) => ({
    // Estado
    formData: {},
    errors: {},
    touched: {},
    isSubmitting: false,
    isValid: false,

    // Configuración
    rules: rules,

    // Lifecycle
    init() {
      // Inicializar formData con valores del formulario
      const inputs = this.$el.querySelectorAll('input, select, textarea');
      inputs.forEach(input => {
        const name = input.name;
        if (name) {
          this.formData[name] = input.value || '';
          this.touched[name] = false;
          this.errors[name] = '';
        }
      });

      // Watch formData para validar en tiempo real
      this.$watch('formData', () => {
        this.validateAll();
      });
    },

    /**
     * Marca un campo como tocado
     */
    touch(fieldName) {
      this.touched[fieldName] = true;
      this.validateField(fieldName);
    },

    /**
     * Valida un campo específico
     */
    validateField(fieldName) {
      if (!this.rules[fieldName]) {
        return true;
      }

      const value = this.formData[fieldName];
      const fieldRules = this.rules[fieldName];

      // Limpiar error previo
      this.errors[fieldName] = '';

      for (const rule of fieldRules) {
        const error = this.applyRule(value, rule, fieldName);
        if (error) {
          this.errors[fieldName] = error;
          return false;
        }
      }

      return true;
    },

    /**
     * Valida todos los campos
     */
    validateAll() {
      let allValid = true;

      for (const fieldName in this.rules) {
        const isValid = this.validateField(fieldName);
        if (!isValid) {
          allValid = false;
        }
      }

      this.isValid = allValid;
      return allValid;
    },

    /**
     * Aplica una regla de validación
     */
    applyRule(value, rule, fieldName) {
      // Required
      if (rule === 'required') {
        if (!value || value.trim() === '') {
          return `${this.getFieldLabel(fieldName)} es obligatorio`;
        }
      }

      // Email
      if (rule === 'email' && value) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(value)) {
          return 'Email inválido';
        }
      }

      // Min length
      if (rule.startsWith('min:') && value) {
        const min = Number.parseInt(rule.split(':')[1]);
        if (value.length < min) {
          return `Mínimo ${min} caracteres`;
        }
      }

      // Max length
      if (rule.startsWith('max:') && value) {
        const max = Number.parseInt(rule.split(':')[1]);
        if (value.length > max) {
          return `Máximo ${max} caracteres`;
        }
      }

      // Numeric
      if (rule === 'numeric' && value) {
        if (Number.isNaN(value)) {
          return 'Debe ser un número';
        }
      }

      // Min value
      if (rule.startsWith('minValue:') && value) {
        const min = Number.parseFloat(rule.split(':')[1]);
        if (Number.parseFloat(value) < min) {
          return `Valor mínimo: ${min}`;
        }
      }

      // Max value
      if (rule.startsWith('maxValue:') && value) {
        const max = Number.parseFloat(rule.split(':')[1]);
        if (Number.parseFloat(value) > max) {
          return `Valor máximo: ${max}`;
        }
      }

      // URL
      if (rule === 'url' && value) {
        try {
          new URL(value);
        } catch {
          return 'URL inválida';
        }
      }

      // Phone (español)
      if (rule === 'phone' && value) {
        const phoneRegex = /^(\+34)?[6-9]\d{8}$/;
        const cleanPhone = value.replaceAll(/[\s-]/g, '');
        if (!phoneRegex.test(cleanPhone)) {
          return 'Teléfono inválido (ej: 612345678)';
        }
      }

      // Date
      if (rule === 'date' && value) {
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) {
          return 'Fecha inválida';
        }
      }

      // Future date
      if (rule === 'future' && value) {
        const date = new Date(value);
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        if (date < today) {
          return 'La fecha debe ser futura';
        }
      }

      // Match field
      if (rule.startsWith('match:') && value) {
        const matchField = rule.split(':')[1];
        if (value !== this.formData[matchField]) {
          return `Debe coincidir con ${this.getFieldLabel(matchField)}`;
        }
      }

      return null;
    },

    /**
     * Obtiene el label de un campo
     */
    getFieldLabel(fieldName) {
      const input = this.$el.querySelector(`[name="${fieldName}"]`);
      const label = input?.labels?.[0];
      return label?.textContent?.trim() || fieldName;
    },

    /**
     * Verifica si un campo tiene error y ha sido tocado
     */
    hasError(fieldName) {
      return this.touched[fieldName] && this.errors[fieldName];
    },

    /**
     * Obtiene el mensaje de error de un campo
     */
    getError(fieldName) {
      return this.errors[fieldName] || '';
    },

    /**
     * Submit del formulario
     */
    async submitForm(submitCallback) {
      // Marcar todos los campos como tocados
      for (const field in this.formData) {
        this.touched[field] = true;
      }

      // Validar
      if (!this.validateAll()) {
        // Scroll al primer error
        const firstError = this.$el.querySelector('.form-error:not(:empty)');
        if (firstError) {
          firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        return;
      }

      // Submit
      this.isSubmitting = true;

      try {
        await submitCallback(this.formData);
      } finally {
        this.isSubmitting = false;
      }
    },

    /**
     * Reset del formulario
     */
    reset() {
      for (const field in this.formData) {
        this.formData[field] = '';
        this.touched[field] = false;
        this.errors[field] = '';
      }
      this.isValid = false;
      this.isSubmitting = false;
    }
  }));

  /**
   * Directiva para auto-validación en blur
   */
  Alpine.directive('validate', (el, { expression }, { evaluate }) => {
    el.addEventListener('blur', () => {
      const fieldName = el.name;
      if (fieldName) {
        evaluate(`touch('${fieldName}')`);
      }
    });
  });
});
