<?php

declare(strict_types=1);

// Bootstrap para phpstan: define stubs mínimos y carga autoload si existe.
// NOTA: Con múltiples bloques namespace {}, TODO el código debe estar dentro
// de un bloque namespace. El require_once está en el bloque namespace {} global.

// Stub: JetBrains\PhpStorm\NoReturn attribute (analizadores y PHPStan)

namespace JetBrains\PhpStorm {
    if (! \class_exists('\JetBrains\\PhpStorm\\NoReturn', false)) {
        #[\Attribute(\Attribute::TARGET_FUNCTION | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
        final class NoReturn {}
    }
}

// Stubs para servicios opcionales que phpstan no encuentra en análisis estático

namespace App\Services {
    if (!\class_exists('\App\\Services\\TelegramService', false)) {
        final class TelegramService
        {
            public function sendAdminAlert(string $message): void {}
        }
    }

    if (!\class_exists('\App\\Services\\RuntimeException', false)) {
        final class RuntimeException extends \RuntimeException {}
    }
}

// Stubs para funciones globales de componentes de vista
// (resources/views/components/*.php no está en los paths de análisis)
namespace {
    // Cargar autoload de composer si está disponible
    if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
        require_once __DIR__ . '/../vendor/autoload.php';
    }

    if (!\function_exists('renderButton')) {
        function renderButton(array $props = []): string
        {
            return '';
        }
    }

    if (!\function_exists('renderModal')) {
        function renderModal(array $props = []): string
        {
            return '';
        }
    }

    if (!\function_exists('renderBadge')) {
        function renderBadge(array $props = []): string
        {
            return '';
        }
    }

    if (!\function_exists('renderCard')) {
        function renderCard(array $props = []): string
        {
            return '';
        }
    }
}

// Fin bootstrap
