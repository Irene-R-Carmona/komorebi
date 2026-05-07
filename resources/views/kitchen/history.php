<div class="container-fluid py-4">

    <!-- Cabecera -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 fw-bold">
                <span class="material-symbols-outlined align-middle me-2">history</span>
                Historial de hoy
            </h1>
            <small class="text-muted"><?= e($cafe_name ?? '') ?></small>
        </div>
        <a href="/ops/kitchen" class="btn btn-outline-secondary btn-sm">
            <span class="material-symbols-outlined align-middle">arrow_back</span>
            Volver al KDS
        </a>
    </div>

    <!-- Tabla de ítems servidos -->
    <?php if (empty($completed)): ?>
        <div class="alert alert-info">
            <span class="material-symbols-outlined align-middle me-1">info</span>
            No hay ítems servidos hoy todavía.
        </div>
    <?php else: ?>
        <div class="card shadow-sm">
            <div class="card-header d-flex justify-content-between">
                <span class="fw-semibold">Ítems servidos hoy</span>
                <span class="badge bg-success"><?= count($completed) ?> total</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>Tracker / Mesa</th>
                            <th>Producto</th>
                            <th>Estación</th>
                            <th class="text-center">Cant.</th>
                            <th class="text-center">Espera</th>
                            <th>Hora</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($completed as $item): ?>
                            <?php
                            $station = $item['station'] ?? 'hot';
                            $stationLabel = match ($station) {
                                'bar' => 'Barra',
                                'kitchen_cold' => 'Frío',
                                default => 'Caliente',
                            };
                            $stationClass = match ($station) {
                                'bar' => 'bg-primary',
                                'kitchen_cold' => 'bg-info text-dark',
                                default => 'bg-danger',
                            };
                            $mins = (int) ($item['waiting_minutes'] ?? 0);
                            ?>
                            <tr>
                                <td>
                                    <span class="font-monospace small"><?= e($item['tracker_code'] ?? '—') ?></span>
                                </td>
                                <td><?= e($item['product_name'] ?? '—') ?></td>
                                <td>
                                    <span class="badge <?= $stationClass ?>"><?= $stationLabel ?></span>
                                </td>
                                <td class="text-center"><?= (int) ($item['quantity'] ?? 1) ?></td>
                                <td class="text-center">
                                    <?php if ($mins > 15): ?>
                                        <span class="badge bg-danger"><?= $mins ?> min</span>
                                    <?php elseif ($mins > 10): ?>
                                        <span class="badge bg-warning text-dark"><?= $mins ?> min</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary"><?= $mins ?> min</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted small"><?= e($item['created_at'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>
