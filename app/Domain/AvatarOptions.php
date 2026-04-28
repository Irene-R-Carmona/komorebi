<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Opciones de avatar disponibles para usuarios.
 *
 * Los avatares son presets SVG predefinidos o la opción de iniciales del nombre.
 * No se permiten subidas de archivo personalizadas.
 */
final class AvatarOptions
{
    /** @var list<string> */
    public const array OPTIONS = [
        'initials',
        'preset_1',
        'preset_2',
        'preset_3',
        'preset_4',
        'preset_5',
        'preset_6',
        'preset_7',
        'preset_8',
    ];

    public const string DEFAULT = 'initials';

    public static function isValid(string $value): bool
    {
        return \in_array($value, self::OPTIONS, true);
    }

    /**
     * Devuelve la URL pública del avatar o null para la opción 'initials'.
     */
    public static function toUrl(string $id): ?string
    {
        if ($id === 'initials') {
            return null;
        }

        return "/images/avatars/{$id}.svg";
    }

    /**
     * @return list<array{id: string, url: string|null, label: string}>
     */
    public static function toList(): array
    {
        $list = [];
        foreach (self::OPTIONS as $id) {
            $list[] = [
                'id'    => $id,
                'url'   => self::toUrl($id),
                'label' => $id === 'initials' ? 'Iniciales' : 'Avatar ' . \substr($id, 7),
            ];
        }

        return $list;
    }
}
