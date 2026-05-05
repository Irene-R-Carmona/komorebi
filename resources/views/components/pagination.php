<!-- Componente de Paginaci\u00f3n Reutilizable -->
<!-- Uso: include este componente en vistas que necesiten paginaci\u00f3n -->
<!-- Requiere variable $pagination con: current_page, total_pages, has_prev, has_next -->

<?php
if (!isset($pagination) || $pagination['total_pages'] <= 1) {
    return; // No mostrar si no hay paginaci\u00f3n o solo hay 1 p\u00e1gina
}

$currentPage = $pagination['current_page'];
$totalPages = $pagination['total_pages'];
$hasPrev = $pagination['has_prev'];
$hasNext = $pagination['has_next'];

// Generar array de p\u00e1ginas a mostrar
$pages = [];
$range = 2; // Mostrar 2 p\u00e1ginas antes y despu\u00e9s de la actual

// Siempre mostrar primera p\u00e1gina
$pages[] = 1;

// P\u00e1ginas alrededor de la actual
$start = max(2, $currentPage - $range);
$end = min($totalPages - 1, $currentPage + $range);

if ($start > 2) {
    $pages[] = '...';
}

for ($i = $start; $i <= $end; $i++) {
    $pages[] = $i;
}

if ($end < $totalPages - 1) {
    $pages[] = '...';
}

// Siempre mostrar \u00faltima p\u00e1gina (si es diferente de la primera)
if ($totalPages > 1) {
    $pages[] = $totalPages;
}

// Construir query string preservando filtros existentes
$queryParams = $_GET;
unset($queryParams['page']); // Remover page para construir desde cero

$buildUrl = function ($page) use ($queryParams) {
    $params = array_merge($queryParams, ['page' => $page]);

    return e('?' . http_build_query($params));
};
?>

<nav class="pagination" role="navigation" aria-label="Paginaci\u00f3n">
    <div class="pagination__info">
        <p class="pagination__text">
            P\u00e1gina <strong><?= $currentPage ?></strong> de <strong><?= $totalPages ?></strong>
            <?php if (isset($pagination['total'])): ?>
                (<?= number_format($pagination['total']) ?> resultados)
            <?php endif; ?>
        </p>
    </div>

    <ul class="pagination__list">
        <!-- Bot\u00f3n Anterior -->
        <li class="pagination__item">
            <?php if ($hasPrev): ?>
                <a href="<?= $buildUrl($currentPage - 1) ?>"
                    class="pagination__link pagination__link--prev"
                    aria-label="P\u00e1gina anterior">
                    <span aria-hidden="true">\u00ab</span>
                    <span class="pagination__label">Anterior</span>
                </a>
            <?php else: ?>
                <span class="pagination__link pagination__link--prev pagination__link--disabled"
                    aria-disabled="true">
                    <span aria-hidden="true">\u00ab</span>
                    <span class="pagination__label">Anterior</span>
                </span>
            <?php endif; ?>
        </li>

        <!-- P\u00e1ginas -->
        <?php foreach ($pages as $page): ?>
            <?php if ($page === '...'): ?>
                <li class="pagination__item pagination__item--ellipsis">
                    <span class="pagination__link pagination__link--disabled" aria-hidden="true">\u2026</span>
                </li>
            <?php elseif ($page === $currentPage): ?>
                <li class="pagination__item">
                    <span class="pagination__link pagination__link--active"
                        aria-current="page"
                        aria-label="P\u00e1gina actual, p\u00e1gina <?= $page ?>">
                        <?= $page ?>
                    </span>
                </li>
            <?php else: ?>
                <li class="pagination__item">
                    <a href="<?= $buildUrl($page) ?>"
                        class="pagination__link"
                        aria-label="Ir a la p\u00e1gina <?= $page ?>">
                        <?= $page ?>
                    </a>
                </li>
            <?php endif; ?>
        <?php endforeach; ?>

        <!-- Bot\u00f3n Siguiente -->
        <li class="pagination__item">
            <?php if ($hasNext): ?>
                <a href="<?= $buildUrl($currentPage + 1) ?>"
                    class="pagination__link pagination__link--next"
                    aria-label="P\u00e1gina siguiente">
                    <span class="pagination__label">Siguiente</span>
                    <span aria-hidden="true">\u00bb</span>
                </a>
            <?php else: ?>
                <span class="pagination__link pagination__link--next pagination__link--disabled"
                    aria-disabled="true">
                    <span class="pagination__label">Siguiente</span>
                    <span aria-hidden="true">\u00bb</span>
                </span>
            <?php endif; ?>
        </li>
    </ul>

    <!-- Selector de resultados por p\u00e1gina (opcional) -->
    <?php if (isset($pagination['per_page'])): ?>
        <div class="pagination__per-page" x-data="{ perPage: <?= $pagination['per_page'] ?> }">
            <label for="per-page-select" class="pagination__label">
                Mostrar:
            </label>
            <select
                id="per-page-select"
                class="pagination__select"
                x-model="perPage"
                @change="window.location.href = '<?= $buildUrl($currentPage) ?>&per_page=' + perPage">
                <option value="10" :selected="perPage === 10">10</option>
                <option value="20" :selected="perPage === 20">20</option>
                <option value="50" :selected="perPage === 50">50</option>
                <option value="100" :selected="perPage === 100">100</option>
            </select>
            <span class="pagination__label">por p\u00e1gina</span>
        </div>
    <?php endif; ?>
