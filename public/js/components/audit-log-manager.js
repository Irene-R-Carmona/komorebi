/**
 * Audit Log Manager Component
 * Lógica de estado para gestión de logs de auditoría
 * Depende de: AuditLogService, Formatters
 */
document.addEventListener('alpine:init', () => {
    Alpine.data('auditLogManager', () => ({
        // Estado
        logs: [],
        users: [],
        stats: {},
        loading: false,
        selectedLog: null,

        // Filtros
        filters: {
            date_from: '',
            date_to: '',
            user_id: '',
            action: '',
            resource_type: '',
            ip_address: ''
        },

        // Paginación
        currentPage: 1,
        perPage: 50,
        totalLogs: 0,

        // Dependencias inyectadas (para testing)
        service: globalThis.auditLogService,
        fmt: globalThis.Formatters,

        // Computed properties
        get totalPages() {
            return Math.ceil(this.totalLogs / this.perPage);
        },

        get visiblePages() {
            return this.fmt.visiblePages(this.currentPage, this.totalPages);
        },

        get hasLogs() {
            return this.logs.length > 0;
        },

        // Lifecycle
        async init() {
            await Promise.all([
                this.loadUsers(),
                this.loadStats(),
                this.loadLogs()
            ]);
        },

        // Cargar datos
        async loadUsers() {
            try {
                const data = await this.service.fetchUsers();
                this.users = data.data?.users || [];
            } catch (error) {
                console.error('Error loading users:', error);
                this.users = [];
            }
        },

        async loadStats() {
            try {
                const data = await this.service.fetchStats(this.filters);
                const stats = data.data?.stats || {};
                this.stats = {
                    ...stats.totals,
                    top_action: stats.top_actions?.[0]?.action || 'N/A'
                };
            } catch (error) {
                console.error('Error loading stats:', error);
                this.stats = {};
            }
        },

        async loadLogs() {
            this.loading = true;
            try {
                const data = await this.service.fetchLogs(
                    this.filters,
                    this.currentPage,
                    this.perPage
                );

                this.logs = data.data?.logs || [];
                this.totalLogs = data.data?.total || 0;
            } catch (error) {
                console.error('Error loading logs:', error);
                this.logs = [];
                this.totalLogs = 0;
            } finally {
                this.loading = false;
            }
        },

        // Acciones de usuario
        changePage(page) {
            if (page >= 1 && page <= this.totalPages && page !== this.currentPage) {
                this.currentPage = page;
                this.loadLogs();
            }
        },

        clearFilters() {
            this.filters = {
                date_from: '',
                date_to: '',
                user_id: '',
                action: '',
                resource_type: '',
                ip_address: ''
            };
            this.currentPage = 1;
            this.refreshData();
        },

        refreshData() {
            this.loadLogs();
            this.loadStats();
        },

        viewDetails(log) {
            this.selectedLog = log;
            const modal = new bootstrap.Modal(document.getElementById('logDetailsModal'));
            modal.show();
        },

        exportLogs() {
            this.service.exportLogs(this.filters);
        },

        // Helpers de template (delegados a Formatters)
        formatDateTime: (datetime) => Formatters.dateTime(datetime),
        formatAction: (action) => Formatters.actionName(action),
        getActionBadgeClass: (action) => Formatters.actionBadgeClass(action),
        formatJSON: (data) => Formatters.json(data)
    }));
});