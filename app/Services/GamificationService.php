<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Servicio de gamificación de usuarios
 *
 * Encapsula la lógica de negocio relacionada con
 * niveles, rangos, badges y sistema de logros.
 */
final class GamificationService
{
    /**
     * Niveles de usuario con configuración
     */
    private const LEVELS = [
        1 => ['nombre' => 'Aprendiz', 'min' => 0, 'next' => 3],
        2 => ['nombre' => 'Habitual', 'min' => 3, 'next' => 7],
        3 => ['nombre' => 'Senpai', 'min' => 7, 'next' => 15],
        4 => ['nombre' => 'Maestro', 'min' => 15, 'next' => 999999],
    ];

    /**
     * Calcula el nivel del usuario basado en número de reservas
     *
     * @param integer $reservasCount Número de reservas completadas
     *
     * @return array{nivel: int, nombre: string, progreso: int, siguiente: int}
     */
    public function calculateUserLevel(int $reservasCount): array
    {
        $nivel = 1;
        foreach (self::LEVELS as $n => $cfg) {
            if ($reservasCount >= $cfg['min']) {
                $nivel = $n;
            }
        }

        $next = self::LEVELS[$nivel]['next'];
        $progreso = ($next >= 999999)
            ? 100
            : (int) \min(100, \round(($reservasCount / \max(1, $next)) * 100));

        return [
            'nivel' => $nivel,
            'nombre' => self::LEVELS[$nivel]['nombre'],
            'progreso' => $progreso,
            'siguiente' => $next,
        ];
    }

    /**
     * Obtiene el nombre del nivel por número
     *
     * @param integer $nivelNumero
     *
     * @return string
     */
    public function getLevelName(int $nivelNumero): string
    {
        return self::LEVELS[$nivelNumero]['nombre'] ?? 'Desconocido';
    }

    /**
     * Calcula si el usuario alcanzó un nuevo nivel
     *
     * @param integer $reservasAntes   Reservas antes de la nueva
     * @param integer $reservasDespues Reservas después de la nueva
     *
     * @return array{level_up: bool, new_level?: int, new_level_name?: string}
     */
    public function checkLevelUp(int $reservasAntes, int $reservasDespues): array
    {
        $levelAntes = $this->calculateUserLevel($reservasAntes);
        $levelDespues = $this->calculateUserLevel($reservasDespues);

        if ($levelDespues['nivel'] > $levelAntes['nivel']) {
            return [
                'level_up' => true,
                'new_level' => $levelDespues['nivel'],
                'new_level_name' => $levelDespues['nombre'],
            ];
        }

        return ['level_up' => false];
    }
}
