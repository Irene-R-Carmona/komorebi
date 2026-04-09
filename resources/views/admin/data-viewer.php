<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Viewer - Komorebi</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            color: #333;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        header {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }
        h1 {
            font-size: 2.5em;
            color: #667eea;
            margin-bottom: 10px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            text-align: center;
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-number {
            font-size: 2.5em;
            font-weight: bold;
            color: #667eea;
        }
        .stat-label {
            color: #666;
            font-size: 0.9em;
            margin-top: 5px;
        }
        .section {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }
        h2 {
            color: #667eea;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 3px solid #667eea;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #667eea;
            border-bottom: 2px solid #dee2e6;
        }
        td {
            padding: 12px;
            border-bottom: 1px solid #e9ecef;
        }
        tr:hover {
            background: #f8f9fa;
        }
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
        }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-warning { background: #fff3cd; color: #856404; }
        .badge-danger { background: #f8d7da; color: #721c24; }
        .badge-info { background: #d1ecf1; color: #0c5460; }
        .price { font-weight: bold; color: #28a745; }
        .rating { color: #ffc107; font-size: 1.2em; }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>🐾 Komorebi Café - Data Viewer</h1>
            <p>Visualización completa de todos los datos cargados en el sistema</p>
        </header>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= $stats['users'] ?></div>
                <div class="stat-label">Usuarios Totales</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['staff'] ?></div>
                <div class="stat-label">Staff/Profesionales</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['cafes'] ?></div>
                <div class="stat-label">Cafés</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['animals'] ?></div>
                <div class="stat-label">Animales</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['products'] ?></div>
                <div class="stat-label">Pases/Experiencias</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['reservations'] ?></div>
                <div class="stat-label">Reservaciones</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['time_slots'] ?></div>
                <div class="stat-label">Time Slots</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['reviews'] ?></div>
                <div class="stat-label">Reviews</div>
            </div>
        </div>

        <!-- Cafés -->
        <div class="section">
            <h2>🏪 Cafés (<?= $stats['cafes'] ?>)</h2>
            <table>
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
                        <td class="rating"><?= $cafe['rating_avg'] ? '⭐ ' . number_format($cafe['rating_avg'], 1) : 'Sin ratings' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pases/Productos -->
        <div class="section">
            <h2>🎫 Pases y Experiencias (<?= $stats['products'] ?>)</h2>
            <table>
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
                        <td class="price">¥<?= number_format($product['price'], 0) ?></td>
                        <td><?= $product['duration'] ?> min</td>
                        <td><?= $product['min_pax'] ?>-<?= $product['max_pax'] ?> personas</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Staff -->
        <div class="section">
            <h2>👥 Personal/Staff (<?= $stats['staff'] ?>)</h2>
            <table>
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
                        <td><span class="badge badge-info"><?= htmlspecialchars($staff['roles']) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Usuarios normales -->
        <div class="section">
            <h2>👤 Usuarios (Clientes)</h2>
            <table>
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
                        <td><span class="badge badge-success"><?= htmlspecialchars($user['roles']) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Reservaciones -->
        <div class="section">
            <h2>📅 Reservaciones (<?= $stats['reservations'] ?> total, <?= $stats['reservations_with_slot'] ?> con time_slot)</h2>
            <table>
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
                        <td class="price">¥<?= number_format($res['pass_unit_price'], 0) ?></td>
                        <td><?= date('d/m/Y H:i', strtotime($res['reservation_date'] . ' ' . $res['reservation_time'])) ?></td>
                        <td><?= $res['guest_count'] ?></td>
                        <td>
                            <?php
                            $statusClass = match($res['status']) {
                                'confirmed' => 'badge-success',
                                'pending' => 'badge-warning',
                                'completed' => 'badge-info',
                                default => 'badge-danger'
                            };
                        ?>
                            <span class="badge <?= $statusClass ?>"><?= $res['status'] ?></span>
                        </td>
                        <td><?= $res['has_slot'] === 'Sí' ? '✅' : '❌' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Time Slots -->
        <div class="section">
            <h2>⏰ Time Slots Futuros (<?= $stats['time_slots_available'] ?> disponibles de <?= $stats['time_slots'] ?> totales)</h2>
            <table>
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
                        <td><?= $slot['is_blocked'] ? '🔒 Sí' : '✅ No' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Reviews -->
        <div class="section">
            <h2>⭐ Reviews (<?= $stats['reviews'] ?>)</h2>
            <table>
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
                        <td class="rating"><?= str_repeat('⭐', (int) $review['rating']) ?></td>
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
        <div class="section">
            <h2>🏥 Incidentes de Animales (<?= $stats['incidents'] ?>)</h2>
            <table>
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
                        <td><span class="badge badge-<?= $incident['type'] === 'health' ? 'danger' : 'warning' ?>"><?= $incident['type'] ?></span></td>
                        <td><?= htmlspecialchars($incident['animal']) ?></td>
                        <td><?= htmlspecialchars($incident['cafe']) ?></td>
                        <td><?= htmlspecialchars(substr($incident['description'], 0, 80)) ?>...</td>
                        <td><span class="badge badge-<?= $incident['severity'] === 'high' ? 'danger' : ($incident['severity'] === 'medium' ? 'warning' : 'info') ?>"><?= $incident['severity'] ?></span></td>
                        <td><?= htmlspecialchars($incident['reported_by']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <footer style="text-align: center; color: white; padding: 20px;">
            <p>✨ Sistema de datos Komorebi Café - Moneda: Yen japonés (¥)</p>
        </footer>
    </div>
</body>
</html>
