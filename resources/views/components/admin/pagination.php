<?php
/**
 * Componente: Paginación
 *
 * Paginación reutilizable con soporte para Alpine.js.
 *
 * @var bool $alpine - Si true, usa variables Alpine.js
 * @var int $currentPage - Página actual (solo si $alpine = false)
 * @var int $totalPages - Total de páginas (solo si $alpine = false)
 * @var string $baseUrl - URL base para enlaces (solo si $alpine = false)
 *
 * Variables Alpine esperadas cuando $alpine = true:
 * - currentPage: número de página actual
 * - totalPages: número total de páginas
 * - visiblePages: array de páginas visibles
 * - goToPage(page): función para cambiar de página
 */

$alpine ??= false;
$currentPage ??= 1;
$totalPages ??= 1;
$baseUrl ??= '';
?>

<?php if ($alpine): ?>
    <!-- Versión Alpine.js -->
    <nav aria-label="Paginación" x-show="totalPages > 1">
        <ul class="pagination pagination-sm mb-0">
            <!-- Anterior -->
            <li class="page-item" :class="{ 'disabled': currentPage === 1 }">
                <button
                        type="button"
                        class="page-link"
                        @click="goToPage(currentPage - 1)"
                        :disabled="currentPage === 1"
                        aria-label="Anterior"
                >
                    <i class="bi bi-chevron-left"></i>
                </button>
            </li>

            <!-- Páginas -->
            <template x-for="page in visiblePages" :key="page">
                <li class="page-item" :class="{ 'active': page === currentPage }">
                    <button
                            type="button"
                            class="page-link"
                            @click="goToPage(page)"
                            x-text="page"
                            :aria-current="page === currentPage ? 'page' : null"
                    ></button>
                </li>
            </template>

            <!-- Siguiente -->
            <li class="page-item" :class="{ 'disabled': currentPage === totalPages }">
                <button
                        type="button"
                        class="page-link"
                        @click="goToPage(currentPage + 1)"
                        :disabled="currentPage === totalPages"
                        aria-label="Siguiente"
                >
                    <i class="bi bi-chevron-right"></i>
                </button>
            </li>
        </ul>
    </nav>

<?php else: ?>
    <!-- Versión estática (PHP) -->
    <?php if ($totalPages > 1): ?>
        <nav aria-label="Paginación">
            <ul class="pagination pagination-sm mb-0">
                <!-- Anterior -->
                <li class="page-item <?= $currentPage === 1 ? 'disabled' : '' ?>">
                    <a
                            class="page-link"
                            href="<?= $currentPage > 1 ? e($baseUrl . '?page=' . ($currentPage - 1)) : '#' ?>"
                            aria-label="Anterior"
                    >
                        <i class="bi bi-chevron-left"></i>
                    </a>
                </li>

                <!-- Páginas -->
                <?php
                $start = max(1, $currentPage - 2);
        $end = min($totalPages, $start + 4);
        if ($end - $start < 4) {
            $start = max(1, $end - 4);
        }

        for ($page = $start; $page <= $end; $page++):
            ?>
                    <li class="page-item <?= $page === $currentPage ? 'active' : '' ?>">
                        <a
                                class="page-link"
                                href="<?= e($baseUrl . '?page=' . $page) ?>"
                            <?= $page === $currentPage ? 'aria-current="page"' : '' ?>
                        >
                            <?= $page ?>
                        </a>
                    </li>
                <?php endfor; ?>

                <!-- Siguiente -->
                <li class="page-item <?= $currentPage === $totalPages ? 'disabled' : '' ?>">
                    <a
                            class="page-link"
                            href="<?= $currentPage < $totalPages ? e($baseUrl . '?page=' . ($currentPage + 1)) : '#' ?>"
                            aria-label="Siguiente"
                    >
                        <i class="bi bi-chevron-right"></i>
                    </a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
<?php endif; ?>