</nav>

<style>
    /* Estilos del componente de paginaci\u00f3n */
    .pagination {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: var(--espaciado-md);
        margin-top: var(--espaciado-lg);
        padding: var(--espaciado-md);
        background: var(--color-superficie);
        border-radius: var(--radio-md);
    }

    .pagination__info {
        flex: 1;
        min-width: 200px;
    }

    .pagination__text {
        margin: 0;
        color: var(--color-texto-suave);
        font-size: 0.875rem;
    }

    .pagination__list {
        display: flex;
        flex-wrap: wrap;
        gap: 0.25rem;
        list-style: none;
        margin: 0;
        padding: 0;
    }

    .pagination__item {
        margin: 0;
    }

    .pagination__link {
        display: flex;
        align-items: center;
        gap: 0.25rem;
        min-width: 2.5rem;
        min-height: 2.5rem;
        padding: 0.5rem 0.75rem;
        border: 1px solid var(--color-borde);
        border-radius: var(--radio-sm);
        background: white;
        color: var(--color-texto);
        font-size: 0.875rem;
        font-weight: 500;
        text-align: center;
        text-decoration: none;
        transition: all var(--transicion-rapida);
        cursor: pointer;
    }

    .pagination__link:hover:not(.pagination__link--disabled):not(.pagination__link--active) {
        background: var(--color-fondo-alt);
        border-color: var(--color-acento);
        color: var(--color-primario);
        transform: translateY(-1px);
    }

    .pagination__link:focus-visible {
        outline: 2px solid var(--color-acento);
        outline-offset: 2px;
    }

    .pagination__link--active {
        background: var(--color-primario);
        border-color: var(--color-primario);
        color: white;
        font-weight: 600;
    }

    .pagination__link--disabled {
        background: var(--color-fondo);
        color: var(--color-texto-suave);
        opacity: 0.5;
        cursor: not-allowed;
    }

    .pagination__item--ellipsis .pagination__link {
        border: none;
        background: transparent;
    }

    .pagination__per-page {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .pagination__select {
        padding: 0.5rem;
        border: 1px solid var(--color-borde);
        border-radius: var(--radio-sm);
        font-size: 0.875rem;
    }

    .pagination__label {
        font-size: 0.875rem;
        color: var(--color-texto-suave);
    }

    /* Responsive */
    @media (max-width: 768px) {
        .pagination {
            flex-direction: column;
            align-items: stretch;
        }

        .pagination__info {
            text-align: center;
        }

        .pagination__list {
            justify-content: center;
        }

        .pagination__per-page {
            justify-content: center;
        }

        .pagination__link--prev .pagination__label,
        .pagination__link--next .pagination__label {
            display: none;
        }
    }
</style>
