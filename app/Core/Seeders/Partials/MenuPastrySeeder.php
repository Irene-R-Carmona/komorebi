<?php

declare(strict_types=1);

namespace App\Core\Seeders\Partials;

use App\Core\Database;
use JsonException;
use PDO;

final class MenuPastrySeeder
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
        $stmtCat = $this->db->query("SELECT id FROM menu_categories WHERE slug = 'postres'");
        $catId = $stmtCat->fetchColumn();

        if (!$catId) {
            echo "Error: Categoría 'postres' no existe.\n";

            return;
        }

        $products = [
            // --- PARFAITS & COPAS (Cold Kitchen) ---
            [
                'name' => 'Torre de Matcha (Kyoto)',
                'jp' => '抹茶パフェ',
                'price' => 1250,
                'desc' => 'Arquitectura dulce. Capas de gelatina de té, anko, helado de matcha intenso, mochi blanco y nata.',
                'img' => '/images/menu/postres/matcha-parfait.jpg',
                'target' => ['lounge', 'zen'],
                'allergens' => ['lácteos', 'huevo', 'frutos secos'],
                'station' => 'kitchen_cold',
                'time' => 6,
                'ingred' => ['Gelatina Matcha', 'Cornflakes', 'Pasta Anko', 'Nata Montada', 'Helado Matcha', 'Shiratama (Mochi)', 'Castaña'],
                'steps' => "Montaje en copa alta (orden visual de abajo a arriba):\n1. Dados gelatina.\n2. Capa nata.\n3. Cornflakes (crujiente).\n4. Bola helado.\n5. Decorar con Anko, Mochi y Castaña.",
                'check' => 'Estética vertical perfecta. Limpiar bordes de copa.',
            ],
            [
                'name' => 'Pudding a la Mode',
                'jp' => 'プリンアラモード',
                'price' => 950,
                'desc' => 'Flan casero firme y sedoso, servido en copa larga con frutas frescas y caramelo amargo.',
                'img' => '/images/menu/postres/pudding.jpg',
                'target' => ['lounge'],
                'allergens' => ['lácteos', 'huevo'],
                'station' => 'kitchen_cold',
                'time' => 4,
                'ingred' => ['Flan Casero', 'Nata Montada', 'Fruta temporada (Melón, Fresa, Kiwi)', 'Cereza', 'Sirope Caramelo'],
                'steps' => "1. Desmoldar flan en plato alargado.\n2. Escudillar nata a los lados.\n3. Colocar fruta cortada decorativamente ('Corte oreja de conejo' para manzana).",
                'check' => 'Flan liso sin burbujas (cocción lenta).',
            ],
            [
                'name' => 'Parfait de Chocolate',
                'jp' => 'チョコバナナパフェ',
                'price' => 1100,
                'desc' => 'Helado de chocolate belga, plátano fresco, brownie casero y salsa de chocolate.',
                'img' => '/images/menu/postres/choco-parfait.jpg',
                'target' => ['lounge'],
                'allergens' => ['lácteos', 'huevo', 'gluten', 'frutos secos'],
                'station' => 'kitchen_cold',
                'time' => 5,
                'ingred' => ['Helado Choco', 'Plátano rodajas', 'Brownie dados', 'Nata', 'Sirope'],
                'steps' => "1. Base brownie y sirope.\n2. Nata.\n3. Plátano en paredes.\n4. Helado.\n5. Decorar.",
                'check' => 'Chocolate prohibido cerca de perros/gatos (tóxico). Revisar zona de cliente.',
            ],

            // --- CALIENTES & TARTAS (Bakery) ---
            [
                'name' => 'Fluffy Pancakes (Soufflé)',
                'jp' => '奇跡のパンケーキ',
                'price' => 1400,
                'desc' => 'Tres tortitas tan esponjosas que tiemblan. Se deshacen en la boca como una nube.',
                'img' => '/images/menu/postres/pancakes.jpg',
                'target' => ['lounge'],
                'allergens' => ['gluten', 'huevo', 'lácteos'],
                'station' => 'bakery',
                'time' => 20, // Cuello de botella
                'ingred' => ['Claras huevo (3)', 'Yemas (2)', 'Harina repostería', 'Polvo hornear', 'Azúcar', 'Leche', 'Mantequilla', 'Sirope Arce'],
                'steps' => "1. Montar merengue francés picos duros (crítico).\n2. Mezclar con yemas y harina suavemente.\n3. Plancha 150ºC. Poner masa alta.\n4. Añadir agua, tapar (vapor). 7 min.\n5. Voltear con cuidado. Tapar. 5 min.",
                'check' => 'Altura > 4cm. Textura temblorosa. Servir INMEDIATAMENTE (colapsan en 5 min).',
            ],
            [
                'name' => 'Shortcake de Fresa',
                'jp' => '苺のショートケーキ',
                'price' => 650,
                'desc' => 'Bizcocho genovés aireado, nata pura 35% MG y fresas dulces.',
                'img' => '/images/menu/postres/shortcake.jpg',
                'target' => ['lounge'],
                'allergens' => ['gluten', 'huevo', 'lácteos'],
                'station' => 'kitchen_cold',
                'time' => 2,
                'ingred' => ['Porción Tarta (Bizcocho Genovés)', 'Fresa fresca'],
                'steps' => "1. Cortar porción triangular.\n2. Comprobar que la nata está firme.\n3. Servir con tenedor postre.",
                'check' => 'Mantener cadena de frío < 4ºC.',
            ],
            [
                'name' => 'Basque Cheesecake',
                'jp' => 'バスクチーズケーキ',
                'price' => 600,
                'desc' => "Tarta de queso cremosa con la superficie 'quemada' caramelizada.",
                'img' => '/images/menu/postres/cheesecake.jpg',
                'target' => ['lounge', 'zen'],
                'allergens' => ['gluten', 'huevo', 'lácteos'],
                'station' => 'kitchen_cold',
                'time' => 2,
                'ingred' => ['Porción Tarta'],
                'steps' => "1. Cortar con cuchillo caliente para corte limpio.\n2. Servir con pizca de sal Maldon (opcional).",
                'check' => 'Interior cremoso, casi líquido al centro.',
            ],
            [
                'name' => 'Shibuya Honey Toast',
                'jp' => 'ハニートースト',
                'price' => 1300,
                'desc' => 'Media barra de pan vaciada y tostada con mantequilla y miel, rellena de helado.',
                'img' => '/images/menu/postres/honey-toast.jpg',
                'target' => ['lounge'],
                'allergens' => ['gluten', 'lácteos', 'huevo'],
                'station' => 'bakery',
                'time' => 12,
                'ingred' => ['1/3 Barra Pan Molde (entera)', 'Mantequilla', 'Miel', 'Helado Vainilla', 'Frutos rojos'],
                'steps' => "1. Vaciar miga del bloque de pan y cortarla en dados.\n2. Untar todo con mantequilla y miel.\n3. Tostar carcasa y dados en horno hasta dorado.\n4. Volver a meter dados dentro.\n5. Coronar con helado y fruta.",
                'check' => 'Pan caliente y crujiente, helado empezando a derretirse.',
            ],
            [
                'name' => 'Mille Crepes',
                'jp' => 'ミルクレープ',
                'price' => 650,
                'desc' => 'Veinte capas de crepe finísima intercaladas con crema pastelera ligera.',
                'img' => '/images/menu/postres/mille-crepe.jpg',
                'target' => ['lounge'],
                'allergens' => ['gluten', 'lácteos', 'huevo'],
                'station' => 'kitchen_cold',
                'time' => 2,
                'ingred' => ['Porción'],
                'steps' => "1. Emplatar.\n2. Opcional: espolvorear azúcar glas.",
                'check' => 'Corte limpio, capas visibles.',
            ],

            // --- TRADICIONALES (Wagashi) ---
            [
                'name' => 'Taiyaki',
                'jp' => 'たい焼き',
                'price' => 350,
                'desc' => 'Pastel caliente con forma de pez.',
                'img' => '/images/menu/postres/taiyaki.jpg',
                'target' => ['farm', 'playroom'],
                'allergens' => ['gluten', 'huevo', 'lácteos', 'soja'],
                'station' => 'bakery',
                'time' => 3,
                'ingred' => ['Taiyaki (Anko o Crema)'],
                'steps' => "1. Regenerar en horno/salamandra 2-3 min para recuperar textura crujiente exterior.\n2. Servir en bolsa de papel abierta.",
                'check' => 'Caliente al tacto. No servir gomoso (microondas prohibido).',
            ],
            [
                'name' => 'Anmitsu Clásico',
                'jp' => 'クリームあんみつ',
                'price' => 750,
                'desc' => 'Cubos de agar-agar, frutas, anko, helado y dango.',
                'img' => '/images/menu/postres/anmitsu.jpg',
                'target' => ['zen'],
                'allergens' => ['lácteos'],
                'station' => 'kitchen_cold',
                'time' => 4,
                'ingred' => ['Agar (cubos)', 'Fruta en almíbar', 'Pasta Anko', 'Shiratama', 'Sirope Kuromitsu (aparte)'],
                'steps' => "1. Base de agar y fruta escurrida.\n2. Bola de Anko.\n3. Bolitas Shiratama.\n4. Servir sirope en jarrita.",
                'check' => 'Estética tradicional.',
            ],
            [
                'name' => 'Dorayaki',
                'jp' => 'どら焼き',
                'price' => 300,
                'desc' => 'Dos tortitas de miel esponjosas rellenas de pasta de judía roja.',
                'img' => '/images/menu/postres/dorayaki.jpg',
                'target' => ['farm', 'playroom'],
                'allergens' => ['gluten', 'huevo', 'soja'],
                'station' => 'bakery',
                'time' => 0, // Listo para comer
                'ingred' => ['Dorayaki envasado artesanal'],
                'steps' => '1. Servir en plato o para llevar.',
                'check' => 'Comprobar fecha caducidad diaria.',
            ],
            [
                'name' => 'Warabimochi',
                'jp' => 'わらび餅',
                'price' => 500,
                'desc' => 'Gelatina de helecho suave cubierta de kinako.',
                'img' => '/images/menu/postres/warabimochi.jpg',
                'target' => ['zen'],
                'allergens' => ['soja'],
                'station' => 'kitchen_cold',
                'time' => 2,
                'ingred' => ['Warabimochi', 'Kinako (Harina soja)', 'Kuromitsu'],
                'steps' => "1. Cortar masa gelatinosa en cubos.\n2. Rebozar generosamente en Kinako.\n3. Servir con palillo de madera.",
                'check' => 'Textura fresca y blanda.',
            ],
        ];

        $stmtProd = $this->db->prepare('
            INSERT INTO products (category_id, name, japanese_name, description, price, station, prep_time, recipe_steps, ingredients_list, critical_check, target_cafe_types, image_url, is_active)
            VALUES (:cat, :name, :jp, :desc, :price, :station, :time, :steps, :ingred, :check, :targets, :img, 1)
        ');

        foreach ($products as $item) {
            // Verificar si el producto ya existe
            if ($this->productExists($item['name'], $catId)) {
                echo "   -> '{$item['name']}' ya existe, saltando...
";
                continue;
            }

            $stmtProd->execute([
                ':cat' => $catId,
                ':name' => $item['name'],
                ':jp' => $item['jp'],
                ':desc' => $item['desc'],
                ':price' => $item['price'],
                ':station' => $item['station'],
                ':time' => $item['time'],
                ':steps' => $item['steps'],
                ':ingred' => \json_encode($item['ingred'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                ':check' => $item['check'],
                ':targets' => !empty($item['target']) ? \json_encode($item['target'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) : null,
                ':img' => $item['img'],
            ]);

            // Asignar alérgenos
            $productId = (int) $this->db->lastInsertId();
            $this->assignAllergens($productId, $item['allergens']);
        }
        echo "   -> Repostería insertada con alérgenos.\n";
    }
}
