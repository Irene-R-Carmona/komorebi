<?php
/**
 * Vista: Cambiar Contraseña
 *
 * @var string $csrf_token
 */
?>

<?php $this->extend('layouts/main.php'); ?>

<?php $this->start('content'); ?>

<div class="container mx-auto px-4 py-12">
    <div class="max-w-md mx-auto">
        <div class="bg-white rounded-lg shadow-lg p-8">
            <h1 class="text-2xl font-bold text-gray-800 mb-2">Cambiar contraseña</h1>
            <p class="text-gray-600 mb-6">Actualiza tu contraseña de acceso.</p>

            <?php if ($error = $this->flash('error')): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($success = $this->flash('success')): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="/account/change-password" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

                <div>
                    <label for="current_password" class="block text-sm font-medium text-gray-700 mb-2">
                        Contraseña actual
                    </label>
                    <input
                        type="password"
                        id="current_password"
                        name="current_password"
                        required
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"
                    >
                </div>

                <div>
                    <label for="new_password" class="block text-sm font-medium text-gray-700 mb-2">
                        Nueva contraseña
                    </label>
                    <input
                        type="password"
                        id="new_password"
                        name="new_password"
                        required
                        minlength="8"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"
                        placeholder="Mín. 8 caracteres"
                    >
                </div>

                <div>
                    <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-2">
                        Confirmar nueva contraseña
                    </label>
                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        required
                        minlength="8"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"
                    >
                </div>

                <button
                    type="submit"
                    class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg transition"
                >
                    Actualizar contraseña
                </button>

                <div class="text-center mt-4">
                    <a href="/account/security" class="text-green-600 hover:text-green-700 text-sm">
                        Ver historial de seguridad
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php $this->end(); ?>
