<?php

/** @var array $waitlists Lista de waitlists activas */
/** @var array $summary Resumen por estado */
/** @var array $filters Filtros activos */
?>

<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Gestión de Listas de Espera</h1>
        <p class="text-gray-600 mt-2">Monitorea y gestiona todas las reservas en lista de espera</p>
    </div>

    <!-- Resumen de estados -->
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-8">
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
            <div class="text-yellow-800 text-sm font-medium">En Espera</div>
            <div class="text-3xl font-bold text-yellow-900 mt-2">
                <?= $summary['waiting'] ?? 0 ?>
            </div>
        </div>
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
            <div class="text-blue-800 text-sm font-medium">Notificados</div>
            <div class="text-3xl font-bold text-blue-900 mt-2">
                <?= $summary['notified'] ?? 0 ?>
            </div>
        </div>
        <div class="bg-green-50 border border-green-200 rounded-lg p-4">
            <div class="text-green-800 text-sm font-medium">Confirmados</div>
            <div class="text-3xl font-bold text-green-900 mt-2">
                <?= $summary['confirmed'] ?? 0 ?>
            </div>
        </div>
        <div class="bg-red-50 border border-red-200 rounded-lg p-4">
            <div class="text-red-800 text-sm font-medium">Cancelados</div>
            <div class="text-3xl font-bold text-red-900 mt-2">
                <?= $summary['cancelled'] ?? 0 ?>
            </div>
        </div>
        <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
            <div class="text-gray-800 text-sm font-medium">Expirados</div>
            <div class="text-3xl font-bold text-gray-900 mt-2">
                <?= $summary['expired'] ?? 0 ?>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
        <form method="GET" action="/admin/waitlists" class="flex flex-wrap gap-4">
            <div class="flex-1 min-w-[200px]">
                <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Estado</label>
                <select name="status" id="status"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                    <option value="">Todos</option>
                    <option value="waiting" <?= ($filters['status'] ?? '') === 'waiting' ? 'selected' : '' ?>>En Espera</option>
                    <option value="notified" <?= ($filters['status'] ?? '') === 'notified' ? 'selected' : '' ?>>Notificados</option>
                    <option value="confirmed" <?= ($filters['status'] ?? '') === 'confirmed' ? 'selected' : '' ?>>Confirmados</option>
                    <option value="cancelled" <?= ($filters['status'] ?? '') === 'cancelled' ? 'selected' : '' ?>>Cancelados</option>
                    <option value="expired" <?= ($filters['status'] ?? '') === 'expired' ? 'selected' : '' ?>>Expirados</option>
                </select>
            </div>

            <div class="flex-1 min-w-[200px]">
                <label for="date" class="block text-sm font-medium text-gray-700 mb-1">Fecha</label>
                <input type="date" name="date" id="date" value="<?= htmlspecialchars($filters['date'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
            </div>

            <div class="flex items-end">
                <button type="submit"
                    class="px-6 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition">
                    Filtrar
                </button>
                <a href="/admin/waitlists"
                    class="ml-2 px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
                    Limpiar
                </a>
            </div>
        </form>
    </div>

    <!-- Tabla de waitlists -->
    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
        <?php if (empty($waitlists)): ?>
            <div class="text-center py-12">
                <div class="text-gray-400 text-5xl mb-4">📋</div>
                <p class="text-gray-600 text-lg">No hay listas de espera con los filtros seleccionados</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                #
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Usuario
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Café
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Fecha/Hora
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Posición
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Personas
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Estado
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Creado
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Acciones
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($waitlists as $w): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?= htmlspecialchars((string)$w['id'], ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?= htmlspecialchars($w['user_name'], ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        <?= htmlspecialchars($w['user_email'], ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?= htmlspecialchars($w['cafe_name'], ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        <?= date('d/m/Y', strtotime($w['slot_date'])) ?>
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        <?= date('H:i', strtotime($w['slot_time'])) ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-gradient-to-br from-purple-500 to-indigo-600 text-white text-sm font-bold">
                                        <?= htmlspecialchars((string)$w['position'], ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?= htmlspecialchars((string)$w['guest_count'], ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php
                                    $statusClasses = [
                                        'waiting'   => 'bg-yellow-100 text-yellow-800',
                                        'notified'  => 'bg-blue-100 text-blue-800',
                                        'confirmed' => 'bg-green-100 text-green-800',
                                        'cancelled' => 'bg-red-100 text-red-800',
                                        'expired'   => 'bg-gray-100 text-gray-800',
                                    ];
                                    $statusLabels = [
                                        'waiting'   => 'En Espera',
                                        'notified'  => 'Notificado',
                                        'confirmed' => 'Confirmado',
                                        'cancelled' => 'Cancelado',
                                        'expired'   => 'Expirado',
                                    ];
                                    $statusClass = $statusClasses[$w['status']] ?? 'bg-gray-100 text-gray-800';
                                    $statusLabel = $statusLabels[$w['status']] ?? $w['status'];
                                    ?>
                                    <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?= $statusClass ?>">
                                        <?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= date('d/m H:i', strtotime($w['created_at'])) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <?php if ($w['status'] === 'waiting' || $w['status'] === 'notified'): ?>
                                        <form method="POST" action="/admin/waitlists/<?= $w['id'] ?>/cancel"
                                            data-action="confirm" data-confirm="¿Cancelar esta waitlist?"
                                            class="inline">
                                            <?= Csrf::field() ?>
                                            <button type="submit" class="text-red-600 hover:text-red-900">
                                                Cancelar
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-gray-400">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Info -->
    <div class="mt-6 text-sm text-gray-600">
        Total: <strong><?= count($waitlists) ?></strong> resultado(s)
    </div>
</div>
