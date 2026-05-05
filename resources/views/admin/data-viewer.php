<?php

declare(strict_types=1);

/**
 * Vista: Data Viewer — visualización interna de datos de seeding
 * Ruta: GET /admin/data-viewer
 *
 * @var array{users:int,staff:int,cafes:int,animals:int,products:int,reservations:int,time_slots:int,reviews:int,incidents:int,reservations_with_slot:int,time_slots_available:int} $stats
 * @var array $samples
 */

use App\Core\View;

echo View::componentToString('components/admin/page-header', [
    'icon' => 'database',
    'title' => 'Data Viewer',
    'subtitle' => 'Visualización completa de todos los datos cargados en el sistema',
]);
?>

<div class="dv-stats-grid">
    <div class="card-admin hover-lift text-center p-3">
        <div class="dv-stat__number"><?= $stats['users'] ?></div>
        <div class="dv-stat__label">Usuarios Totales</div>
    </div>
    <div class="card-admin hover-lift text-center p-3">
        <div class="dv-stat__number"><?= $stats['staff'] ?></div>
        <div class="dv-stat__label">Staff/Profesionales</div>
    </div>
    <div class="card-admin hover-lift text-center p-3">
        <div class="dv-stat__number"><?= $stats['cafes'] ?></div>
        <div class="dv-stat__label">Cafés</div>
    </div>
    <div class="card-admin hover-lift text-center p-3">
        <div class="dv-stat__number"><?= $stats['animals'] ?></div>
        <div class="dv-stat__label">Animales</div>
    </div>
    <div class="card-admin hover-lift text-center p-3">
        <div class="dv-stat__number"><?= $stats['products'] ?></div>
        <div class="dv-stat__label">Pases/Experiencias</div>
    </div>
    <div class="card-admin hover-lift text-center p-3">
        <div class="dv-stat__number"><?= $stats['reservations'] ?></div>
        <div class="dv-stat__label">Reservaciones</div>
    </div>
    <div class="card-admin hover-lift text-center p-3">
        <div class="dv-stat__number"><?= $stats['time_slots'] ?></div>
        <div class="dv-stat__label">Time Slots</div>
    </div>
    <div class="card-admin hover-lift text-center p-3">
        <div class="dv-stat__number"><?= $stats['reviews'] ?></div>
        <div class="dv-stat__label">Reviews</div>
    </div>
</div>

<!-- Cafés -->
<div class="dv-section">
    <h2><i class="bi bi-shop" aria-hidden="true"></i> Cafés (<?= $stats['cafes'] ?>)</h2>
    <table class="table table-admin">
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Tipo de Animal</th>
                <th>Capacidad</th>
                <th>Horario</th>
                <th>Rating</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($samples['cafes'] as $cafe): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($cafe['name']) ?></strong></td>
                    <td><?= htmlspecialchars($cafe['animal_type']) ?></td>
                    <td><?= $cafe['capacity_max'] ?> personas</td>
                    <td><?= substr($cafe['opening_time'], 0, 5) ?> - <?= substr($cafe['closing_time'], 0, 5) ?></td>
                    <td class="dv-rating"><?= $cafe['rating_avg'] ? '<i class="bi bi-star-fill" aria-hidden="true"></i> ' . number_format($cafe['rating_avg'], 1) : 'Sin ratings' ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Pases/Productos -->
<div class="dv-section">
    <h2><i class="bi bi-ticket" aria-hidden="true"></i> Pases y Experiencias (<?= $stats['products'] ?>)</h2>
    <table class="table table-admin">
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Nombre Japonés</th>
                <th>Precio</th>
                <th>Duración</th>
                <th>Pax</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($samples['products'] as $product): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($product['name']) ?></strong></td>
                    <td><?= htmlspecialchars($product['japanese_name']) ?></td>
                    <td class="dv-price">¥<?= number_format($product['price']) ?></td>
                    <td><?= $product['duration'] ?> min</td>
                    <td><?= $product['min_pax'] ?>-<?= $product['max_pax'] ?> personas</td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Staff -->
<div class="dv-section">
    <h2><i class="bi bi-people" aria-hidden="true"></i> Personal/Staff (<?= $stats['staff'] ?>)</h2>
    <table class="table table-admin">
        <thead>
            <tr>
                <th>Nombre (Cuenta)</th>
                <th>Email</th>
                <th>Café</th>
                <th>Roles</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($samples['staff'] as $staff): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($staff['name']) ?></strong></td>
                    <td><?= htmlspecialchars($staff['email']) ?></td>
                    <td><?= htmlspecialchars($staff['cafe'] ?? 'N/A') ?></td>
                    <td><span class="badge text-bg-info"><?= htmlspecialchars($staff['roles']) ?></span></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Usuarios normales -->
<div class="dv-section">
    <h2><i class="bi bi-person" aria-hidden="true"></i> Usuarios (Clientes)</h2>
    <table class="table table-admin">
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Email</th>
                <th>Roles</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($samples['users'] as $user): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($user['name']) ?></strong></td>
                    <td><?= htmlspecialchars($user['email']) ?></td>
                    <td><span class="badge text-bg-success"><?= htmlspecialchars($user['roles']) ?></span></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Reservaciones -->
