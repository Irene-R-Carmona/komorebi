<?php

declare(strict_types=1);

namespace App\Core\Seeders\Partials;

use App\Core\Database;
use JsonException;
use PDO;

final class MenuBeverageSeeder
{
    private PDO $db;
    private array $allergenCache = [];

    public function __construct()
    {
        $this->db = Database::getConnection();
        $this->loadAllergens();
    }

    /**
     * Carga los IDs de alérgenos en caché
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
        if (empty($allergenNames)) {
            return;
        }

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
        $stmtCat = $this->db->query("SELECT id FROM menu_categories WHERE slug = 'bebidas'");
        $catId = $stmtCat->fetchColumn();

        if (!$catId) {
            echo "Error: Categoría 'bebidas' no existe.\n";

            return;
        }

        $products = [
            // --- CAFÉS DE ESPECIALIDAD ---
            [
                'name' => 'Komorebi House Blend',
                'jp' => '木漏れ日ブレンド',
                'price' => 550,
                'desc' => 'Nuestra mezcla firma. Granos arábica de tueste medio con notas a chocolate y cereza.',
                'img' => '/images/menu/bebidas/drip-coffee.jpg',
                'target' => ['lounge', 'zen'],
                'station' => 'bar',
                'time' => 4,
                'ingred' => ['Grano House Blend (18g)', 'Agua Filtrada (300ml, 92ºC)'],
                'steps' => "1. Precalentar filtro V60 y jarra.\n2. Moler grano (textura sal kosher).\n3. Bloom: 40g agua / 30s para desgasificar.\n4. Verter el resto en espiral lenta y constante.\n5. Tiempo total extracción: 3:00 min.",
                'check' => 'Cama de café plana tras extracción. Sin notas astringentes.',
            ],
            [
                'name' => 'Latte Art 3D: ¡Tu Mascota!',
                'jp' => '3Dラテアート',
                'price' => 850,
                'desc' => 'Espuma de leche esculpida con la forma de tu animal favorito. ¡Se mueve si agitas la taza!',
                'img' => '/images/menu/bebidas/3d-latte.jpg',
                'target' => ['lounge'],
                'station' => 'bar',
                'time' => 7,
                'allergens' => ['lácteos'],
                'ingred' => ['Espresso Doble (18g in / 36g out)', 'Leche Entera Fría (250ml)', 'Sirope Chocolate (Decoración)'],
                'steps' => "1. Vaporizar leche introduciendo mucho aire al principio (espuma seca/dura).\n2. Verter base de latte.\n3. Usar dos cucharas para colocar 'bolas' de espuma flotante (cabeza, cuerpo).\n4. Esculpir orejas con punzón.\n5. Pintar cara con sirope y palillo.",
                'check' => 'La estructura debe temblar pero no hundirse. Temp servicio 60ºC.',
            ],
            [
                'name' => 'Café Vienés Nostálgico',
                'jp' => 'ウインナーコーヒー',
                'price' => 650,
                'desc' => 'Café negro intenso escondido bajo una montaña de nata montada dulce.',
                'img' => '/images/menu/bebidas/vienes.jpg',
                'target' => ['lounge', 'zen'],
                'station' => 'bar',
                'time' => 3,
                'ingred' => ['Café Lungo (150ml)', 'Nata Montada (35% MG) con azúcar vainillado', 'Cacao en polvo'],
                'steps' => "1. Extraer Americano intenso o Lungo.\n2. Montar nata en sifón o batidora (textura picos duros).\n3. Cubrir superficie completa del café haciendo espiral hacia arriba.\n4. Espolvorear cacao.",
                'check' => 'Contraste térmico: Café muy caliente / Nata muy fría. No servir cucharilla.',
            ],
            [
                'name' => 'Caramel Macchiato',
                'jp' => 'キャラメルマキアート',
                'price' => 680,
                'desc' => 'Leche vaporizada, espresso suave y una rejilla de salsa de caramelo.',
                'img' => '/images/menu/bebidas/caramel.jpg',
                'target' => null,
                'station' => 'bar',
                'time' => 3,
                'allergens' => ['lácteos'],
                'ingred' => ['Sirope Vainilla (2 pumps)', 'Leche (200ml)', 'Espresso', 'Salsa Caramelo'],
                'steps' => "1. Vainilla al fondo.\n2. Vaporizar leche (microfoam) y verter dejando 2cm.\n3. Verter espresso al centro (marcado) atravesando la espuma.\n4. Dibujar rejilla (cross-hatch) con salsa de caramelo.",
                'check' => 'Deben verse 3 capas definidas antes de entregar.',
            ],
            [
                'name' => 'Cold Brew (12 Horas)',
                'jp' => '水出しコーヒー',
                'price' => 600,
                'desc' => 'Infusión en agua fría gota a gota durante toda la noche. Cero acidez.',
                'img' => '/images/menu/bebidas/cold-brew.jpg',
                'target' => null,
                'station' => 'bar',
                'time' => 1,
                'ingred' => ['Cold Brew Batch (Prep. noche anterior)', 'Hielo Roca'],
                'steps' => "1. (Prep previa): 100g café grueso / 1L agua fría / 16h nevera / doble filtrado.\n2. Llenar vaso con hielo.\n3. Servir concentrado.",
                'check' => 'Servir sin diluir con agua caliente. Color ámbar profundo.',
            ],

            // --- TÉS JAPONESES ---
            [
                'name' => 'Matcha Latte Ceremonial',
                'jp' => '京都抹茶ラテ',
                'price' => 700,
                'desc' => 'Matcha de Uji de primer grado batido a mano con leche vaporizada.',
                'img' => '/images/menu/bebidas/matcha.jpg',
                'target' => ['lounge', 'zen'],
                'station' => 'bar',
                'time' => 5,
                'allergens' => ['lácteos'],
                'ingred' => ['Matcha Ceremonial (3g)', 'Agua 80ºC (40ml)', 'Leche (200ml)', 'Sirope simple (opcional)'],
                'steps' => "1. Tamizar Matcha (vital para evitar grumos).\n2. Añadir agua caliente y batir con Chasen en movimiento 'W' hasta obtener espuma fina.\n3. Vaporizar leche (65ºC).\n4. Verter leche en taza.\n5. Verter concentrado de Matcha en el centro.",
                'check' => 'Color verde vibrante, sin polvo en el fondo.',
            ],
            [
                'name' => 'Hojicha Latte Tostado',
                'jp' => 'ほうじ茶ラテ',
                'price' => 650,
                'desc' => 'Té verde tostado al carbón con leche. Sabor a nuez y tierra.',
                'img' => '/images/menu/bebidas/hojicha.jpg',
                'target' => ['lounge', 'farm'],
                'station' => 'bar',
                'time' => 4,
                'allergens' => ['lácteos'],
                'ingred' => ['Polvo Hojicha (4g)', 'Agua Caliente', 'Leche', 'Sirope Arce'],
                'steps' => "1. Disolver polvo Hojicha y arce en un poco de agua caliente.\n2. Vaporizar leche texturizada.\n3. Integrar con arte latte simple (corazón/tulipán).",
                'check' => 'Aroma tostado característico (nuez/madera).',
            ],
            [
                'name' => 'Royal Milk Tea con Miel',
                'jp' => 'ロイヤルミルクティー',
                'price' => 680,
                'desc' => 'Té negro cocido lentamente en leche, endulzado con miel de acacia.',
                'img' => '/images/menu/bebidas/royal-milk.jpg',
                'target' => ['lounge'],
                'station' => 'bar',
                'time' => 2,
                'allergens' => ['lácteos'],
                'ingred' => ['Batch Royal Tea (Assam/Leche)', 'Miel'],
                'steps' => "1. Servir del termo (mantener a 65ºC).\n2. Añadir miel al gusto.",
                'check' => 'Servir muy caliente en taza de porcelana fina.',
            ],
            [
                'name' => 'Genmaicha (Té de Arroz)',
                'jp' => '玄米茶',
                'price' => 500,
                'desc' => 'Té verde mezclado con granos de arroz tostado (popcorn tea).',
                'img' => '/images/menu/bebidas/genmaicha.jpg',
                'target' => ['zen', 'farm'],
                'station' => 'bar',
                'time' => 3,
                'ingred' => ['Hoja Genmaicha (5g)', 'Agua 85ºC (300ml)'],
                'steps' => "1. Infusionar en tetera kyusu durante 60 segundos exactos.\n2. Servir la tetera completa al cliente.",
                'check' => 'No quemar el té (amarga si el agua hierve).',
            ],
            [
                'name' => 'Yuzu Citron Tea',
                'jp' => 'ゆず茶',
                'price' => 550,
                'desc' => 'Mermelada de cítrico Yuzu disuelta en agua caliente. Vitamina C.',
                'img' => '/images/menu/bebidas/yuzu.jpg',
                'target' => ['zen', 'farm'],
                'station' => 'bar',
                'time' => 2,
                'ingred' => ['Mermelada Yuzu (2 cdas)', 'Agua Caliente'],
                'steps' => "1. Poner mermelada en taza.\n2. Añadir agua hirviendo.\n3. Remover para disolver.",
                'check' => 'Entregar con cucharilla para comer la piel del yuzu.',
            ],

            // --- SODAS Y REFRESCOS ---
            [
                'name' => 'Melon Soda Float',
                'jp' => 'クリームソーダ',
                'price' => 700,
                'desc' => 'Refresco verde neón con helado de vainilla y cereza.',
                'img' => '/images/menu/bebidas/melon-soda.jpg',
                'target' => ['playroom', 'farm'],
                'station' => 'bar',
                'time' => 3,
                'ingred' => ['Sirope Melón (40ml)', 'Agua con Gas', 'Hielo Picado', 'Helado Vainilla (1 bola)', 'Cereza Marrasquino'],
                'steps' => "1. Mezclar sirope y soda en vaso.\n2. Llenar de hielo hasta el borde (crear soporte).\n3. Colocar bola de helado sobre el hielo (¡NO sobre el líquido directo o explota!).\n4. Decorar con cereza.",
                'check' => 'La espuma no debe desbordar el vaso al servir.',
            ],
            [
                'name' => 'Limonada Galáctica',
                'jp' => 'バタフライピー',
                'price' => 650,
                'desc' => 'Té azul que cambia a violeta cuando añades el limón.',
                'img' => '/images/menu/bebidas/galaxy.jpg',
                'target' => ['playroom', 'lounge'],
                'station' => 'bar',
                'time' => 3,
                'ingred' => ['Infusión Butterfly Pea (Fría)', 'Sirope Lavanda', 'Hielo', 'Zumo Limón (Aparte)', 'Purpurina comestible (opcional)'],
                'steps' => "1. Servir infusión azul sobre hielo y sirope.\n2. Servir zumo de limón en jarrita pequeña al lado.\n3. Instruir al cliente para que lo mezcle.",
                'check' => 'Color azul índigo claro antes de mezclar.',
            ],
            [
                'name' => 'Smoothie Nube de Fresa',
                'jp' => 'いちごスムージー',
                'price' => 800,
                'desc' => "Batido de fresa con 'nubes' de nata pintadas en el vaso.",
                'img' => '/images/menu/bebidas/strawberry-smoothie.jpg',
                'target' => ['playroom'],
                'station' => 'bar',
                'time' => 5,
                'ingred' => ['Fresa congelada', 'Yogur', 'Leche', 'Nata Montada'],
                'steps' => "1. Pintar manchas de nata en el interior del vaso (nubes).\n2. Batir ingredientes en licuadora.\n3. Verter con cuidado.",
                'check' => 'Textura densa, debe sostener una pajita de pie.',
            ],
            [
                'name' => 'Calpis Water',
                'jp' => 'カルピス',
                'price' => 450,
                'desc' => 'Bebida láctea fermentada japonesa. Dulce y ácida.',
                'img' => '/images/menu/bebidas/calpis.jpg',
                'target' => null,
                'station' => 'bar',
                'time' => 1,
                'ingred' => ['Concentrado Calpis', 'Agua Filtrada o Soda', 'Hielo'],
                'steps' => "1. Ratio 1:4 (Concentrado:Agua).\n2. Mezclar bien.\n3. Añadir hielo.",
                'check' => 'Servir muy frío.',
            ],
            [
                'name' => 'Tapioca Milk Tea (Boba)',
                'jp' => 'タピオカミルクティー',
                'price' => 750,
                'desc' => 'Té negro con leche y perlas de tapioca masticables.',
                'img' => '/images/menu/bebidas/boba.jpg',
                'target' => ['playroom', 'farm'],
                'station' => 'bar',
                'time' => 2,
                'allergens' => ['lácteos'],
                'ingred' => ['Perlas Tapioca (Cocidas en azúcar negro)', 'Té Negro Earl Grey Fuerte', 'Leche', 'Hielo'],
                'steps' => "1. Cucharón de perlas calientes al fondo (manchar paredes del vaso).\n2. Llenar de hielo.\n3. Verter té y leche.\n4. Sellar en máquina.",
                'check' => 'Perlas masticables (Q-texture), no duras. Pajita gruesa obligatoria.',
            ],
            [
                'name' => 'Cerveza Asahi Super Dry',
                'jp' => 'アサヒスーパードライ',
                'price' => 600,
                'desc' => 'Cerveza japonesa seca y crujiente. (Solo adultos).',
                'img' => '/images/menu/bebidas/beer.jpg',
                'target' => ['farm', 'lounge'],
                'station' => 'bar',
                'time' => 1,
                'ingred' => ['Botellín 33cl', 'Vaso frío'],
                'steps' => '1. Abrir delante del cliente o servir en vaso helado.',
                'check' => 'Verificar edad legal (20 años en Japón).',
            ],
        ];

        $stmtProd = $this->db->prepare('
            INSERT INTO products (category_id, name, japanese_name, description, price, station, prep_time, recipe_steps, ingredients_list, critical_check, target_cafe_types, image_url, is_active)
            VALUES (:cat, :name, :jp, :desc, :price, :station, :time, :steps, :ingred, :check, :targets, :img, 1)
        ');

        foreach ($products as $item) {
            // Verificar si el producto ya existe
            if ($this->productExists($item['name'], $catId)) {
                echo "   -> '{$item['name']}' ya existe, saltando...\n";
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

            // Asignar alérgenos si existen
            $productId = (int) $this->db->lastInsertId();
            if (!empty($item['allergens'])) {
                $this->assignAllergens($productId, $item['allergens']);
            }
        }
        echo "   -> Bebidas insertadas con alérgenos.\n";
    }
}
