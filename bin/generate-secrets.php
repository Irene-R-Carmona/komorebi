#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Generador de secretos para Komorebi Café
 *
 * Genera claves criptográficamente seguras para:
 * - APP_KEY (32 bytes base64)
 * - SESSION_SECRET (32 bytes base64)
 * - CSRF_TOKEN_SECRET (32 bytes base64)
 * - ENCRYPTION_KEY (32 bytes base64)
 *
 * Uso:
 *   php bin/generate-secrets.php
 *   php bin/generate-secrets.php --show-only  (solo mostrar, no escribir)
 */

use Random\RandomException;

/** Generar clave base64 segura de N bytes
 *
 * @throws RandomException
 */
function generateSecureKey(int $bytes = 32): string
{
    return base64_encode(random_bytes($bytes));
}

// Colores para terminal
function color(string $text, string $color): string
{
    $colors = [
        'green' => "\033[32m",
        'yellow' => "\033[33m",
        'red' => "\033[31m",
        'blue' => "\033[34m",
        'reset' => "\033[0m",
    ];

    return ($colors[$color] ?? '') . $text . $colors['reset'];
}

// Banner
echo "\n";
echo color("╔═══════════════════════════════════════════════════════════╗\n", 'blue');
echo color("║  🔐 Komorebi Café - Generador de Secretos               ║\n", 'blue');
echo color("╚═══════════════════════════════════════════════════════════╝\n", 'blue');
echo "\n";

// Generar claves
echo color("Generando claves criptográficamente seguras...\n", 'yellow');
echo "\n";

$secrets = [
    'APP_KEY' => generateSecureKey(32),
    'SESSION_SECRET' => generateSecureKey(32),
    'CSRF_TOKEN_SECRET' => generateSecureKey(32),
    'ENCRYPTION_KEY' => generateSecureKey(32),
];

// Mostrar claves generadas
foreach ($secrets as $name => $value) {
    echo color("✓ $name: ", 'green') . $value . "\n";
}

echo "\n";

// Verificar si es solo visualización
if (in_array('--show-only', $argv, true)) {
    echo color("Modo solo visualización. Copia manualmente estas claves a tu .env\n", 'yellow');
    exit(0);
}

// Buscar archivo .env
$envPath = __DIR__ . '/../.env';
if (!file_exists($envPath)) {
    echo color("⚠ Archivo .env no encontrado.\n", 'yellow');
    echo color('¿Deseas crear uno desde .env.example? (y/n): ', 'yellow');

    $handle = fopen('php://stdin', 'rb');
    $response = trim(fgets($handle));
    fclose($handle);

    if (strtolower($response) === 'y') {
        $examplePath = __DIR__ . '/../.env.example';
        if (!file_exists($examplePath)) {
            echo color("✗ Error: .env.example no encontrado\n", 'red');
            exit(1);
        }
        copy($examplePath, $envPath);
        echo color("✓ Archivo .env creado desde .env.example\n", 'green');
    } else {
        echo color("Operación cancelada.\n", 'red');
        exit(1);
    }
}

// Leer contenido actual de .env
$envContent = file_get_contents($envPath);

// Actualizar o añadir cada secreto
foreach ($secrets as $name => $value) {
    $pattern = "/^$name=.*/m";
    $replacement = "$name=$value";

    if (preg_match($pattern, $envContent)) {
        // Actualizar valor existente
        $envContent = preg_replace($pattern, $replacement, $envContent);
        echo color("✓ Actualizado $name en .env\n", 'green');
    } else {
        // Añadir nuevo valor
        $envContent .= "\n$replacement";
        echo color("✓ Añadido $name a .env\n", 'green');
    }
}

// Escribir archivo actualizado
file_put_contents($envPath, $envContent);

echo "\n";
echo color("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n", 'green');
echo color("✓ Secretos generados y guardados en .env correctamente\n", 'green');
echo color("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n", 'green');
echo "\n";

// Advertencias de seguridad
echo color("⚠  IMPORTANTE:\n", 'yellow');
echo "   1. NO commitear .env a Git (verificar .gitignore)\n";
echo "   2. Rotar secretos cada 90 días en producción\n";
echo "   3. Usar diferentes claves por entorno (dev/staging/prod)\n";
echo "\n";

// Verificar que .env está en .gitignore
$gitignorePath = __DIR__ . '/../.gitignore';
if (file_exists($gitignorePath)) {
    $gitignoreContent = file_get_contents($gitignorePath);
    if (!str_contains($gitignoreContent, '.env')) {
        echo color("✗ ADVERTENCIA: .env NO está en .gitignore\n", 'red');
        echo color("  Añade '.env' a tu .gitignore INMEDIATAMENTE\n", 'red');
    } else {
        echo color("✓ .env está protegido en .gitignore\n", 'green');
    }
}

echo "\n";
echo color("Happy coding! 🚀\n", 'blue');
echo "\n";

exit(0);
