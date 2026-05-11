<?php

declare(strict_types=1);

namespace App\Core\Seeders\Partials;

use App\Core\Database;
use PDO;

/**
 * Seeder de Pases/Experiencias
 *
 * Crea productos tipo 'pass' para reservas de tiempo en cafés.
 */
final class MenuPassSeeder
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    /**
     * Ejecuta el seeder de pases
     */
    public function run(int $categoryId): void
    {
        echo "  → Creando pases/experiencias...\n";

        $passes = [
            // Pases Básicos - Todos los cafés
            [
                'slug' => 'pass-rapido',
                'name' => 'Pase Rápido',
                'japanese_name' => 'クイックパス',
                'description' => 'Visita express de 30 minutos perfecta para una pausa rápida con los animales.',
                'price' => 1280.00,  // ¥1,280
                'duration_minutes' => 30,
                'min_pax' => 1,
                'max_pax' => 2,
                'image_url' => '/images/products/pass-rapido.jpg',
                'target_cafe_types' => null,
                'target_animal_types' => null,
            ],
            [
                'slug' => 'pass-estandar',
                'name' => 'Pase Estándar',
                'japanese_name' => 'スタンダードパス',
                'description' => 'Experiencia completa de 60 minutos. Incluye tiempo para relajarte y disfrutar con los animales.',
                'price' => 1920.00,  // ¥1,920
                'duration_minutes' => 60,
                'min_pax' => 1,
                'max_pax' => 4,
                'image_url' => '/images/products/pass-estandar.jpg',
                'target_cafe_types' => null,
                'target_animal_types' => null,
            ],
            [
                'slug' => 'pass-extendido',
                'name' => 'Pase Extendido',
                'japanese_name' => '拡張パス',
                'description' => 'Dos horas de pura felicidad animal. Ideal para fotógrafos y amantes de los animales.',
                'price' => 3200.00,  // ¥3,200
                'duration_minutes' => 120,
                'min_pax' => 1,
                'max_pax' => 6,
                'image_url' => '/images/products/pass-extendido.jpg',
                'target_cafe_types' => null,
                'target_animal_types' => null,
            ],

            // Pases Especiales - Grupos
            [
                'slug' => 'pass-familiar',
                'name' => 'Pase Familiar',
                'japanese_name' => 'ファミリーパス',
                'description' => '90 minutos para grupos de 4-8 personas. Perfecto para familias con niños.',
                'price' => 5600.00,  // ¥5,600
                'duration_minutes' => 90,
                'min_pax' => 4,
                'max_pax' => 8,
                'image_url' => '/images/products/pass-familiar.jpg',
                'target_cafe_types' => null,
                'target_animal_types' => null,
            ],
            [
                'slug' => 'pass-grupo-grande',
                'name' => 'Pase Grupo Grande',
                'japanese_name' => 'グループパス',
                'description' => '2 horas para grupos de 8-15 personas. Ideal para celebraciones y eventos corporativos.',
                'price' => 9600.00,  // ¥9,600
                'duration_minutes' => 120,
                'min_pax' => 8,
                'max_pax' => 15,
                'image_url' => '/images/products/pass-grupo.jpg',
                'target_cafe_types' => null,
                'target_animal_types' => null,
            ],

            // Pases Premium - Cafés Específicos
            [
                'slug' => 'pass-zen',
                'name' => 'Experiencia Zen',
                'japanese_name' => '禅体験',
                'description' => '3 horas de meditación y conexión con animales de movimiento lento. Solo en cafés Zen.',
                'price' => 7200.00,  // ¥7,200
                'duration_minutes' => 180,
                'min_pax' => 1,
                'max_pax' => 4,
                'image_url' => '/images/products/pass-zen.jpg',
                'target_cafe_types' => '["zen"]',
                'target_animal_types' => '["tortuga", "cobaya"]',
            ],
            [
                'slug' => 'pass-granja',
                'name' => 'Aventura Granja',
                'japanese_name' => '農場アドベンチャー',
                'description' => 'Medio día (4 horas) interactuando con animales de granja. Incluye alimentación guiada.',
                'price' => 8800.00,  // ¥8,800
                'duration_minutes' => 240,
                'min_pax' => 1,
                'max_pax' => 6,
                'image_url' => '/images/products/pass-granja.jpg',
                'target_cafe_types' => '["farm"]',
                'target_animal_types' => '["capibara", "alpaca", "caballo", "pato"]',
            ],
            [
                'slug' => 'pass-foto-pro',
                'name' => 'Sesión Fotográfica Pro',
                'japanese_name' => 'プロフォトセッション',
                'description' => '2 horas exclusivas para fotografía profesional en cafés Lounge o Playroom.',
                'price' => 12800.00,  // ¥12,800
                'duration_minutes' => 120,
                'min_pax' => 1,
                'max_pax' => 3,
                'image_url' => '/images/products/pass-foto-pro.jpg',
                'target_cafe_types' => '["lounge", "playroom"]',
                'target_animal_types' => null,
            ],

            // Pases VIP
            [
                'slug' => 'pass-dia-completo',
                'name' => 'Pase Día Completo',
                'japanese_name' => '一日パス',
                'description' => 'Acceso ilimitado durante todo el día (6 horas). Incluye bebida y postre de cortesía.',
                'price' => 12000.00,  // ¥12,000
                'duration_minutes' => 360,
                'min_pax' => 1,
                'max_pax' => 2,
                'image_url' => '/images/products/pass-dia-completo.jpg',
                'target_cafe_types' => null,
                'target_animal_types' => null,
            ],
            [
                'slug' => 'pass-vip-private',
                'name' => 'Experiencia VIP Private',
                'japanese_name' => 'VIPプライベート体験',
                'description' => 'Cafetería privada durante 3 horas. Solo para tu grupo. Incluye menú especial.',
                'price' => 24000.00,  // ¥24,000
                'duration_minutes' => 180,
                'min_pax' => 6,
                'max_pax' => 12,
                'image_url' => '/images/products/pass-vip-private.jpg',
                'target_cafe_types' => null,
                'target_animal_types' => null,
            ],
        ];

        $stmt = $this->db->prepare(
            "INSERT INTO products (
                category_id, product_type, name, japanese_name, slug, description,
                price, is_active, image_url,
                duration_minutes, min_pax, max_pax,
                target_cafe_types, target_animal_types,
                created_at
            ) VALUES (
                :category_id, 'pass', :name, :japanese_name, :slug, :description,
                :price, 1, :image_url,
                :duration_minutes, :min_pax, :max_pax,
                :target_cafe_types, :target_animal_types,
                NOW()
            )
            ON DUPLICATE KEY UPDATE
                name               = VALUES(name),
                japanese_name      = VALUES(japanese_name),
                description        = VALUES(description),
                price              = VALUES(price),
                image_url          = VALUES(image_url),
                duration_minutes   = VALUES(duration_minutes),
                min_pax            = VALUES(min_pax),
                max_pax            = VALUES(max_pax),
                target_cafe_types  = VALUES(target_cafe_types),
                target_animal_types = VALUES(target_animal_types),
                updated_at         = NOW()"
        );

        foreach ($passes as $pass) {
            $stmt->execute([
                'category_id' => $categoryId,
                'slug' => $pass['slug'],
                'name' => $pass['name'],
                'japanese_name' => $pass['japanese_name'],
                'description' => $pass['description'],
                'price' => $pass['price'],
                'image_url' => $pass['image_url'],
                'duration_minutes' => $pass['duration_minutes'],
                'min_pax' => $pass['min_pax'],
                'max_pax' => $pass['max_pax'],
                'target_cafe_types' => $pass['target_cafe_types'],
                'target_animal_types' => $pass['target_animal_types'],
            ]);
        }

        echo '    ' . \count($passes) . " pases insertados/actualizados\n";
    }
}
