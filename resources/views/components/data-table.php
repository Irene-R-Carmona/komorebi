<?php

/**
 * Componente: Tabla de Datos Reutilizable
 *
 * Tabla con búsqueda, ordenamiento y paginación (Alpine.js)
 *
 * @param array $columns - Columnas de la tabla ['key' => 'label']
 * @param array $data - Datos a mostrar
 * @param string $tableId - ID único de la tabla
 * @param bool $searchable - Habilitar búsqueda
 * @param bool $sortable - Habilitar ordenamiento
 * @param int $perPage - Items por página
 */

$columns ??= [];
$data ??= [];
$tableId ??= 'dataTable';
$searchable ??= true;
$sortable ??= true;
$perPage ??= 10;
$emptyMessage ??= 'No hay datos para mostrar';
$actionsCallback ??= null; // Función para renderizar acciones
?>

<div x-data="dataTable({
    data: <?= json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP) ?>,
    columns: <?= json_encode(array_keys($columns), JSON_HEX_TAG | JSON_HEX_AMP) ?>,
    perPage: <?= $perPage ?>,
    searchable: <?= $searchable ? 'true' : 'false' ?>,
    sortable: <?= $sortable ? 'true' : 'false' ?>
})" class="data-table-component">

    <!-- Controles superiores -->
    <div class="row mb-3 align-items-center">
        <!-- Búsqueda -->
        <?php if ($searchable): ?>
            <div class="col-md-6">
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="bi bi-search"></i>
                    </span>
                    <input
                        type="text"
                        class="form-control"
                        placeholder="Buscar..."
                        x-model.debounce.300ms="searchQuery"
                        @input="currentPage = 1">
                </div>
            </div>
        <?php endif; ?>

        <!-- Items por página -->
        <div class="col-md-6 text-end">
            <div class="d-inline-flex align-items-center gap-2">
                <label class="mb-0 text-muted small">Mostrar:</label>
                <select
                    class="form-select form-select-sm"
                    style="width: auto;"
                    x-model.number="perPage"
                    @change="currentPage = 1">
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
                <span class="text-muted small">registros</span>
            </div>
        </div>
    </div>

    <!-- Tabla -->
    <div class="table-responsive">
        <table class="table table-hover align-middle" id="<?= $tableId ?>">
            <thead class="table-light">
                <tr>
                    <?php foreach ($columns as $key => $label): ?>
                        <th <?php if ($sortable): ?>
                            @click="sortBy('<?= $key ?>')"
                            style="cursor: pointer;"
                            class="user-select-none"
                            <?php endif; ?>>
                            <?= $label ?>
                            <?php if ($sortable): ?>
                                <i class="bi small ms-1"
                                    :class="{
                                       'bi-sort-down': sortColumn === '<?= $key ?>' && sortDirection === 'asc',
                                       'bi-sort-up': sortColumn === '<?= $key ?>' && sortDirection === 'desc',
                                       'bi-arrow-down-up text-muted': sortColumn !== '<?= $key ?>'
                                   }"></i>
                            <?php endif; ?>
                        </th>
                    <?php endforeach; ?>
                    <?php if ($actionsCallback): ?>
                        <th class="text-end">Acciones</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <template x-if="paginatedData.length === 0">
                    <tr>
                        <td colspan="<?= count($columns) + ($actionsCallback ? 1 : 0) ?>"
                            class="text-center text-muted py-4">
                            <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                            <?= $emptyMessage ?>
                        </td>
                    </tr>
                </template>

                <template x-for="(row, index) in paginatedData" :key="index">
                    <tr>
                        <?php foreach (array_keys($columns) as $key): ?>
                            <td x-html="formatCell('<?= $key ?>', row)"></td>
                        <?php endforeach; ?>
                        <?php if ($actionsCallback): ?>
                            <td class="text-end">
                                <!-- Las acciones se renderizan en la vista padre -->
                                <?= $actionsCallback ?>
                            </td>
                        <?php endif; ?>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>

    <!-- Paginación y info -->
    <div class="row align-items-center mt-3">
        <!-- Info -->
        <div class="col-md-6">
            <p class="text-muted small mb-0">
                Mostrando
                <strong x-text="startIndex + 1"></strong> -
                <strong x-text="Math.min(endIndex, filteredData.length)"></strong>
                de
                <strong x-text="filteredData.length"></strong>
                registros
                <template x-if="searchQuery">
                    (filtrados de <strong x-text="data.length"></strong> totales)
                </template>
            </p>
        </div>

        <!-- Paginación -->
        <div class="col-md-6">
            <nav aria-label="Navegación de tabla">
                <ul class="pagination pagination-sm justify-content-end mb-0">
                    <!-- Anterior -->
                    <li class="page-item" :class="{ 'disabled': currentPage === 1 }">
                        <button
                            class="page-link"
                            @click="currentPage--"
                            :disabled="currentPage === 1">
                            <i class="bi bi-chevron-left"></i>
                        </button>
                    </li>

                    <!-- Páginas -->
                    <template x-for="page in visiblePages" :key="page">
                        <li class="page-item" :class="{ 'active': page === currentPage }">
                            <button
                                class="page-link"
                                @click="currentPage = page"
                                x-text="page"></button>
                        </li>
                    </template>

                    <!-- Siguiente -->
                    <li class="page-item" :class="{ 'disabled': currentPage === totalPages }">
                        <button
                            class="page-link"
                            @click="currentPage++"
                            :disabled="currentPage === totalPages">
                            <i class="bi bi-chevron-right"></i>
                        </button>
                    </li>
                </ul>
            </nav>
        </div>
    </div>
</div>
</div>
