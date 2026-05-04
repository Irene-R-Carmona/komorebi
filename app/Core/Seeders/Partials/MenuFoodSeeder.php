<?php

declare(strict_types=1);

namespace App\Core\Seeders\Partials;

use App\Core\Database;
use JsonException;
use PDO;

final class MenuFoodSeeder
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
        $stmtCat = $this->db->query("SELECT id FROM menu_categories WHERE slug = 'comida'");
        $catId = $stmtCat->fetchColumn();

        if (!$catId) {
            echo "Error: Categoría 'comida' no existe.\n";

            return;
        }

        $products = [
            [
                'name' => "Omurice 'Durmiendo'",
                'jp' => 'おやすみオムライス',
                'price' => 1100,
                'desc' => 'Arroz con pollo envuelto en una manta de huevo amarillo brillante.',
                'img' => '/images/menu/comida/omurice.jpg',
                'target' => ['lounge', 'playroom'],
                'allergens' => ['gluten', 'huevo', 'lácteos'],
                'station' => 'kitchen_hot',
                'time' => 12,
                'ingred' => ['Arroz cocido (180g)', 'Muslo pollo picado (50g)', 'Cebolla brunoise', 'Ketchup', 'Huevo (3u)', 'Nata (10ml)'],
                'steps' => "1. Sofreír pollo y cebolla. Añadir arroz y ketchup. Saltear hasta integrar (Chicken Rice).\n2. Moldear arroz en plato con forma ovalada.\n3. Batir 3 huevos con nata y sal. Sartén antiadherente fuego medio.\n4. Remover rápido con palillos para crear cuajada cremosa, dejar asentar el fondo.\n5. Deslizar sobre el arroz (estilo manta).\n6. Dibujar cara de oso durmiendo con biberón de ketchup.",
                'check' => 'Huevo jugoso (baveuse) por dentro, no seco. Temp servicio > 65ºC (HACCP).',
            ],
            [
                'name' => 'Curry Japonés (Suave)',
                'jp' => '野菜カレー',
                'price' => 1200,
                'desc' => 'Curry espeso, oscuro y dulzón con verduras fritas.',
                'img' => '/images/menu/comida/curry.jpg',
                'target' => ['lounge', 'zen'],
                'allergens' => ['gluten', 'soja'],
                'station' => 'kitchen_hot',
                'time' => 6,
                'ingred' => ['Arroz Gohan (200g)', 'Salsa Curry (Batch)', 'Renkon (Raíz loto)', 'Calabaza', 'Berenjena', 'Fukujinzuke (Encurtido)'],
                'steps' => "1. Emplatar arroz (mitad) y salsa caliente (mitad).\n2. Freír verduras al momento (Tempura sin rebozar o salteado).\n3. Colocar verduras con altura en el centro del plato.\n4. Acompañar de encurtidos rojos Fukujinzuke.",
                'check' => 'Salsa muy caliente y brillante. Verduras crujientes, no aceitosas.',
            ],
            [
                'name' => 'Takoyaki (6u)',
                'jp' => 'たこ焼き',
                'price' => 600,
                'desc' => 'Bolitas de masa rellenas de pulpo.',
                'img' => '/images/menu/comida/takoyaki.jpg',
                'target' => ['farm', 'playroom'],
                'allergens' => ['gluten', 'huevo', 'pescado', 'moluscos'],
                'station' => 'kitchen_hot',
                'time' => 5,
                'ingred' => ['Takoyaki (Congelado Calidad Premium)', 'Salsa Otafuku', 'Mayonesa Japonesa', 'Aonori (Alga)', 'Katsuobushi (Bonito)'],
                'steps' => "1. Freidora 180ºC durante 4:30 min (hasta dorado).\n2. Escurrir bien aceite.\n3. Poner en barca de bambú.\n4. Salsear líneas finas de Otafuku y Mayonesa.\n5. Espolvorear toppings generosos.",
                'check' => 'Interior debe estar hirviendo (Avisar riesgo quemadura). Copos de bonito deben moverse por el calor.',
            ],
            [
                'name' => 'Espaguetis Napolitan',
                'jp' => 'ナポリタン',
                'price' => 950,
                'desc' => 'Pasta retro salteada con salsa de tomate dulce.',
                'img' => '/images/menu/comida/napolitan.jpg',
                'target' => ['lounge', 'zen'],
                'allergens' => ['gluten', 'lácteos'],
                'station' => 'kitchen_hot',
                'time' => 8,
                'ingred' => ['Espagueti grueso (2.2mm) precocido', 'Cebolla juliana', 'Pimiento Verde tiras', 'Salchicha Viena rodajas', 'Ketchup', 'Mantequilla'],
                'steps' => "1. Saltear verduras y salchicha en mantequilla.\n2. Añadir pasta.\n3. Añadir Ketchup y un cazo de agua.\n4. SOFREÍR EL KETCHUP: Cocinar a fuego alto hasta que el ácido se evapore y la salsa espese y caramelice (Clave del sabor Napolitan).\n5. Servir con parmesano y tabasco aparte.",
                'check' => 'No debe quedar caldoso, la salsa debe estar adherida a la pasta. Sabor caramelizado.',
            ],
            [
                'name' => 'Katsu Sando Premium',
                'jp' => '特製カツサンド',
                'price' => 1000,
                'desc' => 'Sándwich de chuleta de cerdo empanada gruesa.',
                'img' => '/images/menu/comida/katsu-sando.jpg',
                'target' => null,
                'allergens' => ['gluten', 'huevo', 'mostaza'],
                'station' => 'kitchen_hot',
                'time' => 10,
                'ingred' => ['Lomo Cerdo (120g)', 'Panko grueso', 'Pan Shokupan (2cm grosor)', 'Salsa Tonkatsu', 'Col rallada', 'Mostaza Karashi'],
                'steps' => "1. Empanar cerdo (Harina -> Huevo -> Panko).\n2. Freír 170ºC 5 min. Reposar 2 min en rejilla.\n3. Tostar pan ligeramente (solo una cara).\n4. Untar salsa y mostaza.\n5. Montar: Pan - Col - Carne - Pan.\n6. Cortar bordes y dividir en 3 rectángulos.",
                'check' => 'Cerdo cocido completo (>75ºC) pero jugoso. Corte limpio con cuchillo de sierra.',
            ],
            [
                'name' => 'Tamago Sando',
                'jp' => 'たまごサンド',
                'price' => 750,
                'desc' => 'Sándwich de ensalada de huevo rica y cremosa.',
                'img' => '/images/menu/comida/tamago-sando.jpg',
                'target' => null,
                'allergens' => ['gluten', 'huevo', 'soja', 'mostaza'],
                'station' => 'kitchen_cold',
                'time' => 3,
                'ingred' => ['Mezcla Huevo (Huevo duro picado + Mayo Kewpie + Sal/Pimienta)', 'Pan Shokupan'],
                'steps' => "1. Usar mezcla de huevo preparada del día (Mise en place).\n2. Untar generosamente en el centro del pan (montaña).\n3. Cerrar y presionar suavemente.\n4. Cortar bordes.\n5. Cortar en 3 rectángulos o 2 triángulos.",
                'check' => 'Pan esponjoso, no aplastado en los bordes. Relleno abundante.',
            ],
            [
                'name' => 'Pizza Toast Retro',
                'jp' => 'ピザトースト',
                'price' => 650,
                'desc' => 'Rebanada de pan extra gruesa tostada con queso.',
                'img' => '/images/menu/comida/pizza-toast.jpg',
                'target' => ['farm', 'playroom'],
                'allergens' => ['gluten', 'lácteos'],
                'station' => 'kitchen_hot',
                'time' => 5,
                'ingred' => ['Pan Shokupan (4cm grosor)', 'Salsa Tomate/Pizza', 'Queso Mozzarella', 'Pepperoni/Salami', 'Pimiento verde'],
                'steps' => "1. Untar salsa en el pan.\n2. Colocar ingredientes y cubrir con queso.\n3. Gratinar en salamandra u horno hasta que el queso burbujee y se dore.\n4. Cortar en X.",
                'check' => 'Queso fundido y elástico. Pan crujiente por fuera, suave por dentro.',
            ],
            [
                'name' => 'Cesta de Karaage',
                'jp' => '若鶏の唐揚げ',
                'price' => 650,
                'desc' => 'Pollo frito al estilo japonés. Marinado en jengibre y soja.',
                'img' => '/images/menu/comida/karaage.jpg',
                'target' => ['farm', 'zen'],
                'allergens' => ['gluten', 'soja'],
                'station' => 'kitchen_hot',
                'time' => 6,
                'ingred' => ['Contramuslo pollo marinado (Soja, Sake, Jengibre, Ajo)', 'Fécula de Patata (Katakuriko)', 'Aceite'],
                'steps' => "1. Escurrir pollo del marinado.\n2. Rebozar en fécula justo antes de freír (para que quede blanco/crujiente).\n3. Doble fritura: 160ºC 3 min (cocción) -> Reposo 2 min -> 190ºC 1 min (crunch).\n4. Servir con limón y mayonesa.",
                'check' => 'Color dorado oscuro pero no quemado. Super crujiente por fuera.',
            ],
            [
                'name' => 'Taco Rice de Okinawa',
                'jp' => 'タコライス',
                'price' => 1000,
                'desc' => 'Arroz blanco cubierto de carne de taco, lechuga y queso.',
                'img' => '/images/menu/comida/taco-rice.jpg',
                'target' => ['farm', 'playroom'],
                'allergens' => ['gluten', 'lácteos', 'soja'],
                'station' => 'kitchen_hot',
                'time' => 6,
                'ingred' => ['Arroz', 'Carne Taco Mix (Batch)', 'Queso Cheddar rallado', 'Lechuga Iceberg juliana', 'Tomate dados', 'Salsa picante'],
                'steps' => "1. Base de arroz caliente.\n2. Carne caliente encima.\n3. Queso rallado (que funda con el calor de la carne).\n4. Montaña de lechuga fría encima.\n5. Tomate y salsa.",
                'check' => 'Contraste de temperaturas: Arroz/Carne caliente vs Lechuga fría.',
            ],
            [
                'name' => 'Pasta Mentaiko',
                'jp' => '明太子パスタ',
                'price' => 1050,
                'desc' => 'Salsa cremosa rosa hecha con huevas de abadejo y mantequilla.',
                'img' => '/images/menu/comida/mentaiko.jpg',
                'target' => ['lounge', 'zen'],
                'allergens' => ['gluten', 'pescado', 'lácteos'],
                'station' => 'kitchen_hot',
                'time' => 8,
                'ingred' => ['Pasta', 'Salsa Mentaiko (Batch: Huevas + Nata)', 'Mantequilla', 'Nori tiras', 'Shiso (opcional)'],
                'steps' => "1. Cocer pasta.\n2. Mezclar en un bol con la salsa y la mantequilla (fuera del fuego, el calor residual cocina la hueva).\n3. Emplatar y decorar con Nori en el centro.",
                'check' => 'No cocinar la salsa en sartén (se corta y cambia sabor). Textura cremosa rosa.',
            ],
        ];

        $stmtProd = $this->db->prepare('
            INSERT INTO products (category_id, name, japanese_name, description, price, station, prep_time, recipe_steps, ingredients_list, critical_check, target_cafe_types, image_url, is_active)
            VALUES (:cat, :name, :jp, :desc, :price, :station, :time, :steps, :ingred, :check, :targets, :img, 1)
        ');

        foreach ($products as $item) {
            // Verificar si el producto ya existe
            if ($this->productExists($item['name'], $catId)) {
                echo "   -> '{$item['name']}' ya existe, asignando al\u00e9rgenos...\n";
                $existId = $this->getExistingProductId($item['name'], $catId);
                $this->assignAllergens($existId, $item['allergens']);
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
        echo "   -> Comida insertada con alérgenos.\n";
    }
}
