<!-- Contenedor Alpine -->
<div class="kds-board" x-data="kdsApp()">

    <!-- 1. COLUMNA CALIENTE -->
    <div class="station-col col-hot">
        <div class="station-header">
            <div class="station-title">
                <span class="material-symbols-outlined">local_fire_department</span>
                Caliente
            </div>
            <span class="station-tag">HOT LINE [<?= count($stations['hot']) ?>]</span>
        </div>
        <div class="station-content">
            <?php if (empty($stations['hot'])): ?>
                <div class="kds-empty">STATION CLEAR</div>
            <?php else: ?>
                <?php foreach ($stations['hot'] as $item): include __DIR__ . '/partials/kds_cell.php';
                endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- 2. COLUMNA BARRA -->
    <div class="station-col col-bar">
        <div class="station-header">
            <div class="station-title">
                <span class="material-symbols-outlined">local_bar</span>
                Barra
            </div>
            <span class="station-tag">DRINKS [<?= count($stations['bar']) ?>]</span>
        </div>
        <div class="station-content">
            <?php if (empty($stations['bar'])): ?>
                <div class="kds-empty">STATION CLEAR</div>
            <?php else: ?>
                <?php foreach ($stations['bar'] as $item): include __DIR__ . '/partials/kds_cell.php';
                endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- 3. COLUMNA FRÍO -->
    <div class="station-col col-cold">
        <div class="station-header">
            <div class="station-title">
                <span class="material-symbols-outlined">ac_unit</span>
                Frío
            </div>
            <span class="station-tag">COLD/PASTRY [<?= count($stations['cold']) ?>]</span>
        </div>
        <div class="station-content">
            <?php if (empty($stations['cold'])): ?>
                <div class="kds-empty">STATION CLEAR</div>
            <?php else: ?>
                <?php foreach ($stations['cold'] as $item): include __DIR__ . '/partials/kds_cell.php';
                endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- MODAL SOP -->
    <?php include __DIR__ . '/partials/sop_modal.php'; ?>

</div>
