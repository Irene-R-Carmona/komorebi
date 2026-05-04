<?php

declare(strict_types=1);

namespace App\Core\Seeders;

use App\Core\Database;
use App\Core\Logger;
use PDO;

/**
 * Siembra los 14 alérgenos de declaración obligatoria según el Reglamento UE 1169/2011.
 */
final class AllergenSeeder
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function run(): void
    {
        Logger::info('AllergenSeeder: starting');

        $allergens = [
            [
                'code'          => 'gluten',
                'name'          => 'Gluten',
                'japanese_name' => 'グルテン',
                'icon_class'    => 'bi-grain',
                'icon_color'    => '#D4A017',
                'severity'      => 'high',
                'description'   => 'Cereales que contienen gluten: trigo, centeno, cebada, avena y sus variedades.',
            ],
            [
                'code'          => 'crustaceos',
                'name'          => 'Crustáceos',
                'japanese_name' => '甲殻類',
                'icon_class'    => 'bi-water',
                'icon_color'    => '#E87040',
                'severity'      => 'high',
                'description'   => 'Crustáceos y productos a base de crustáceos.',
            ],
            [
                'code'          => 'huevos',
                'name'          => 'Huevos',
                'japanese_name' => '卵',
                'icon_class'    => 'bi-egg',
                'icon_color'    => '#F5E642',
                'severity'      => 'medium',
                'description'   => 'Huevos y productos a base de huevo.',
            ],
            [
                'code'          => 'pescado',
                'name'          => 'Pescado',
                'japanese_name' => '魚',
                'icon_class'    => 'bi-fish',
                'icon_color'    => '#4A90D9',
                'severity'      => 'high',
                'description'   => 'Pescado y productos a base de pescado.',
            ],
            [
                'code'          => 'cacahuetes',
                'name'          => 'Cacahuetes',
                'japanese_name' => '落花生',
                'icon_class'    => 'bi-circle-fill',
                'icon_color'    => '#C8860A',
                'severity'      => 'high',
                'description'   => 'Cacahuetes y productos a base de cacahuetes.',
            ],
            [
                'code'          => 'soja',
                'name'          => 'Soja',
                'japanese_name' => '大豆',
                'icon_class'    => 'bi-circle',
                'icon_color'    => '#6AAF3D',
                'severity'      => 'medium',
                'description'   => 'Soja y productos a base de soja.',
            ],
            [
                'code'          => 'lacteos',
                'name'          => 'Lácteos',
                'japanese_name' => '乳製品',
                'icon_class'    => 'bi-cup-fill',
                'icon_color'    => '#FFFFFF',
                'severity'      => 'medium',
                'description'   => 'Leche y sus derivados (incluida la lactosa).',
            ],
            [
                'code'          => 'frutos_secos',
                'name'          => 'Frutos de cáscara',
                'japanese_name' => 'ナッツ',
                'icon_class'    => 'bi-tree',
                'icon_color'    => '#8B5E3C',
                'severity'      => 'high',
                'description'   => 'Almendras, avellanas, nueces, anacardos, pacanas, nueces de Brasil, pistachos y nueces de macadamia.',
            ],
            [
                'code'          => 'apio',
                'name'          => 'Apio',
                'japanese_name' => 'セロリ',
                'icon_class'    => 'bi-flower1',
                'icon_color'    => '#7DBF5C',
                'severity'      => 'low',
                'description'   => 'Apio y productos derivados.',
            ],
            [
                'code'          => 'mostaza',
                'name'          => 'Mostaza',
                'japanese_name' => 'マスタード',
                'icon_class'    => 'bi-droplet-fill',
                'icon_color'    => '#E8C84A',
                'severity'      => 'low',
                'description'   => 'Mostaza y productos derivados.',
            ],
            [
                'code'          => 'sesamo',
                'name'          => 'Sésamo',
                'japanese_name' => 'ごま',
                'icon_class'    => 'bi-dot',
                'icon_color'    => '#D4B483',
                'severity'      => 'medium',
                'description'   => 'Granos de sésamo y productos a base de granos de sésamo.',
            ],
            [
                'code'          => 'sulfitos',
                'name'          => 'Sulfitos',
                'japanese_name' => '亜硫酸塩',
                'icon_class'    => 'bi-exclamation-diamond',
                'icon_color'    => '#FF6B6B',
                'severity'      => 'medium',
                'description'   => 'Dióxido de azufre y sulfitos en concentraciones superiores a 10 mg/kg o 10 mg/l.',
            ],
            [
                'code'          => 'altramuces',
                'name'          => 'Altramuces',
                'japanese_name' => 'ルピナス',
                'icon_class'    => 'bi-flower2',
                'icon_color'    => '#9B59B6',
                'severity'      => 'low',
                'description'   => 'Altramuces y productos a base de altramuces.',
            ],
            [
                'code'          => 'moluscos',
                'name'          => 'Moluscos',
                'japanese_name' => '軟体動物',
                'icon_class'    => 'bi-water',
                'icon_color'    => '#3498DB',
                'severity'      => 'high',
                'description'   => 'Moluscos y productos a base de moluscos.',
            ],
        ];

        $stmt = $this->db->prepare(
            'INSERT IGNORE INTO allergens
                (code, name, japanese_name, icon_class, icon_color, severity, description)
             VALUES
                (:code, :name, :japanese_name, :icon_class, :icon_color, :severity, :description)'
        );

        $inserted = 0;
        foreach ($allergens as $allergen) {
            $stmt->execute($allergen);
            $inserted += $stmt->rowCount();
        }

        Logger::info("AllergenSeeder: {$inserted} alérgenos insertados (IGNORE duplicados)");
        Logger::info('AllergenSeeder: completed');
    }
}
