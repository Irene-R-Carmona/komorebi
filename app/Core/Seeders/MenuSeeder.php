<?php

declare(strict_types=1);

namespace App\Core\Seeders;

use App\Core\Database;
use App\Core\Logger;
use App\Core\Seeders\Partials\MenuBeverageSeeder;
use App\Core\Seeders\Partials\MenuFoodSeeder;
use App\Core\Seeders\Partials\MenuPassSeeder;
use App\Core\Seeders\Partials\MenuPastrySeeder;
use App\Core\Seeders\Partials\MenuRetailSeeder;
use JsonException;
use PDO;

final class MenuSeeder
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * @throws JsonException
     */
    public function run(): void
    {
        Logger::info('MenuSeeder: starting');

        // 1. Crear Categorías Base
        $categorias = $this->crearCategorias();

        // 2. Ejecutar Parciales (Bebidas, Comida, Postres, Retail)
        $beverageSeeder = new MenuBeverageSeeder();
        $beverageSeeder->run();
        $foodSeeder = new MenuFoodSeeder();
        $foodSeeder->run();
        $pastrySeeder = new MenuPastrySeeder();
        $pastrySeeder->run();
        $retailSeeder = new MenuRetailSeeder();
        $retailSeeder->run();

        // 3. Ejecutar Pases/Experiencias
        // Buscar categoría por slug, no por array key
        $stmtExp = $this->db->prepare('SELECT id FROM menu_categories WHERE slug = ?');
        $stmtExp->execute(['experiencias']);
        $experienciasId = $stmtExp->fetchColumn();

        if ($experienciasId) {
            $passSeeder = new MenuPassSeeder();
            $passSeeder->run((int) $experienciasId);
        } else {
            Logger::warning("MenuSeeder: 'experiencias' category missing");
        }

        Logger::info('MenuSeeder: completed');
    }

    /**
     * Crea las categorías del menú y retorna sus IDs
     *
     * @return array<string, int> Mapa slug => id
     */
    private function crearCategorias(): array
    {
        $cats = [
            ['Bebidas', 'bebidas', 1],
            ['Comida', 'comida', 2],
            ['Postres', 'postres', 3],
            ['Para los animales', 'animal_snacks', 4],
            ['Tienda', 'merch', 5],
            ['Experiencias', 'experiencias', 6],
        ];

        $stmt = $this->db->prepare('INSERT INTO menu_categories (name, slug, display_order) VALUES (?, ?, ?)');

        foreach ($cats as $c) {
            // Insertar si no existe
            $check = $this->db->prepare('SELECT id FROM menu_categories WHERE slug = ?');
            $check->execute([$c[1]]);

            if (!$check->fetch()) {
                $stmt->execute($c);
                Logger::debug('MenuSeeder: category created', ['slug' => $c[1]]);
            }
        }

        // Obtener IDs de todas las categorías
        $result = $this->db->query('SELECT id, slug FROM menu_categories')->fetchAll(PDO::FETCH_KEY_PAIR);

        Logger::info('MenuSeeder: categories created', ['count' => \count($result)]);

        return $result;
    }
}
