// Componente Alpine dataTable externo
(function () {
  document.addEventListener('alpine:init', () => {
    Alpine.data('dataTable', (config) => ({
      data: config.data || [],
      columns: config.columns || [],
      perPage: config.perPage || 10,
      searchable: config.searchable || true,
      sortable: config.sortable || true,

      searchQuery: '',
      sortColumn: '',
      sortDirection: 'asc',
      currentPage: 1,

      get filteredData() {
        if (!this.searchQuery) return this.data;
        const query = this.searchQuery.toLowerCase();
        return this.data.filter(row => this.columns.some(col => String(row[col] || '').toLowerCase().includes(query)));
      },

      get sortedData() {
        if (!this.sortColumn) return this.filteredData;
        return [...this.filteredData].sort((a, b) => {
          const aVal = a[this.sortColumn];
          const bVal = b[this.sortColumn];
          if (aVal === bVal) return 0;
          const comparison = aVal < bVal ? -1 : 1;
          return this.sortDirection === 'asc' ? comparison : -comparison;
        });
      },

      get totalPages() { return Math.ceil(this.sortedData.length / this.perPage); },
      get startIndex() { return (this.currentPage - 1) * this.perPage; },
      get endIndex() { return this.startIndex + this.perPage; },
      get paginatedData() { return this.sortedData.slice(this.startIndex, this.endIndex); },

      get visiblePages() {
        const pages = []; const maxVisible = 5;
        let start = Math.max(1, this.currentPage - 2);
        let end = Math.min(this.totalPages, start + maxVisible - 1);
        if (end - start < maxVisible - 1) start = Math.max(1, end - maxVisible + 1);
        for (let i = start; i <= end; i++) pages.push(i);
        return pages;
      },

      sortBy(column) {
        if (this.sortColumn === column) this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
        else { this.sortColumn = column; this.sortDirection = 'asc'; }
        this.currentPage = 1;
      },

      formatCell(column, row) {
        const value = row[column];
        if (column === 'is_active' || column === 'active') return value ? '<span class="badge bg-success">Activo</span>' : '<span class="badge bg-danger">Inactivo</span>';
        if (column === 'created_at' || column === 'updated_at') { if (!value) return '-'; const date = new Date(value); return date.toLocaleDateString('es-ES'); }
        if (value === null || value === undefined || value === '') return '-';
        return String(value).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
      },

      updateData(newData) { this.data = newData; this.currentPage = 1; }
    }));
  });
})();
