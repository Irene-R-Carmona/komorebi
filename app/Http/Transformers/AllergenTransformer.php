<?php

declare(strict_types=1);

namespace App\Http\Transformers;

use Override;

/**
 * Transforma una fila de la tabla `allergens` para la API pública.
 *
 * Normaliza tipos y documenta los campos expuestos.
 */
final class AllergenTransformer extends AbstractTransformer
{
    #[Override]
    public function transform(array $data): array
    {
        return [
            'id' => (int) ($data['id'] ?? 0),
            'name' => (string) ($data['name'] ?? ''),
            'code' => (string) ($data['code'] ?? ''),
            'name_jp' => isset($data['name_jp']) ? (string) $data['name_jp'] : null,
            'icon' => isset($data['icon']) ? (string) $data['icon'] : null,
            'icon_color' => isset($data['icon_color']) ? (string) $data['icon_color'] : null,
            'severity' => (string) ($data['severity'] ?? ''),
        ];
    }
}
