<?php
/**
 * Vista: Historial de Seguridad
 *
 * @var array[] $auth_history
 */
?>

<?php $this->extend('layouts/main.php'); ?>

<?php $this->start('content'); ?>

<div class="container mx-auto px-4 py-12">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold text-gray-800 mb-2">Seguridad y Autenticación</h1>
        <p class="text-gray-600 mb-6">Historial de eventos de seguridad en tu cuenta.</p>

        <div class="bg-white rounded-lg shadow-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-100 border-b">
                        <tr>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">Evento</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">Estado</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">IP Address</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">Dispositivo</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">Fecha</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($auth_history as $event): ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="px-6 py-3 text-sm text-gray-800">
                                    <span class="font-medium">
                                        <?= htmlspecialchars($event['event_type']) ?>
                                    </span>
                                    <?php if ($event['reason']): ?>
                                        <br><span class="text-xs text-gray-500">
                                            (<?= htmlspecialchars($event['reason']) ?>)
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-3 text-sm">
                                    <?php if ($event['success']): ?>
                                        <span class="inline-block bg-green-100 text-green-800 px-2 py-1 rounded text-xs font-semibold">
                                            ✓ Exitoso
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-block bg-red-100 text-red-800 px-2 py-1 rounded text-xs font-semibold">
                                            ✗ Fallido
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-3 text-sm text-gray-600">
                                    <code><?= htmlspecialchars($event['ip_address']) ?></code>
                                </td>
                                <td class="px-6 py-3 text-sm text-gray-600">
                                    <?= htmlspecialchars($event['device_name'] ?? 'Desconocido') ?>
                                </td>
                                <td class="px-6 py-3 text-sm text-gray-600">
                                    <?= date('d/m/Y H:i', strtotime($event['created_at'])) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (empty($auth_history)): ?>
                            <tr>
                                <td colspan="5" class="px-6 py-4 text-center text-gray-500">
                                    No hay eventos de autenticación registrados.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-6 text-center">
            <a href="/account" class="text-green-600 hover:text-green-700">← Volver a mi cuenta</a>
        </div>
    </div>
</div>

<?php $this->end(); ?>
