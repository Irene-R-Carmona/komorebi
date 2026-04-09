<?php

/**
 * Vista: Formulario de Olvido de Contraseña
 *
 * @var string $csrf_token
 */
?>

<?php $this->extend('layouts/main.php'); ?>

<?php $this->start('content'); ?>

<div class="container mx-auto px-4 py-12">
    <div class="max-w-md mx-auto">
        <div class="bg-white rounded-lg shadow-lg p-8">
            <h1 class="text-2xl font-bold text-gray-800 mb-2">Recupera tu contraseña</h1>
            <p class="text-gray-600 mb-6">Ingresa tu email para recibir instrucciones de recuperación.</p>

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

            <form method="POST" action="/forgot-password" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                        Email
                    </label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        required
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"
                        placeholder="tu@email.com">
                </div>

                <button
                    type="submit"
                    class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg transition">
                    Enviar instrucciones
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
