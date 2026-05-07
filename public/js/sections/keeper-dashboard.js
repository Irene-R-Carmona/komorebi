/**
 * JavaScript para Dashboard de Keeper
 *
 * Maneja la funcionalidad del dashboard de bienestar animal:
 * - Subida de fotos
 * - Logs de cuidado
 * - Cambios de estado
 * - Gestión de incidentes
 */

document.addEventListener('DOMContentLoaded', function () {
  initializeDashboard();
});

function initializeDashboard() {
  // Inicializar modales
  initializePhotoUpload();
  initializeCareLogging();
  initializeStatusChanges();
  initializeIncidents();

  // Inicializar tooltips de Bootstrap
  const tooltipTriggerList = Array.prototype.slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
  tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
  });
}

/**
 * Inicializar funcionalidad de subida de fotos
 */
function initializePhotoUpload() {
  const uploadButtons = document.querySelectorAll('.upload-photo-btn');
  const modal = new bootstrap.Modal(document.getElementById('uploadPhotoModal'));
  const form = document.getElementById('uploadPhotoForm');
  const fileInput = document.getElementById('photo-file');
  const preview = document.getElementById('photo-preview');
  const previewImg = document.getElementById('preview-img');
  const uploadBtn = document.getElementById('upload-btn');
  const spinner = uploadBtn.querySelector('.spinner-border');

  // Event listeners para botones de subir foto
  uploadButtons.forEach(button => {
    button.addEventListener('click', function () {
      const animalId = this.dataset.animalId;
      const animalName = this.dataset.animalName;

      document.getElementById('upload-animal-id').value = animalId;
      document.getElementById('animal-name-modal').textContent = animalName;

      // Reset form
      form.reset();
      preview.classList.add('d-none');
      fileInput.value = '';

      modal.show();
    });
  });

  // Preview de imagen
  fileInput.addEventListener('change', function (e) {
    const file = e.target.files[0];
    if (file) {
      // Validar tipo de archivo
      if (!file.type.startsWith('image/')) {
        showToast('Por favor selecciona un archivo de imagen válido.', 'error');
        fileInput.value = '';
        return;
      }

      // Validar tamaño (5MB máximo)
      if (file.size > 5 * 1024 * 1024) {
        showToast('La imagen es demasiado grande. Máximo 5MB.', 'error');
        fileInput.value = '';
        return;
      }

      // Mostrar preview
      const reader = new FileReader();
      reader.onload = function (e) {
        previewImg.src = e.target.result;
        preview.classList.remove('d-none');
      };
      reader.readAsDataURL(file);
    } else {
      preview.classList.add('d-none');
    }
  });

  // Submit del formulario
  form.addEventListener('submit', async function (e) {
    e.preventDefault();

    const formData = new FormData(this);

    uploadBtn.disabled = true;
    spinner.classList.remove('d-none');

    try {
      const animalId = formData.get('animal_id') || document.getElementById('upload-animal-id').value;
      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
      formData.set('csrf_token', csrfToken);

      const response = await fetch(`/api/v1/keeper/animals/${animalId}/photo`, {
        method: 'POST',
        headers: { 'X-CSRF-Token': csrfToken },
        body: formData,
      });

      const result = await response.json();

      if (response.ok && result.ok) {
        showToast(result.data?.message || 'Foto subida correctamente.', 'success');
        modal.hide();
        document.dispatchEvent(new CustomEvent('keeper:refresh'));
      } else {
        showToast(result.detail || result.error || 'Error al subir la foto.', 'error');
      }
    } catch (error) {
      console.error('Upload error:', error);
      showToast('Error de conexión al subir la foto.', 'error');
    } finally {
      uploadBtn.disabled = false;
      spinner.classList.add('d-none');
    }
  });
}

/**
 * Inicializar funcionalidad de logs de cuidado
 */
function initializeCareLogging() {
  const logButtons = document.querySelectorAll('.log-care-btn');
  const modal = new bootstrap.Modal(document.getElementById('logCareModal'));
  const form = document.getElementById('logCareForm');
  const logBtn = document.getElementById('log-btn');
  const spinner = logBtn.querySelector('.spinner-border');

  // Event listeners para botones de log
  logButtons.forEach(button => {
    button.addEventListener('click', function () {
      const animalId = this.dataset.animalId;
      const animalName = this.dataset.animalName;

      document.getElementById('log-animal-id').value = animalId;
      document.getElementById('log-animal-name').textContent = animalName;

      // Reset form
      form.reset();

      modal.show();
    });
  });

  // Submit del formulario
  form.addEventListener('submit', async function (e) {
    e.preventDefault();

    const formData = new FormData(this);

    logBtn.disabled = true;
    spinner.classList.remove('d-none');

    try {
      const animalId = formData.get('animal_id');
      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
      const body = {
        activity_type: formData.get('activity_type'),
        notes: formData.get('notes'),
        duration_minutes: formData.get('duration_minutes') ? Number(formData.get('duration_minutes')) : null,
        mood_before: formData.get('mood_before'),
        mood_after: formData.get('mood_after'),
      };

      const response = await fetch(`/api/v1/keeper/animals/${animalId}/care-log`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
        body: JSON.stringify(body),
      });

      const result = await response.json();

      if (response.ok && result.ok) {
        showToast(result.data?.message || 'Log registrado correctamente.', 'success');
        modal.hide();
        document.dispatchEvent(new CustomEvent('keeper:refresh'));
      } else {
        showToast(result.detail || result.error || 'Error al registrar el log.', 'error');
      }
    } catch (error) {
      console.error('Log error:', error);
      showToast('Error de conexión al registrar el log.', 'error');
    } finally {
      logBtn.disabled = false;
      spinner.classList.add('d-none');
    }
  });
}

