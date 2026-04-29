/**
 * Componente Alpine.js para selección de avatar preset
 *
 * Reemplaza el sistema de subida de archivos por un selector
 * de avatares predefinidos (initials + 8 presets SVG).
 */

const avatarUploadComponent = () => ({
  // State
  isUploading: false,
  showPicker: false,
  currentAvatar: null,
  options: [],
  error: null,
  success: null,

  async init() {
    this.currentAvatar = this.$el.dataset.currentAvatar || null;
    try {
      const res = await fetch('/api/v1/user/avatar-options', { credentials: 'same-origin' });
      if (res.ok) {
        const json = await res.json();
        this.options = json.data ?? [];
      }
    } catch (_) {
      // Opciones no disponibles — se mostrará vacío
    }
  },

  openPicker() {
    this.showPicker = true;
    this.error = null;
    this.success = null;
  },

  closePicker() {
    this.showPicker = false;
  },

  async selectAvatar(avatarId) {
    this.isUploading = true;
    this.error = null;
    this.success = null;

    try {
      const response = await fetch('/account/avatar/upload', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({
          avatar_id: avatarId,
          csrf_token: this.getCsrfToken(),
        }),
      });

      const data = await response.json();

      if (!response.ok || !data.ok) {
        throw new Error(data.message || 'Error al actualizar el avatar');
      }

      this.currentAvatar = data.data?.avatar_url ?? null;
      this.success = 'Avatar actualizado correctamente';
      this.showPicker = false;

      // Actualizar avatar en el header si existe
      this.updateHeaderAvatar(this.currentAvatar, avatarId);

      // Actualizar la propiedad profile del componente padre si existe
      if (this.$store && this.$store.profile) {
        this.$store.profile.avatar_url = this.currentAvatar;
      }

      setTimeout(() => { this.success = null; }, 3000);

    } catch (err) {
      this.error = err instanceof Error ? err.message : 'Error al actualizar el avatar';
    } finally {
      this.isUploading = false;
    }
  },

  async deleteAvatar() {
    if (!confirm('¿Estás seguro de eliminar tu avatar?')) return;

    this.isUploading = true;
    this.error = null;

    try {
      const response = await fetch('/account/avatar/delete', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ csrf_token: this.getCsrfToken() }),
      });

      const data = await response.json();

      if (!response.ok) {
        throw new Error(data.message || 'Error al eliminar el avatar');
      }

      this.currentAvatar = null;
      this.success = 'Avatar eliminado correctamente';
      this.updateHeaderAvatar(null, null);

      setTimeout(() => { this.success = null; }, 3000);

    } catch (err) {
      this.error = err instanceof Error ? err.message : 'Error al eliminar el avatar';
    } finally {
      this.isUploading = false;
    }
  },

  getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    const input = document.querySelector('input[name="csrf_token"]');
    return (meta && meta.content) || (input && input.value) || '';
  },

  updateHeaderAvatar(avatarUrl, avatarId) {
    const headerAvatar = document.querySelector('.header__avatar img');
    if (!headerAvatar) return;
    if (avatarUrl) {
      headerAvatar.src = avatarUrl;
    } else {
      const initial = headerAvatar.dataset.initial || '?';
      headerAvatar.src = this.getInitialSvg(initial);
    }
  },

  getInitialSvg(initial) {
    const svg = `<svg width="40" height="40" xmlns="http://www.w3.org/2000/svg">
      <circle cx="20" cy="20" r="20" fill="#5C3D2E"/>
      <text x="50%" y="50%" text-anchor="middle" dy=".35em"
            fill="white" font-size="18" font-family="Arial">
        ${initial.toUpperCase()}
      </text>
    </svg>`;
    return 'data:image/svg+xml;base64,' + btoa(svg);
  },

  get avatarSrc() {
    return this.currentAvatar || null;
  },
});

// Exponer globalmente
globalThis.avatarUpload = avatarUploadComponent;

document.addEventListener('alpine:init', () => {
  if (globalThis.Alpine && globalThis.Alpine.data) {
    globalThis.Alpine.data('avatarUpload', avatarUploadComponent);
  }
});

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
