/**
 * Componente Alpine.js para subida de avatar
 *
 * Funcionalidades:
 * - Drag & drop
 * - Preview de imagen
 * - Validación cliente
 * - Upload con progreso
 */

// Definir el componente en una función reutilizable
const avatarUploadComponent = () => ({
  // State
  isUploading: false,
  isDragging: false,
  currentAvatar: null,
  previewUrl: null,
  error: null,
  success: null,

  // Configuración
  maxSize: 2 * 1024 * 1024, // 2 MB
  allowedTypes: ['image/jpeg', 'image/png', 'image/webp'],
  allowedExtensions: ['jpg', 'jpeg', 'png', 'webp'],

  // Inicialización
  init() {
    this.currentAvatar = this.$el.dataset.currentAvatar || null;
  },

  // Handlers de drag & drop
  handleDragEnter(e) {
    e.preventDefault();
    this.isDragging = true;
  },

  handleDragLeave(e) {
    e.preventDefault();
    this.isDragging = false;
  },

  handleDragOver(e) {
    e.preventDefault();
  },

  handleDrop(e) {
    e.preventDefault();
    this.isDragging = false;

    const files = e.dataTransfer.files;
    if (files.length > 0) {
      this.handleFile(files[0]);
    }
  },

  // Handler de input file
  handleFileInput(e) {
    const files = e.target.files;
    if (files.length > 0) {
      this.handleFile(files[0]);
    }
  },

  // Validar y procesar archivo
  handleFile(file) {
    this.error = null;
    this.success = null;

    // Validar tamaño
    if (file.size > this.maxSize) {
      const maxSizeMB = this.maxSize / 1024 / 1024;
      this.error = `El archivo debe ser menor a ${maxSizeMB}MB`;
      return;
    }

    // Validar tipo
    if (!this.allowedTypes.includes(file.type)) {
      this.error = 'Formato no válido. Usa JPG, PNG o WebP';
      return;
    }

    // Validar extensión
    const fileName = file.name.toLowerCase();
    const hasValidExtension = this.allowedExtensions.some(ext => fileName.endsWith('.' + ext));
    if (!hasValidExtension) {
      this.error = 'Extensión de archivo no válida';
      return;
    }

    // Generar preview
    this.generatePreview(file);

    // Subir archivo
    this.uploadFile(file);
  },

  // Generar preview de imagen
  generatePreview(file) {
    const reader = new FileReader();

    reader.onload = (e) => {
      this.previewUrl = e.target.result;
    };

    reader.onerror = () => {
      this.error = 'Error al leer el archivo';
    };

    reader.readAsDataURL(file);
  },

  // Subir archivo al servidor
  async uploadFile(file) {
    this.isUploading = true;
    this.error = null;

    const formData = new FormData();
    formData.append('avatar', file);
    formData.append('csrf_token', this.getCsrfToken());

    try {
      const response = await fetch('/account/avatar/upload', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
      });

      const data = await response.json();

      if (!response.ok) {
        throw new Error(data.message || 'Error al subir el avatar');
      }

      // Éxito
      this.success = data.message || 'Avatar actualizado correctamente';
      this.currentAvatar = data.avatar_url;

      // Actualizar avatar en el header si existe
      this.updateHeaderAvatar(data.avatar_url);

      // Limpiar preview después de 2 segundos
      setTimeout(() => {
        this.previewUrl = null;
        this.success = null;
      }, 2000);

    } catch (error) {
      this.error = error.message;
      this.previewUrl = null;
    } finally {
      this.isUploading = false;
    }
  },

  // Eliminar avatar
  async deleteAvatar() {
    if (!confirm('¿Estás seguro de eliminar tu avatar?')) {
      return;
    }

    this.isUploading = true;
    this.error = null;

    try {
      const response = await fetch('/account/avatar/delete', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          csrf_token: this.getCsrfToken()
        }),
        credentials: 'same-origin'
      });

      const data = await response.json();

      if (!response.ok) {
        throw new Error(data.message || 'Error al eliminar el avatar');
      }

      // Éxito
      this.success = data.message || 'Avatar eliminado correctamente';
      this.currentAvatar = null;
      this.previewUrl = null;

      // Actualizar avatar en el header
      this.updateHeaderAvatar(null);

    } catch (error) {
      this.error = error.message;
    } finally {
      this.isUploading = false;
    }
  },

  // Obtener token CSRF
  getCsrfToken() {
    const metaTag = document.querySelector('meta[name="csrf-token"]');
    const inputTag = document.querySelector('input[name="csrf_token"]');
    return (metaTag && metaTag.content) || (inputTag && inputTag.value) || '';
  },

  // Actualizar avatar en el header
  updateHeaderAvatar(avatarUrl) {
    const headerAvatar = document.querySelector('.header__avatar img');
    if (headerAvatar) {
      if (avatarUrl) {
        headerAvatar.src = avatarUrl;
      } else {
        // Volver a mostrar inicial
        const userInitial = headerAvatar.dataset.initial || '?';
        headerAvatar.src = this.getInitialAvatar(userInitial);
      }
    }
  },

  // Generar avatar con inicial
  getInitialAvatar(initial) {
    // SVG simple con inicial
    const svg = `
      <svg width="40" height="40" xmlns="http://www.w3.org/2000/svg">
        <circle cx="20" cy="20" r="20" fill="#5C3D2E"/>
        <text x="50%" y="50%" text-anchor="middle" dy=".35em"
              fill="white" font-size="18" font-family="Arial">
          ${initial.toUpperCase()}
        </text>
      </svg>
    `;
    return 'data:image/svg+xml;base64,' + btoa(svg);
  },

  // Resetear formulario
  reset() {
    this.previewUrl = null;
    this.error = null;
    this.success = null;
    this.$refs.fileInput.value = '';
  }
});

// Exponer globalmente
globalThis.avatarUpload = avatarUploadComponent;

// Registrar directamente con Alpine en el evento init
document.addEventListener('alpine:init', () => {
  if (globalThis.Alpine && globalThis.Alpine.data) {
    globalThis.Alpine.data('avatarUpload', avatarUploadComponent);
  }
});