/**
 * Inicializar funcionalidad de cambios de estado
 */
function initializeStatusChanges() {
  const statusButtons = document.querySelectorAll('.change-status-btn');
  const modal = new bootstrap.Modal(document.getElementById('changeStatusModal'));
  const form = document.getElementById('changeStatusForm');
  const statusBtn = document.getElementById('status-btn');
  const spinner = statusBtn.querySelector('.spinner-border');

  // Event listeners para botones de cambio de estado
  statusButtons.forEach(button => {
    button.addEventListener('click', function () {
      const animalId = this.dataset.animalId;
      const currentStatus = this.dataset.status;

      document.getElementById('status-animal-id').value = animalId;
      document.getElementById('health_status').value = currentStatus;

      // Reset form
      form.reset();
      document.getElementById('health_status').value = currentStatus;

      modal.show();
    });
  });

  // Submit del formulario
  form.addEventListener('submit', async function (e) {
    e.preventDefault();

    const formData = new FormData(this);

    statusBtn.disabled = true;
    spinner.classList.remove('d-none');

    try {
      const animalId = formData.get('animal_id');
      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
      const body = {
        health_status: formData.get('health_status'),
        notes: formData.get('notes'),
      };

      const response = await fetch(`/api/v1/keeper/animals/${animalId}/health`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
        body: JSON.stringify(body),
      });

      const result = await response.json();

      if (response.ok && result.ok) {
        showToast(result.data?.message || 'Estado actualizado correctamente.', 'success');
        modal.hide();
        document.dispatchEvent(new CustomEvent('keeper:refresh'));
      } else {
        showToast(result.detail || result.error || 'Error al actualizar el estado.', 'error');
      }
    } catch (error) {
      console.error('Status change error:', error);
      showToast('Error de conexión al cambiar el estado.', 'error');
    } finally {
      statusBtn.disabled = false;
      spinner.classList.add('d-none');
    }
  });
}

/**
 * Inicializar funcionalidad de incidentes
 */
function initializeIncidents() {
  const resolveButtons = document.querySelectorAll('.resolve-incident-btn');
  const resolveModal = new bootstrap.Modal(document.getElementById('resolveIncidentModal'));
  const resolveForm = document.getElementById('resolveIncidentForm');
  const resolveBtn = document.getElementById('resolve-btn');
  const resolveSpinner = resolveBtn.querySelector('.spinner-border');

  const incidentForm = document.getElementById('incidentForm');
  const incidentBtn = document.getElementById('incident-btn');
  const incidentSpinner = incidentBtn.querySelector('.spinner-border');

  // Event listeners para botones de resolver incidente
  resolveButtons.forEach(button => {
    button.addEventListener('click', function () {
      const incidentId = this.dataset.incidentId;

      document.getElementById('resolve-incident-id').value = incidentId;

      // Reset form
      resolveForm.reset();

      resolveModal.show();
    });
  });

  // Submit del formulario de resolución
  resolveForm.addEventListener('submit', async function (e) {
    e.preventDefault();

    const formData = new FormData(this);
    const incidentId = formData.get('incident_id');

    resolveBtn.disabled = true;
    resolveSpinner.classList.remove('d-none');

    try {
      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

      const response = await fetch(`/api/v1/keeper/incidents/${incidentId}/resolve`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
        body: JSON.stringify({ resolution: formData.get('resolution') }),
      });

      const result = await response.json();

      if (response.ok && result.ok) {
        showToast(result.data?.message || 'Incidente resuelto correctamente.', 'success');
        resolveModal.hide();
        document.dispatchEvent(new CustomEvent('keeper:refresh'));
      } else {
        showToast(result.detail || result.error || 'Error al resolver el incidente.', 'error');
      }
    } catch (error) {
      console.error('Resolve incident error:', error);
      showToast('Error de conexión al resolver el incidente.', 'error');
    } finally {
      resolveBtn.disabled = false;
      resolveSpinner.classList.add('d-none');
    }
  });

  // Submit del formulario de reporte de incidente
  incidentForm.addEventListener('submit', async function (e) {
    e.preventDefault();

    const formData = new FormData(this);

    incidentBtn.disabled = true;
    incidentSpinner.classList.remove('d-none');

    try {
      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
      const body = {
        animal_id: Number(formData.get('animal_id')),
        severity: formData.get('severity'),
        description: formData.get('description'),
      };

      const response = await fetch('/api/v1/keeper/incidents', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
        body: JSON.stringify(body),
      });

      const result = await response.json();

      if (response.ok && result.ok) {
        showToast(result.data?.message || 'Incidente reportado correctamente.', 'success');
        incidentForm.reset();
        bootstrap.Modal.getInstance(document.getElementById('incidentModal')).hide();
        document.dispatchEvent(new CustomEvent('keeper:refresh'));
      } else {
        showToast(result.detail || result.error || 'Error al reportar el incidente.', 'error');
      }
    } catch (error) {
      console.error('Report incident error:', error);
      showToast('Error de conexión al reportar el incidente.', 'error');
    } finally {
      incidentBtn.disabled = false;
      incidentSpinner.classList.add('d-none');
    }
  });
}

/**
 * Mostrar toast de notificación
 */
function showToast(message, type = 'info') {
  const toastId = type === 'success' ? 'successToast' : 'errorToast';
  const messageId = type === 'success' ? 'successMessage' : 'errorMessage';

  document.getElementById(messageId).textContent = message;

  const toast = new bootstrap.Toast(document.getElementById(toastId));
  toast.show();
}
