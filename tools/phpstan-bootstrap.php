<?php

declare(strict_types=1);
// Bootstrap para phpstan: define stubs mínimos y carga autoload si existe.

// Cargar autoload de composer si está disponible
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

// Stub: JetBrains\PhpStorm\NoReturn attribute (analizadores y PHPStan)

namespace JetBrains\PhpStorm {
    if (! \class_exists('\JetBrains\\PhpStorm\\NoReturn', false)) {
        #[\Attribute(\Attribute::TARGET_FUNCTION | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
        final class NoReturn
        {
        }
    }
}

// Stubs para servicios opcionales que phpstan no encuentra en análisis estático

namespace App\Services {
    if (!\class_exists('\App\\Services\\TelegramService', false)) {
        final class TelegramService
        {
            public function sendAdminAlert(string $message): void
            {
            }
        }
    }

    if (!\class_exists('\App\\Services\\RuntimeException', false)) {
        final class RuntimeException extends \RuntimeException
        {
        }
    }
}

// Fin bootstrap
