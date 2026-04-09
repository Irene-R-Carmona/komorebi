<?php

/**
 * Vista: Formulario de Reset de Contraseña
 *
 * @var string $token
 * @var string $csrf_token
 */
?>

<?php $this->extend('layouts/main.php'); ?>

<?php $this->start('content'); ?>

<div class="container mx-auto px-4 py-12">
    <div class="max-w-md mx-auto">
        <div class="bg-white rounded-lg shadow-lg p-8">
            <h1 class="text-2xl font-bold text-gray-800 mb-2">Establecer nueva contraseña</h1>
            <p class="text-gray-600 mb-6">Ingresa una nueva contraseña segura.</p>

            <?php if ($error = $this->flash('error')): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?= e($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="/auth/reset-password" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                <input type="hidden" name="token" value="<?= e($token) ?>">

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
                        placeholder="Mín. 8 caracteres">
                    <p class="text-xs text-gray-500 mt-1">Usa mayúsculas, minúsculas, números y símbolos.</p>
                </div>

                <div>
                    <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-2">
                        Confirmar contraseña
                    </label>
                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        required
                        minlength="8"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"
                        placeholder="Repite tu contraseña">
                </div>

                <button
                    type="submit"
                    class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg transition">
                    Actualizar contraseña
                </button>

                <div class="text-center mt-4">
                    <a href="/login" class="text-green-600 hover:text-green-700 text-sm">
                        Volver al login
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php $this->end(); ?>