<div class="dv-section">
    <h2><i class="bi bi-calendar3" aria-hidden="true"></i> Reservaciones (<?= $stats['reservations'] ?> total, <?= $stats['reservations_with_slot'] ?> con time_slot)</h2>
    <table class="table table-admin">
        <thead>
            <tr>
                <th>Usuario</th>
                <th>Café</th>
                <th>Pase</th>
                <th>Precio</th>
                <th>Fecha</th>
                <th>Invitados</th>
                <th>Estado</th>
                <th>Time Slot</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($samples['reservations'] as $res): ?>
                <tr>
                    <td><?= htmlspecialchars($res['user']) ?></td>
                    <td><?= htmlspecialchars($res['cafe']) ?></td>
                    <td><?= htmlspecialchars($res['pass_name']) ?></td>
                    <td class="dv-price">¥<?= number_format($res['pass_unit_price']) ?></td>
                    <td><?= date('d/m/Y H:i', strtotime($res['reservation_date'] . ' ' . $res['reservation_time'])) ?></td>
                    <td><?= $res['guest_count'] ?></td>
                    <td>
                        <?php
                        $statusClass = match ($res['status']) {
                            'confirmed' => 'text-bg-success',
                            'pending' => 'text-bg-warning',
                            'completed' => 'text-bg-info',
                            default => 'text-bg-danger'
                        };
                $statusLabelDv = [
                    'confirmed' => 'Confirmada',
                    'pending' => 'Pendiente',
                    'completed' => 'Completada',
                    'cancelled' => 'Cancelada',
                ][$res['status']] ?? ucfirst($res['status']);
                ?>
                        <span class="badge <?= $statusClass ?>"><?= htmlspecialchars($statusLabelDv, ENT_QUOTES, 'UTF-8') ?></span>
                    </td>
                    <td><?= $res['has_slot'] === 'Sí' ? '<i class="bi bi-check-circle-fill text-success" aria-hidden="true"></i>' : '<i class="bi bi-x-circle-fill text-danger" aria-hidden="true"></i>' ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Time Slots -->
<div class="dv-section">
    <h2><i class="bi bi-clock" aria-hidden="true"></i> Time Slots Futuros (<?= $stats['time_slots_available'] ?> disponibles de <?= $stats['time_slots'] ?> totales)</h2>
    <table class="table table-admin">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Hora</th>
                <th>Café</th>
                <th>Capacidad Total</th>
                <th>Reservados</th>
                <th>Disponibles</th>
                <th>Bloqueado</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($samples['time_slots'] as $slot): ?>
                <tr>
                    <td><?= date('d/m/Y', strtotime($slot['slot_date'])) ?></td>
                    <td><?= substr($slot['slot_time'], 0, 5) ?></td>
                    <td><?= htmlspecialchars($slot['cafe']) ?></td>
                    <td><?= $slot['total_capacity'] ?></td>
                    <td><?= $slot['reserved_spots'] ?></td>
                    <td><strong><?= $slot['available_spots'] ?></strong></td>
                    <td><?= $slot['is_blocked'] ? '<i class="bi bi-lock-fill" aria-hidden="true"></i> Sí' : '<i class="bi bi-check-circle-fill text-success" aria-hidden="true"></i> No' ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Reviews -->
<div class="dv-section">
    <h2><i class="bi bi-star-fill" aria-hidden="true"></i> Reviews (<?= $stats['reviews'] ?>)</h2>
    <table class="table table-admin">
        <thead>
            <tr>
                <th>Rating</th>
                <th>Título</th>
                <th>Café</th>
                <th>Usuario</th>
                <th>Fecha</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($samples['reviews'] as $review): ?>
                <tr>
                    <td class="dv-rating"><?= str_repeat('<i class="bi bi-star-fill" aria-hidden="true"></i>', (int) $review['rating']) ?></td>
                    <td><strong><?= htmlspecialchars($review['title']) ?></strong></td>
                    <td><?= htmlspecialchars($review['cafe']) ?></td>
                    <td><?= htmlspecialchars($review['user']) ?></td>
                    <td><?= date('d/m/Y', strtotime($review['created_at'])) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Incidentes -->
<div class="dv-section">
    <h2><i class="bi bi-heart-pulse" aria-hidden="true"></i> Incidentes de Animales (<?= $stats['incidents'] ?>)</h2>
    <table class="table table-admin">
        <thead>
            <tr>
                <th>Tipo</th>
                <th>Animal</th>
                <th>Café</th>
                <th>Descripción</th>
                <th>Severidad</th>
                <th>Reportado por</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($samples['incidents'] as $incident): ?>
                <tr>
                    <td><span class="badge text-bg-<?= $incident['type'] === 'health' ? 'danger' : 'warning' ?>"><?= $incident['type'] ?></span></td>
                    <td><?= htmlspecialchars($incident['animal']) ?></td>
                    <td><?= htmlspecialchars($incident['cafe']) ?></td>
                    <td><?= htmlspecialchars(substr($incident['description'], 0, 80)) ?>...</td>
                    <td><span class="badge text-bg-<?= $incident['severity'] === 'high' ? 'danger' : ($incident['severity'] === 'medium' ? 'warning' : 'info') ?>"><?= $incident['severity'] ?></span></td>
                    <td><?= htmlspecialchars($incident['reported_by']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<p class="text-center text-muted py-3 small">
    Sistema de datos Komorebi Café — Moneda: Yen japonés (¥)
</p>
</div>
