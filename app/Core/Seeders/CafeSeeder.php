<?php

declare(strict_types=1);

namespace App\Core\Seeders;

use App\Core\Database;
use App\Core\Logger;
use PDO;
use Throwable;

final class CafeSeeder
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function run(): void
    {
        Logger::info('CafeSeeder: starting');

        $cafes = [
            // ----------------------------------------------------------------
            // DISTRITO URBANO (Lounge)
            // ----------------------------------------------------------------
            [
                'id' => 1,
                'name' => 'Neko no Niwa',
                'japanese_name' => '猫の庭',
                'slug' => 'neko-no-niwa',
                'location' => 'Shibuya, Tokyo',
                'category' => 'lounge',
                'animal_type' => 'gato',
                'price' => 1200,
                'rating' => 4.7,
                'open' => '11:00:00',
                'close' => '20:00:00',
                'cap' => 20,
                'reserve' => 1,
                'lat' => 35.6595,
                'lon' => 139.7004,
                'timezone' => 'Asia/Tokyo',
                'desc' => 'Un jardín de gatos en pleno Shibuya. Ambiente relajado y silencioso.',
                'img' => '/images/cafes/cafe-1.jpg',
            ],
            [
                'id' => 2,
                'name' => 'Usagi Paradise',
                'japanese_name' => 'うさぎパラダイス',
                'slug' => 'usagi-paradise',
                'location' => 'Harajuku, Tokyo',
                'category' => 'lounge',
                'animal_type' => 'conejo',
                'price' => 1100,
                'rating' => 4.8,
                'open' => '10:00:00',
                'close' => '19:00:00',
                'cap' => 15,
                'reserve' => 1,
                'lat' => 35.6663,
                'lon' => 139.7044,
                'timezone' => 'Asia/Tokyo',
                'desc' => 'El paraíso de los conejos. Disfruta de la compañía de estos suaves amigos.',
                'img' => '/images/cafes/cafe-2.jpg',
            ],
            [
                'id' => 3,
                'name' => 'Soft Cloud',
                'japanese_name' => 'ソフトクラウド',
                'slug' => 'soft-cloud',
                'location' => 'Shinjuku, Tokyo',
                'category' => 'lounge',
                'animal_type' => 'chinchilla',
                'price' => 1600,
                'rating' => 4.6,
                'open' => '14:00:00',
                'close' => '22:00:00',
                'cap' => 12,
                'reserve' => 1,
                'lat' => 35.6895,
                'lon' => 139.7004,
                'timezone' => 'Asia/Tokyo',
                'desc' => 'Café nocturno especializado en la suavidad de las chinchillas.',
                'img' => '/images/cafes/cafe-3.jpg',
            ],
            [
                'id' => 4,
                'name' => 'Chipmunk Forest',
                'japanese_name' => 'シマリスの森',
                'slug' => 'chipmunk-forest',
                'location' => 'Kichijoji, Tokyo',
                'category' => 'lounge',
                'animal_type' => 'ardilla',
                'price' => 1300,
                'rating' => 4.5,
                'open' => '10:00:00',
                'close' => '18:00:00',
                'cap' => 10,
                'reserve' => 1,
                'lat' => 35.7014,
                'lon' => 139.5878,
                'timezone' => 'Asia/Tokyo',
                'desc' => 'Un bosque de tubos y ramas donde las ardillas corren sobre ti.',
                'img' => '/images/cafes/cafe-4.jpg',
            ],

            // ----------------------------------------------------------------
            // DISTRITO PARQUE (Playroom)
            // ----------------------------------------------------------------
            [
                'id' => 5,
                'name' => 'Mame Shiba Café',
                'japanese_name' => '豆柴カフェ',
                'slug' => 'mame-shiba-cafe',
                'location' => 'Yoyogi Park, Tokyo',
                'category' => 'playroom',
                'animal_type' => 'perro',
                'price' => 1500,
                'rating' => 4.9,
                'open' => '11:00:00',
                'close' => '19:00:00',
                'cap' => 15,
                'reserve' => 1,
                'lat' => 35.6762,
                'lon' => 139.6943,
                'timezone' => 'Asia/Tokyo',
                'desc' => "Café dedicado a los Shiba Inu tamaño 'frijol'. Energía pura.",
                'img' => '/images/cafes/cafe-5.jpg',
            ],
            [
                'id' => 6,
                'name' => 'Mipig Cafe',
                'japanese_name' => 'マイピッグカフェ',
                'slug' => 'mipig-cafe',
                'location' => 'Meguro, Tokyo',
                'category' => 'playroom',
                'animal_type' => 'cerdito',
                'price' => 2000,
                'rating' => 4.8,
                'open' => '10:00:00',
                'close' => '20:00:00',
                'cap' => 20,
                'reserve' => 1,
                'lat' => 35.6454,
                'lon' => 139.7297,
                'timezone' => 'Asia/Tokyo',
                'desc' => 'Conoce a los micro-cerditos más adorables. ¡Se subirán a tu regazo!',
                'img' => '/images/cafes/cafe-6.jpg',
            ],
            [
                'id' => 7,
                'name' => 'Parrot Talk',
                'japanese_name' => 'おしゃべりオウム',
                'slug' => 'parrot-talk',
                'location' => 'Ueno, Tokyo',
                'category' => 'playroom',
                'animal_type' => 'loro',
                'price' => 1400,
                'rating' => 4.4,
                'open' => '11:00:00',
                'close' => '18:00:00',
                'cap' => 12,
                'reserve' => 1,
                'lat' => 35.7149,
                'lon' => 139.7736,
                'timezone' => 'Asia/Tokyo',
                'desc' => 'Interactúa con aves inteligentes y coloridas en un entorno seguro.',
                'img' => '/images/cafes/cafe-7.jpg',
            ],

            // ----------------------------------------------------------------
            // DISTRITO GRANJA (Urban Farm)
            // ----------------------------------------------------------------
            [
                'id' => 8,
                'name' => 'Capyba Land',
                'japanese_name' => 'カピバランド',
                'slug' => 'capyba-land',
                'location' => 'Odaiba, Tokyo',
                'category' => 'farm',
                'animal_type' => 'capybara',
                'price' => 1800,
                'rating' => 4.9,
                'open' => '09:00:00',
                'close' => '17:00:00',
                'cap' => 25,
                'reserve' => 1,
                'lat' => 35.6293,
                'lon' => 139.7752,
                'timezone' => 'Asia/Tokyo',
                'desc' => 'Relájate con los gentiles capibaras en su onsen privado.',
                'img' => '/images/cafes/cafe-8.jpg',
            ],
            [
                'id' => 9,
                'name' => 'Alpaca Hill',
                'japanese_name' => 'アルパカの丘',
                'slug' => 'alpaca-hill',
                'location' => 'Setagaya, Tokyo',
                'category' => 'farm',
                'animal_type' => 'alpaca',
                'price' => 1600,
                'rating' => 4.7,
                'open' => '10:00:00',
                'close' => '18:00:00',
                'cap' => 20,
                'reserve' => 1,
                'lat' => 35.6456,
                'lon' => 139.6215,
                'timezone' => 'Asia/Tokyo',
                'desc' => 'Acaricia la lana más suave del mundo y pasea con alpacas.',
                'img' => '/images/cafes/cafe-9.jpg',
            ],
            [
                'id' => 10,
                'name' => 'Little Hooves',
                'japanese_name' => '小さなひづめ',
                'slug' => 'little-hooves',
                'location' => 'Kichijoji, Tokyo',
                'category' => 'farm',
                'animal_type' => 'caballo',
                'price' => 1700,
                'rating' => 4.6,
                'open' => '10:00:00',
                'close' => '18:00:00',
                'cap' => 15,
                'reserve' => 1,
                'lat' => 35.7014,
                'lon' => 139.5878,
                'timezone' => 'Asia/Tokyo',
                'desc' => 'Caballos miniatura en el corazón de la ciudad.',
                'img' => '/images/cafes/cafe-10.jpg',
            ],
            [
                'id' => 11,
                'name' => 'Quack Club',
                'japanese_name' => 'クワッククラブ',
                'slug' => 'quack-club',
                'location' => 'Ikebukuro, Tokyo',
                'category' => 'farm',
                'animal_type' => 'pato',
                'price' => 1200,
                'rating' => 4.5,
                'open' => '11:00:00',
                'close' => '19:00:00',
                'cap' => 10,
                'reserve' => 1,
                'lat' => 35.7295,
                'lon' => 139.7107,
                'timezone' => 'Asia/Tokyo',
                'desc' => 'Patos Call adorables y esponjosos.',
                'img' => '/images/cafes/cafe-11.jpg',
            ],

            // ----------------------------------------------------------------
            // DISTRITO EXÓTICO (Zen / Terrarios)
            // ----------------------------------------------------------------
            [
                'id' => 12,
                'name' => 'Pui Pui House',
                'japanese_name' => 'プイプイハウス',
                'slug' => 'pui-pui-house',
                'location' => 'Nakano, Tokyo',
                'category' => 'zen',
                'animal_type' => 'cobaya',
                'price' => 1000,
                'rating' => 4.6,
                'open' => '11:00:00',
                'close' => '20:00:00',
                'cap' => 18,
                'reserve' => 1,
                'lat' => 35.7053,
                'lon' => 139.6655,
                'timezone' => 'Asia/Tokyo',
                'desc' => 'El sonido relajante de las cobayas pidiendo comida.',
                'img' => '/images/cafes/cafe-12.jpg',
            ],
            [
                'id' => 13,
                'name' => 'Prairie Town',
                'japanese_name' => 'プレーリータウン',
                'slug' => 'prairie-town',
                'location' => 'Ikebukuro, Tokyo',
                'category' => 'zen',
                'animal_type' => 'perrito_pradera',
                'price' => 1400,
                'rating' => 4.4,
                'open' => '12:00:00',
                'close' => '20:00:00',
                'cap' => 12,
                'reserve' => 1,
                'lat' => 35.7295,
                'lon' => 139.7107,
                'timezone' => 'Asia/Tokyo',
                'desc' => 'Observa las complejas relaciones sociales de estos roedores.',
                'img' => '/images/cafes/cafe-13.jpg',
            ],
            [
                'id' => 14,
                'name' => 'Slow Life',
                'japanese_name' => 'スローライフ',
                'slug' => 'slow-life',
                'location' => 'Roppongi, Tokyo',
                'category' => 'zen',
                'animal_type' => 'tortuga',
                'price' => 1500,
                'rating' => 4.8,
                'open' => '11:00:00',
                'close' => '21:00:00',
                'cap' => 20,
                'reserve' => 0,
                'lat' => 35.6627,
                'lon' => 139.7305,
                'timezone' => 'Asia/Tokyo',
                'desc' => 'Tómate tu tiempo. Aquí nadie tiene prisa.',
                'img' => '/images/cafes/cafe-14.jpg',
            ],
        ];

        // Preparar sentencias
        // Usar ON DUPLICATE KEY UPDATE para operaciones idempotentes
        $stmt = $this->db->prepare('
            INSERT INTO cafes (id, name, japanese_name, slug, location, category, animal_type, price_per_hour, rating_avg, description, opening_time, closing_time, capacity_max, has_reservations, image_url, latitude, longitude, timezone)
            VALUES (:id, :name, :jp, :slug, :loc, :cat, :type, :price, :rate, :desc, :open, :close, :cap, :res, :img, :lat, :lon, :tz)
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                japanese_name = VALUES(japanese_name),
                slug = VALUES(slug),
                location = VALUES(location),
                category = VALUES(category),
                animal_type = VALUES(animal_type),
                price_per_hour = VALUES(price_per_hour),
                rating_avg = VALUES(rating_avg),
                description = VALUES(description),
                opening_time = VALUES(opening_time),
                closing_time = VALUES(closing_time),
                capacity_max = VALUES(capacity_max),
                has_reservations = VALUES(has_reservations),
                image_url = VALUES(image_url),
                latitude = VALUES(latitude),
                longitude = VALUES(longitude),
                timezone = VALUES(timezone)
        ');

        $stmtZone = $this->db->prepare('INSERT INTO cafe_zones (cafe_id, name, type, capacity, requires_briefing, requires_shoes_off) VALUES (:cid, :name, :type, :cap, :brief, :shoes) ON DUPLICATE KEY UPDATE capacity = VALUES(capacity), requires_briefing = VALUES(requires_briefing), requires_shoes_off = VALUES(requires_shoes_off)');

        $stmtTracker = $this->db->prepare('INSERT INTO trackers (cafe_id, code, type) VALUES (:cid, :code, :type) ON DUPLICATE KEY UPDATE type = VALUES(type)');

        Logger::info('[CafeSeeder] building infrastructure');

        foreach ($cafes as $cafe) {
            try {
                // 1. Crear Café
                $stmt->execute([
                    ':id' => $cafe['id'],
                    ':name' => $cafe['name'],
                    ':jp' => $cafe['japanese_name'],
                    ':slug' => $cafe['slug'],
                    ':loc' => $cafe['location'],
                    ':cat' => $cafe['category'],
                    ':type' => $cafe['animal_type'],
                    ':price' => $cafe['price'],
                    ':rate' => $cafe['rating'],
                    ':desc' => $cafe['desc'],
                    ':open' => $cafe['open'],
                    ':close' => $cafe['close'],
                    ':cap' => $cafe['cap'],
                    ':res' => $cafe['reserve'],
                    ':img' => $cafe['img'],
                    ':lat' => $cafe['lat'],
                    ':lon' => $cafe['lon'],
                    ':tz' => $cafe['timezone'],
                ]);

                // 2. Crear Zonas Operativas
                // A. Recepción (Zona sucia)
                $stmtZone->execute([
                    ':cid' => $cafe['id'],
                    ':name' => 'Recepción',
                    ':type' => 'reception',
                    ':cap' => 10,
                    ':brief' => 0,
                    ':shoes' => 0,
                ]);

                // B. Cafetería (Zona limpia - Donde se come)
                $stmtZone->execute([
                    ':cid' => $cafe['id'],
                    ':name' => 'Cafetería',
                    ':type' => 'cafe',
                    ':cap' => $cafe['cap'],
                    ':brief' => 0,
                    ':shoes' => 0,
                ]);

                // C. Zona Interacción (Depende de la categoría)
                $briefing = 1;
                $shoesOff = ($cafe['category'] === 'lounge' || $cafe['category'] === 'playroom') ? 1 : 0;

                $stmtZone->execute([
                    ':cid' => $cafe['id'],
                    ':name' => 'Zona Animales',
                    ':type' => 'interaction',
                    ':cap' => $cafe['cap'],
                    ':brief' => $briefing,
                    ':shoes' => $shoesOff,
                ]);

                // D. Zona Descanso Animal (Backstage)
                $stmtZone->execute([
                    ':cid' => $cafe['id'],
                    ':name' => 'Cuarto de Descanso',
                    ':type' => 'rest',
                    ':cap' => 50,
                    ':brief' => 0,
                    ':shoes' => 0,
                ]);

                // 3. Crear Trackers Físicos (Para el sistema de comandas)
                // Generamos tantos trackers como capacidad tenga el local + 5 de repuesto
                for ($i = 1; $i <= ($cafe['cap'] + 5); $i++) {
                    // Código: Iniciales + Número (Ej: NEK-01)
                    $code = \strtoupper(\substr($cafe['slug'], 0, 3)) . '-' . \str_pad((string) $i, 2, '0', STR_PAD_LEFT);
                    $stmtTracker->execute([':cid' => $cafe['id'], ':code' => $code, ':type' => 'token']);
                }

                Logger::info('CafeSeeder: cafe processed', ['id' => $cafe['id'], 'slug' => $cafe['slug']]);
            } catch (Throwable $e) {
                Logger::error('CafeSeeder: failed to process cafe', ['id' => $cafe['id'], 'exception' => $e->getMessage()]);
            }
        }

        Logger::info('[CafeSeeder] completed');
    }
}
