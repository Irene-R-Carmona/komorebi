/**
 * Auth Log Service
 * Gestión de logs de autenticación y seguridad
 */
class AuthLogService {
  constructor(baseUrl = '/api/v1/admin/logs/auth') {
    this.baseUrl = baseUrl;
    this.headers = {
      'Accept': 'application/json',
      'X-Requested-With': 'XMLHttpRequest'
    };
  }

  async fetchLogs(filters, page = 1, limit = 50) {
    const params = new URLSearchParams({
      ...filters,
      page: page.toString(),
      limit: limit.toString()
    });

    const response = await fetch(`${this.baseUrl}?${params}`, {
      headers: this.headers
    });

    if (!response.ok) throw new Error(`HTTP ${response.status}`);
    return response.json();
  }

  async fetchStats(filters) {
    const params = new URLSearchParams(filters);
    const response = await fetch(`${this.baseUrl}/stats?${params}`, {
      headers: this.headers
    });

    if (!response.ok) throw new Error(`HTTP ${response.status}`);
    return response.json();
  }

  async fetchSuspiciousActivity() {
    const response = await fetch(`${this.baseUrl}/suspicious`, {
      headers: this.headers
    });

    if (!response.ok) throw new Error(`HTTP ${response.status}`);
    return response.json();
  }

  async fetchUsers() {
    const response = await fetch('/admin/usuarios/list', {
      headers: this.headers
    });

    if (!response.ok) throw new Error(`HTTP ${response.status}`);
    return response.json();
  }

  exportLogs(filters) {
    const params = new URLSearchParams(filters);
    globalThis.location.href = `${this.baseUrl}/export?${params}`;
  }
}

globalThis.authLogService = new AuthLogService();
