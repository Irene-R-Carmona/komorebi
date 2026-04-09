<?php

/**
 * Vista: Gestión de Sesiones Activas
 *
 * @var array[] $sessions
 * @var string $csrf_token
 */
?>

<?php $this->extend('layouts/main.php'); ?>

<?php $this->start('content'); ?>

<div class="container mx-auto px-4 py-12">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold text-gray-800 mb-2">Mis Sesiones Activas</h1>
        <p class="text-gray-600 mb-6">Gestiona los dispositivos conectados a tu cuenta.</p>

        <?php if ($success = $this->flash('success')): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <?php if ($error = $this->flash('error')): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-lg shadow-lg overflow-hidden mb-6">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-100 border-b">
                        <tr>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">Dispositivo</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">IP Address</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">Última actividad</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sessions as $session): ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="px-6 py-3 text-sm text-gray-800">
                                    <?= htmlspecialchars($session['device_name'] ?? 'Desconocido') ?>
                                </td>
                                <td class="px-6 py-3 text-sm text-gray-600">
                                    <code><?= htmlspecialchars($session['ip_address']) ?></code>
                                </td>
                                <td class="px-6 py-3 text-sm text-gray-600">
                                    <?= date('d/m/Y H:i', strtotime($session['last_activity'])) ?>
                                </td>
                                <td class="px-6 py-3 text-sm">
                                    <form method="POST" action="/account/sessions/revoke/<?= (int) $session['id'] ?>" class="inline">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                        <button
                                            type="button"
                                            class="text-red-600 hover:text-red-800 font-medium"
                                            data-action="confirm"
                                            data-confirm="¿Estás seguro de que quieres revocar esta sesión?">
                                            Revocar
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
            <h3 class="font-semibold text-gray-800 mb-2">Opciones rápidas</h3>
            <form method="POST" action="/account/sessions/revoke-all">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <button
                    type="button"
                    class="bg-yellow-600 hover:bg-yellow-700 text-white font-bold py-2 px-4 rounded-lg transition"
                    data-action="confirm"
                    data-confirm="Esto revocará todas tus otras sesiones. ¿Continuar?">
                    Revocar todas las demás sesiones
                </button>
            </form>
        </div>

        <div class="text-center">
            <a href="/account" class="text-green-600 hover:text-green-700">← Volver a mi cuenta</a>
        </div>
    </div>
</div>

<?php $this->end(); ?>
