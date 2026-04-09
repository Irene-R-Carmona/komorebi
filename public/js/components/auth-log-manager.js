/**
 * Auth Log Manager Component
 * Gestión de logs de autenticación y monitoreo de seguridad
 */
document.addEventListener('alpine:init', () => {
    Alpine.data('authLogManager', () => ({
        // Estado
        logs: [],
        users: [],
        stats: {},
        suspiciousActivity: [],
        loading: false,
        dismissedSuspicious: false, // Para persistir cierre de alerta en sesión

        // Filtros
        filters: {
            date_from: '',
            date_to: '',
            user_id: '',
            event_type: '',
            success: '',
            ip_address: ''
        },

        // Paginación
        currentPage: 1,
        perPage: 50,
        totalLogs: 0,

        // Dependencias
        service: globalThis.authLogService,
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

        get failureRate() {
            return this.fmt.failureRate(
                this.stats.successful_logins,
                this.stats.failed_logins
            );
        },

        get hasSuspiciousActivity() {
            return !this.dismissedSuspicious && this.suspiciousActivity.length > 0;
        },

        // Lifecycle
        async init() {
            await Promise.all([
                this.loadUsers(),
                this.loadStats(),
                this.loadLogs(),
                this.checkSuspiciousActivity()
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
                this.stats = data.data?.stats?.totals || {};
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

        async checkSuspiciousActivity() {
            try {
                const data = await this.service.fetchSuspiciousActivity();
                this.suspiciousActivity = data.data?.suspicious || [];
            } catch (error) {
                console.error('Error checking suspicious activity:', error);
                this.suspiciousActivity = [];
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
                event_type: '',
                success: '',
                ip_address: ''
            };
            this.currentPage = 1;
            this.refreshData();
        },

        refreshData() {
            this.loadLogs();
            this.loadStats();
        },

        dismissSuspiciousAlert() {
            this.dismissedSuspicious = true;
            // Opcional: persistir en sessionStorage
            sessionStorage.setItem('auth_logs_dismissed_suspicious', Date.now());
        },

        exportLogs() {
            this.service.exportLogs(this.filters);
        },

        // Helpers de template (delegados a Formatters)
        formatDateTime: (datetime) => Formatters.dateTimeSeconds(datetime),
        formatEventType: (event) => Formatters.authEventType(event),
        getEventBadgeClass: (event) => Formatters.authEventClass(event),
        formatDevice: (device) => Formatters.deviceWithIcon(device)
    }));
});