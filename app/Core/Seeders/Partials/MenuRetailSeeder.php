<?php

declare(strict_types=1);

namespace App\Core\Seeders\Partials;

use App\Core\Database;
use JsonException;
use PDO;

final class MenuRetailSeeder
{
    private PDO $db;
    private array $allergenCache = [];

    public function __construct()
    {
        $this->db = Database::getConnection();
        $this->loadAllergens();
    }

    /**
     * Carga el cache de IDs de alérgenos
     */
    private function loadAllergens(): void
    {
        $stmt = $this->db->query('SELECT id, name FROM allergens');
        $allergens = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($allergens as $allergen) {
            $this->allergenCache[\strtolower($allergen['name'])] = (int) $allergen['id'];
        }
    }

    /**
     * Verifica si un producto ya existe
     */
    private function productExists(string $name, int $categoryId): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM products WHERE name = ? AND category_id = ?');
        $stmt->execute([$name, $categoryId]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function getExistingProductId(string $name, int $categoryId): int
    {
        $stmt = $this->db->prepare('SELECT id FROM products WHERE name = ? AND category_id = ?');
        $stmt->execute([$name, $categoryId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Asigna alérgenos a un producto
     */
    private function assignAllergens(int $productId, array $allergenNames): void
    {
        $stmt = $this->db->prepare('INSERT IGNORE INTO product_allergens (product_id, allergen_id) VALUES (?, ?)');

        foreach ($allergenNames as $name) {
            $allergenId = $this->allergenCache[\strtolower($name)] ?? null;

            if ($allergenId) {
                $stmt->execute([$productId, $allergenId]);
            }
        }
    }

    /**
     * @throws JsonException
     */
    public function run(): void
    {
        $this->insertCategory('animal_snacks', [
            [
                'name' => 'Vasito de Zanahorias',
                'jp' => 'にんじんカップ',
                'price' => 300,
                'desc' => 'Para conejos, cobayas, capibaras y alpacas.',
                'img' => '/images/menu/snacks/carrot.jpg',
                'target' => ['farm', 'zen', 'lounge'],
                'station' => 'assembly',
                'time' => 1,
                'ingred' => ['Zanahoria fresca lavada'],
                'steps' => "1. Cortar en bastones largos.\n2. Servir en vaso de papel.",
                'check' => 'Revisar frescura.',
            ],
            [
                'name' => 'Tiras de Pollo Seco',
                'jp' => 'ささみジャーキー',
                'price' => 400,
                'desc' => 'Snack premium para gatos y perros.',
                'img' => '/images/menu/snacks/chicken.jpg',
                'target' => ['lounge', 'playroom'],
                'station' => 'assembly',
                'time' => 1,
                'ingred' => ['Pollo deshidratado (Jerky)'],
                'steps' => 'Servir 3 piezas.',
                'check' => 'Trocear para gatos pequeños.',
            ],
            [
                'name' => 'Semillas de Girasol',
                'jp' => 'ひまわりの種',
                'price' => 200,
                'desc' => 'Para ardillas y loros.',
                'img' => '/images/menu/snacks/seeds.jpg',
                'target' => ['lounge', 'playroom'],
                'station' => 'assembly',
                'time' => 1,
                'ingred' => ['Semillas sin sal'],
                'steps' => 'Un puñado en cuenco.',
                'check' => 'Asegurar que no tienen sal.',
            ],
            [
                'name' => 'Hojas de Morera',
                'jp' => '桑の葉',
                'price' => 300,
                'desc' => 'Delicia fresca para tortugas.',
                'img' => '/images/menu/snacks/leaves.jpg',
                'target' => ['zen'],
                'station' => 'assembly',
                'time' => 1,
                'ingred' => ['Hojas Morera/Hibisco'],
                'steps' => 'Lavar y secar ligeramente.',
                'check' => 'Frescas y verdes.',
            ],
        ]);

        // ----------------------------------------------------------------
        // EXPERIENCIAS (PASES) — realistas y alineadas con cafes.category + animal_type
        // Precio por persona por defecto. Fijo solo cuando min_pax=max_pax>1.
        // ----------------------------------------------------------------
        $this->insertCategory('experiencias', [
            // GENÉRICOS
            [
                'name' => 'Pase Komorebi (60m)',
                'jp' => '木漏れ日プラン',
                'price' => 1200,
                'desc' => 'Acceso estándar de 60 minutos. Ideal para tu primera visita.',
                'img' => '/images/menu/experiencias/komorebi.jpg',
                'duration' => 60,
                'target' => null,
                'attrs' => ['includes_drink' => true],
            ],
            [
                'name' => 'Pack Relax (90m)',
                'jp' => 'ゆったりパック',
                'price' => 1800,
                'desc' => '90 minutos para desconectar. Recomendado para cafés tranquilos.',
                'img' => '/images/menu/experiencias/relax.jpg',
                'duration' => 90,
                'target' => ['lounge', 'zen'],
                'attrs' => ['includes_drink' => true, 'includes_dessert' => true, 'quiet' => true],
            ],
            [
                'name' => 'Cita Privada Tatami (60m · 2 pax)',
                'jp' => '個室プラン',
                'price' => 4500,
                'desc' => 'Sala privada para 2 personas. Experiencia íntima y tranquila.',
                'img' => '/images/menu/experiencias/private.jpg',
                'duration' => 60,
                'min_pax' => 2,
                'max_pax' => 2,
                'target' => ['lounge'],
                'attrs' => ['private_room' => true, 'includes_drink' => true],
            ],

            // LOUNGE
            [
                'name' => 'Neko no Niwa (60m)',
                'jp' => '猫の庭プラン',
                'price' => 1400,
                'desc' => 'Sesión tranquila con gatos. Recomendado para primera toma de contacto.',
                'img' => '/images/menu/experiencias/neko.jpg',
                'duration' => 60,
                'target' => ['lounge'],
                'animal_target' => ['gato'],
                'attrs' => ['quiet' => true, 'includes_drink' => true],
            ],
            [
                'name' => 'Usagi Paradise Calm (60m)',
                'jp' => 'うさぎ癒し',
                'price' => 1300,
                'desc' => 'Una hora de calma con conejos. Ritmo lento, contacto suave.',
                'img' => '/images/menu/experiencias/usagi.jpg',
                'duration' => 60,
                'target' => ['lounge'],
                'animal_target' => ['conejo'],
                'attrs' => ['quiet' => true],
            ],
            [
                'name' => 'Soft Cloud Night (90m)',
                'jp' => 'ソフトナイト',
                'price' => 2200,
                'desc' => 'Chinchillas en ambiente nocturno. Sesión extendida.',
                'img' => '/images/menu/experiencias/chinchilla-night.jpg',
                'duration' => 90,
                'target' => ['lounge'],
                'animal_target' => ['chinchilla'],
                'attrs' => ['allowed_start' => '18:00', 'allowed_end' => '22:00'],
            ],
            [
                'name' => 'Chipmunk Explorer (45m)',
                'jp' => 'シマリス探検',
                'price' => 1100,
                'desc' => 'Sesión corta para verlas correr por el circuito.',
                'img' => '/images/menu/experiencias/chipmunk.jpg',
                'duration' => 45,
                'target' => ['lounge'],
                'animal_target' => ['ardilla'],
            ],

            // PLAYROOM
            [
                'name' => 'Mame Shiba Play (60m)',
                'jp' => '豆柴タイム',
                'price' => 1900,
                'desc' => 'Sesión activa con perros. Ideal para gente con energía.',
                'img' => '/images/menu/experiencias/shiba.jpg',
                'duration' => 60,
                'target' => ['playroom'],
                'animal_target' => ['perro'],
                'attrs' => ['high_energy' => true],
            ],
            [
                'name' => 'Mipig Cuddle (60m)',
                'jp' => '子豚の時間',
                'price' => 2200,
                'desc' => 'Micro-cerditos en modo relax. Perfecto para fotos.',
                'img' => '/images/menu/experiencias/mipig.jpg',
                'duration' => 60,
                'target' => ['playroom'],
                'animal_target' => ['cerdito'],
            ],
            [
                'name' => 'Parrot Talk Mini Workshop (45m)',
                'jp' => 'おしゃべりオウム',
                'price' => 1600,
                'desc' => 'Interacción guiada con loros. Grupo reducido.',
                'img' => '/images/menu/experiencias/parrot.jpg',
                'duration' => 45,
                'min_pax' => 1,
                'max_pax' => 4,
                'target' => ['playroom'],
                'animal_target' => ['loro'],
                'attrs' => ['guided' => true],
            ],

            // FARM
            [
                'name' => 'Capyba Onsen (60m)',
                'jp' => 'カピバ温泉',
                'price' => 2300,
                'desc' => 'Sesión premium con capibaras. Ambiente tipo onsen.',
                'img' => '/images/menu/experiencias/capybara.jpg',
                'duration' => 60,
                'target' => ['farm'],
                'animal_target' => ['capybara'],
                'attrs' => ['includes_feed' => true],
            ],
            [
                'name' => 'Alpaca Stroll (60m)',
                'jp' => 'アルパカ散歩',
                'price' => 2100,
                'desc' => 'Acaricia y pasea con alpacas (según disponibilidad).',
                'img' => '/images/menu/experiencias/alpaca.jpg',
                'duration' => 60,
                'target' => ['farm'],
                'animal_target' => ['alpaca'],
                'attrs' => ['includes_feed' => true],
            ],
            [
                'name' => 'Little Hooves Meet (60m)',
                'jp' => '小さなひづめ',
                'price' => 2000,
                'desc' => 'Caballos miniatura. Sesión tranquila y guiada.',
                'img' => '/images/menu/experiencias/hooves.jpg',
                'duration' => 60,
                'target' => ['farm'],
                'animal_target' => ['caballo'],
                'attrs' => ['guided' => true],
            ],
            [
                'name' => 'Quack Club Feed (45m)',
                'jp' => 'クワックタイム',
                'price' => 1200,
                'desc' => 'Patos call: sesión corta con alimentación controlada.',
                'img' => '/images/menu/experiencias/duck.jpg',
                'duration' => 45,
                'target' => ['farm'],
                'animal_target' => ['pato'],
                'attrs' => ['includes_feed' => true],
            ],

            // ZEN
            [
                'name' => 'Pui Pui Zen (60m)',
                'jp' => 'プイプイ禅',
                'price' => 1200,
                'desc' => 'Cobayas en ambiente zen. Observación y contacto suave.',
                'img' => '/images/menu/experiencias/guinea.jpg',
                'duration' => 60,
                'target' => ['zen'],
                'animal_target' => ['cobaya'],
                'attrs' => ['quiet' => true],
            ],
            [
                'name' => 'Prairie Social (60m)',
                'jp' => 'プレーリー社交',
                'price' => 1600,
                'desc' => 'Perritos de la pradera: sesión tranquila para observar su dinámica.',
                'img' => '/images/menu/experiencias/prairie.jpg',
                'duration' => 60,
                'target' => ['zen'],
                'animal_target' => ['perrito_pradera'],
                'attrs' => ['quiet' => true],
            ],
        ]);

        // MERCH
        $this->insertCategory('merch', [
            [
                'name' => 'Peluche Mochi',
                'jp' => 'もちぬいぐるみ',
                'price' => 3500,
                'desc' => 'Tamaño real, extremadamente suave.',
                'img' => '/images/menu/merch/plushie.jpg',
                'target' => null,
            ],
            [
                'name' => 'Caja de Galletas',
                'jp' => '肉球クッキー缶',
                'price' => 1500,
                'desc' => 'Pastas de mantequilla con forma de huella.',
                'img' => '/images/menu/merch/cookies.jpg',
                'target' => null,
            ],
            [
                'name' => 'Tote Bag Ecológica',
                'jp' => 'オリジナルトート',
                'price' => 1200,
                'desc' => 'Bolsa de tela resistente con logo.',
                'img' => '/images/menu/merch/tote.jpg',
                'target' => null,
            ],
            [
                'name' => 'Gachapon: Pines',
                'jp' => '缶バッジガチャ',
                'price' => 300,
                'desc' => 'Chapas sorpresa de nuestros animales.',
                'img' => '/images/menu/merch/gacha.jpg',
                'target' => null,
            ],
            [
                'name' => 'Taza Cerámica',
                'jp' => 'マグカップ',
                'price' => 1800,
                'desc' => 'Hecha en Gifu. Gato asomando.',
                'img' => '/images/menu/merch/mug.jpg',
                'target' => null,
            ],
        ]);
    }

    /**
     * @param string                           $slug
     * @param array<int, array<string, mixed>> $items
     * @throws JsonException
     */
    private function insertCategory(string $slug, array $items): void
    {
        $stmtCat = $this->db->prepare('SELECT id FROM menu_categories WHERE slug = ? LIMIT 1');
        $stmtCat->execute([$slug]);
        $catId = $stmtCat->fetchColumn();

        if (!$catId) {
            echo "   -> Categoría '$slug' no existe (omitido).\n";

            return;
        }

        $productType = ($slug === 'experiencias') ? 'pass' : 'item';

        $stmtProd = $this->db->prepare('
            INSERT INTO products (
                category_id, product_type,
                name, japanese_name, description, price,
                station, prep_time, recipe_steps, ingredients_list, critical_check,
                duration_minutes, min_pax, max_pax,
                attributes,
                target_cafe_types, target_animal_types,
                image_url, is_active
            ) VALUES (
                :cat, :ptype,
                :name, :jp, :desc, :price,
                :station, :time, :steps, :ingred, :check,
                :dur, :min, :max,
                :attrs,
                :targets, :animalTargets,
                :img, 1
            )
        ');

        foreach ($items as $item) {
            // Verificar si el producto ya existe
            if ($this->productExists($item['name'], (int) $catId)) {
                echo "   -> '{$item['name']}' ya existe, asignando al\u00e9rgenos...\n";
                $existId = $this->getExistingProductId($item['name'], (int) $catId);
                if ($existId > 0 && !empty($item['allergens'])) {
                    $this->assignAllergens($existId, $item['allergens']);
                }
                continue;
            }

            $ingredJson = isset($item['ingred'])
                ? \json_encode($item['ingred'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)
                : null;

            $targetJson = \array_key_exists('target', $item) && $item['target'] !== null
                ? \json_encode($item['target'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)
                : null;

            $animalTargetsJson = isset($item['animal_target'])
                ? \json_encode($item['animal_target'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)
                : null;

            $attrsJson = isset($item['attrs'])
                ? \json_encode($item['attrs'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)
                : null;

            // Convertir 'duration' (en minutos) a 'duration_minutes'
            $duration = $item['duration'] ?? $item['duration_minutes'] ?? null;
            $minPax = $item['min_pax'] ?? 1;
            $maxPax = $item['max_pax'] ?? null;

            $stmtProd->execute([
                ':cat' => (int) $catId,
                ':ptype' => $productType,

                ':name' => (string) $item['name'],
                ':jp' => $item['jp'] ?? null,
                ':desc' => $item['desc'] ?? null,
                ':price' => (int) $item['price'],

                ':station' => $item['station'] ?? 'assembly',
                ':time' => (int) ($item['time'] ?? 0),
                ':steps' => $item['steps'] ?? null,
                ':ingred' => $ingredJson,
                ':check' => $item['check'] ?? null,

                ':dur' => $duration !== null ? (int) $duration : null,
                ':min' => (int) $minPax,
                ':max' => $maxPax !== null ? (int) $maxPax : null,

                ':attrs' => $attrsJson,

                ':targets' => $targetJson,
                ':animalTargets' => $animalTargetsJson,

                ':img' => $item['img'] ?? null,
            ]);

            // Asignar alérgenos si están definidos
            $productId = (int) $this->db->lastInsertId();
            if (!empty($item['allergens'])) {
                $this->assignAllergens($productId, $item['allergens']);
            }
        }

        echo "   -> Categoría '$slug' insertada.\n";
    }
}
