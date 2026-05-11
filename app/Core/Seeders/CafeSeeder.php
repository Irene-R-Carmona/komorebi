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
            // LOUNGE — Ambiente tranquilo, contacto suave
            // ----------------------------------------------------------------
            [
                'id' => 1,
                'name' => 'Neko no Niwa',
                'japanese_name' => '猫の庭',
                'slug' => 'neko-no-niwa',
                'location' => 'Malasaña, Madrid',
                'city' => 'Madrid',
                'district' => 'Shibuya',
                'category' => 'lounge',
                'animal_type' => 'gato',
                'min_age' => null,
                'price' => 1300,
                'rating' => 4.8,
                'open' => '11:00:00',
                'close' => '20:00:00',
                'cap' => 25,
                'reserve' => 1,
                'lat' => 40.4241,
                'lon' => -3.7084,
                'timezone' => 'Europe/Madrid',
                'desc' => 'Un jardín de gatos en pleno Malasaña. Ambiente japonés relajado donde los felinos marcan el ritmo.',
                'img' => '/images/cafes/cafe-1.jpg',
            ],
            [
                'id' => 3,
                'name' => 'Soft Cloud',
                'japanese_name' => 'ソフトクラウド',
                'slug' => 'soft-cloud',
                'location' => 'Ruzafa, Valencia',
                'city' => 'Valencia',
                'district' => 'Shinjuku',
                'category' => 'lounge',
                'animal_type' => 'chinchilla',
                'min_age' => null,
                'price' => 1300,
                'rating' => 4.7,
                'open' => '11:00:00',
                'close' => '20:00:00',
                'cap' => 15,
                'reserve' => 1,
                'lat' => 39.4644,
                'lon' => -0.3860,
                'timezone' => 'Europe/Madrid',
                'desc' => 'La suavidad imposible de las chinchillas en el barrio más trendy de Valencia.',
                'img' => '/images/cafes/cafe-3.jpg',
            ],
            [
                'id' => 5,
                'name' => 'Mame Shiba Café',
                'japanese_name' => '豆柴カフェ',
                'slug' => 'mame-shiba-cafe',
                'location' => 'Barrio de Salamanca, Madrid',
                'city' => 'Madrid',
                'district' => 'Yoyogi',
                'category' => 'lounge',
                'animal_type' => 'perro',
                'min_age' => null,
                'price' => 1300,
                'rating' => 4.9,
                'open' => '10:00:00',
                'close' => '20:00:00',
                'cap' => 30,
                'reserve' => 1,
                'lat' => 40.4217,
                'lon' => -3.6885,
                'timezone' => 'Europe/Madrid',
                'desc' => 'Los Shiba Inu más pequeños de Madrid te esperan en el Barrio de Salamanca. Energía pura y amor sin límites.',
                'img' => '/images/cafes/cafe-5.jpg',
            ],
            [
                'id' => 12,
                'name' => 'Pui Pui House',
                'japanese_name' => 'プイプイハウス',
                'slug' => 'pui-pui-house',
                'location' => 'Centro, San Sebastián',
                'city' => 'San Sebastián',
                'district' => 'Nakano',
                'category' => 'lounge',
                'animal_type' => 'cobaya',
                'min_age' => null,
                'price' => 1300,
                'rating' => 4.6,
                'open' => '11:00:00',
                'close' => '20:00:00',
                'cap' => 18,
                'reserve' => 1,
                'lat' => 43.3183,
                'lon' => -1.9812,
                'timezone' => 'Europe/Madrid',
                'desc' => 'El sonido relajante de las cobayas pidiendo comida en la Parte Vieja donostiarra.',
                'img' => '/images/cafes/cafe-12.jpg',
            ],

            // ----------------------------------------------------------------
            // PLAYROOM — Actividad e interacción lúdica
            // ----------------------------------------------------------------
            [
                'id' => 2,
                'name' => 'Usagi Paradise',
                'japanese_name' => 'うさぎパラダイス',
                'slug' => 'usagi-paradise',
                'location' => 'El Born, Barcelona',
                'city' => 'Barcelona',
                'district' => 'Harajuku',
                'category' => 'playroom',
                'animal_type' => 'conejo',
                'min_age' => null,
                'price' => 1600,
                'rating' => 4.6,
                'open' => '11:00:00',
                'close' => '20:00:00',
                'cap' => 20,
                'reserve' => 1,
                'lat' => 41.3839,
                'lon' => 2.1801,
                'timezone' => 'Europe/Madrid',
                'desc' => 'Paraíso de conejos en el corazón del Born. Suaves y curiosos, te acompañarán toda la visita.',
                'img' => '/images/cafes/cafe-2.jpg',
            ],
            [
                'id' => 4,
                'name' => 'Chipmunk Forest',
                'japanese_name' => 'シマリスの森',
                'slug' => 'chipmunk-forest',
                'location' => 'Casco Viejo, Bilbao',
                'city' => 'Bilbao',
                'district' => 'Kichijoji',
                'category' => 'playroom',
                'animal_type' => 'ardilla',
                'min_age' => null,
                'price' => 1600,
                'rating' => 4.5,
                'open' => '10:00:00',
                'close' => '19:00:00',
                'cap' => 12,
                'reserve' => 1,
                'lat' => 43.2592,
                'lon' => -2.9258,
                'timezone' => 'Europe/Madrid',
                'desc' => 'Un bosque de tubos y ramas donde las ardillas corren sobre ti en el Casco Viejo bilbaíno.',
                'img' => '/images/cafes/cafe-4.jpg',
            ],
            [
                'id' => 6,
                'name' => 'Mipig Cafe',
                'japanese_name' => 'マイピッグカフェ',
                'slug' => 'mipig-cafe',
                'location' => 'Centro, Málaga',
                'city' => 'Málaga',
                'district' => 'Meguro',
                'category' => 'playroom',
                'animal_type' => 'cerdito',
                'min_age' => 10,
                'price' => 1600,
                'rating' => 4.8,
                'open' => '11:00:00',
                'close' => '20:00:00',
                'cap' => 20,
                'reserve' => 1,
                'lat' => 36.7192,
                'lon' => -4.4210,
                'timezone' => 'Europe/Madrid',
                'desc' => 'Los micro-cerditos más adorables de Málaga. ¡Se subirán a tu regazo sin permiso!',
                'img' => '/images/cafes/cafe-6.jpg',
            ],
            [
                'id' => 7,
                'name' => 'Parrot Talk',
                'japanese_name' => 'おしゃべりオウム',
                'slug' => 'parrot-talk',
                'location' => 'Alameda, Sevilla',
                'city' => 'Sevilla',
                'district' => 'Ueno',
                'category' => 'playroom',
                'animal_type' => 'loro',
                'min_age' => 8,
                'price' => 1600,
                'rating' => 4.6,
                'open' => '11:00:00',
                'close' => '19:00:00',
                'cap' => 18,
                'reserve' => 1,
                'lat' => 37.3960,
                'lon' => -5.9943,
                'timezone' => 'Europe/Madrid',
                'desc' => 'Loros inteligentes y coloridos en la Alameda sevillana. Aprenden tu nombre antes de que termines el café.',
                'img' => '/images/cafes/cafe-7.jpg',
            ],
            [
                'id' => 13,
                'name' => 'Prairie Town',
                'japanese_name' => 'プレーリータウン',
                'slug' => 'prairie-town',
                'location' => 'Lavapiés, Madrid',
                'city' => 'Madrid',
                'district' => 'Ikebukuro',
                'category' => 'playroom',
                'animal_type' => 'perrito_pradera',
                'min_age' => null,
                'price' => 1600,
                'rating' => 4.4,
                'open' => '12:00:00',
                'close' => '20:00:00',
                'cap' => 12,
                'reserve' => 1,
                'lat' => 40.4068,
                'lon' => -3.7012,
                'timezone' => 'Europe/Madrid',
                'desc' => 'Observa las complejas relaciones sociales de estos roedores en el multicultural Lavapiés.',
                'img' => '/images/cafes/cafe-13.jpg',
            ],

            // ----------------------------------------------------------------
            // FARM — Contacto con animales de mayor tamaño
            // ----------------------------------------------------------------
            [
                'id' => 8,
                'name' => 'Capyba Land',
                'japanese_name' => 'カピバランド',
                'slug' => 'capyba-land',
                'location' => 'Junto a la Albufera, Valencia',
                'city' => 'Valencia',
                'district' => 'Odaiba',
                'category' => 'farm',
                'animal_type' => 'capibara',
                'min_age' => null,
                'price' => 1900,
                'rating' => 4.7,
                'open' => '10:00:00',
                'close' => '18:00:00',
                'cap' => 25,
                'reserve' => 1,
                'lat' => 39.3467,
                'lon' => -0.3563,
                'timezone' => 'Europe/Madrid',
                'desc' => 'Los capibaras más tranquilos de la costa mediterránea. Zen animal cerca de la Albufera valenciana.',
                'img' => '/images/cafes/cafe-8.jpg',
            ],
            [
                'id' => 9,
                'name' => 'Alpaca Hill',
                'japanese_name' => 'アルパカの丘',
                'slug' => 'alpaca-hill',
                'location' => 'Afueras, Segovia',
                'city' => 'Segovia',
                'district' => 'Setagaya',
                'category' => 'farm',
                'animal_type' => 'alpaca',
                'min_age' => null,
                'price' => 1900,
                'rating' => 4.7,
                'open' => '10:00:00',
                'close' => '18:00:00',
                'cap' => 20,
                'reserve' => 1,
                'lat' => 40.9488,
                'lon' => -4.1184,
                'timezone' => 'Europe/Madrid',
                'desc' => 'Acaricia la lana más suave del mundo con vistas a los campos segovianos.',
                'img' => '/images/cafes/cafe-9.jpg',
            ],
            [
                'id' => 10,
                'name' => 'Little Hooves',
                'japanese_name' => '小さなひづめ',
                'slug' => 'little-hooves',
                'location' => 'Centro, Jerez de la Frontera',
                'city' => 'Jerez de la Frontera',
                'district' => 'Kichijoji',
                'category' => 'farm',
                'animal_type' => 'caballo',
                'min_age' => 6,
                'price' => 1900,
                'rating' => 4.6,
                'open' => '10:00:00',
                'close' => '18:00:00',
                'cap' => 15,
                'reserve' => 1,
                'lat' => 36.6850,
                'lon' => -6.1261,
                'timezone' => 'Europe/Madrid',
                'desc' => 'Caballos miniatura en el corazón de la ciudad del flamenco y el jerez.',
                'img' => '/images/cafes/cafe-10.jpg',
            ],
            [
                'id' => 11,
                'name' => 'Quack Club',
                'japanese_name' => 'クワッククラブ',
                'slug' => 'quack-club',
                'location' => 'Ribera del Ebro, Zaragoza',
                'city' => 'Zaragoza',
                'district' => 'Ikebukuro',
                'category' => 'farm',
                'animal_type' => 'pato',
                'min_age' => null,
                'price' => 1900,
                'rating' => 4.5,
                'open' => '11:00:00',
                'close' => '19:00:00',
                'cap' => 10,
                'reserve' => 1,
                'lat' => 41.6542,
                'lon' => -0.8769,
                'timezone' => 'Europe/Madrid',
                'desc' => 'Patos adorables junto al Ebro. Los más esponjosos te seguirán por toda la granja.',
                'img' => '/images/cafes/cafe-11.jpg',
            ],

            // ----------------------------------------------------------------
            // ZEN — Contemplación y ritmo pausado
            // ----------------------------------------------------------------
            [
                'id' => 14,
                'name' => 'Slow Life',
                'japanese_name' => 'スローライフ',
                'slug' => 'slow-life',
                'location' => 'Centro, Alicante',
                'city' => 'Alicante',
                'district' => 'Roppongi',
                'category' => 'zen',
                'animal_type' => 'tortuga',
                'min_age' => null,
                'price' => 900,
                'rating' => 4.8,
                'open' => '11:00:00',
                'close' => '21:00:00',
                'cap' => 20,
                'reserve' => 1,
                'lat' => 38.3452,
                'lon' => -0.4810,
                'timezone' => 'Europe/Madrid',
                'desc' => 'Tómate tu tiempo. En Alicante, con las tortugas, nadie tiene prisa.',
                'img' => '/images/cafes/cafe-14.jpg',
            ],
        ];

        // Preparar sentencias
        // Usar ON DUPLICATE KEY UPDATE para operaciones idempotentes
        $stmt = $this->db->prepare('
            INSERT INTO cafes (id, name, japanese_name, slug, location, city, themed_district, category, animal_type, min_age_years, price_per_hour, rating_avg, description, opening_time, closing_time, capacity_max, has_reservations, image_url, latitude, longitude, timezone)
            VALUES (:id, :name, :jp, :slug, :loc, :city, :district, :cat, :type, :min_age, :price, :rate, :desc, :open, :close, :cap, :res, :img, :lat, :lon, :tz)
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                japanese_name = VALUES(japanese_name),
                slug = VALUES(slug),
                location = VALUES(location),
                city = VALUES(city),
                themed_district = VALUES(themed_district),
                category = VALUES(category),
                animal_type = VALUES(animal_type),
                min_age_years = VALUES(min_age_years),
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
                    ':city' => $cafe['city'],
                    ':district' => $cafe['district'],
                    ':cat' => $cafe['category'],
                    ':type' => $cafe['animal_type'],
                    ':min_age' => $cafe['min_age'],
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
