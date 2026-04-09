<?php

/**
 * Vista: Gestión de Asignaciones de Mesas (Supervisor)
 *
 * @var string $titulo
 * @var array $assignments
 */

use App\Core\Csrf;

$csrf = Csrf::token();
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col">
            <h1><?= $titulo ?></h1>
            <p class="text-muted">Historial y gestión de asignaciones de mesas a reservas</p>
        </div>
    </div>

    <?php if (empty($assignments)): ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle me-2"></i>
            No hay asignaciones registradas aún.
        </div>
    <?php else: ?>
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Reserva ID</th>
                                <th>Mesa</th>
                                <th>Asignado en</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($assignments as $assignment): ?>
                                <tr>
                                    <td>#<?= htmlspecialchars((string)$assignment['reservation_id'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><span class="badge bg-primary"><?= htmlspecialchars($assignment['table_code'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                    <td><?= htmlspecialchars($assignment['assigned_at'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-secondary" disabled>
                                            <i class="bi bi-eye"></i> Ver
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="mt-4">
        <a href="/supervisor/dashboard" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Volver al Dashboard
        </a>
    </div>
</div>
