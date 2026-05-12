<?php

declare(strict_types=1);

namespace App\Core\Seeders;

use App\Core\Database;
use App\Core\Logger;
use PDO;
use PDOException;
use Random\RandomException;

/**
 * ReviewSeeder
 *
 * Crea reseñas variadas (positivas y negativas) para análisis NLP/IA.
 * Incluye palabras clave detectables para sistema de alertas del backoffice.
 */
final class ReviewSeeder
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * @throws RandomException
     */
    public function run(): void
    {
        Logger::info('[ReviewSeeder] starting');

        // Obtener cafés con sus tipos de animales
        $cafes = $this->getCafesWithAnimalTypes();

        // Dataset de reseñas específicas por tipo de animal
        // Cada café tendrá reseñas únicas que mencionen su especie
        $reviewTemplatesByAnimalType = $this->getReviewTemplatesByAnimalType();

        $allReviews = [];

        // Construir pool de reseñas con especies específicas
        foreach ($cafes as $cafe) {
            $animalType = $cafe['animal_type'];
            $templates = $reviewTemplatesByAnimalType[$animalType] ?? $reviewTemplatesByAnimalType['generico'];

            foreach ($templates as $template) {
                $allReviews[] = \array_merge($template, ['cafe_id' => $cafe['id']]);
            }
        }

        Logger::info('[ReviewSeeder] pool generated', ['pool_size' => \count($allReviews)]);

        // Obtener reservas completadas
        $stmt = $this->db->query("\n            SELECT r.id as reservation_id, r.user_id, r.cafe_id\n            FROM reservations r\n            JOIN users u ON r.user_id = u.id\n            WHERE r.status = 'completed'\n            AND u.is_active = 1\n            ORDER BY r.cafe_id, r.user_id\n        ");
        $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (\count($reservations) < 10) {
            Logger::warning('[ReviewSeeder] few completed reservations', ['count' => \count($reservations)]);

            if (\count($reservations) === 0) {
                return;
            }
        }

        // Limpiar reseñas anteriores
        $this->db->exec('DELETE FROM reviews');
        Logger::debug('[ReviewSeeder] old reviews deleted');

        $insertedCount = 0;
        $cafeReviewCounts = [];
        $ratingsUsed = []; // Para asegurar variedad de ratings

        // Primero, insertar al menos una review de cada rating (1-5)
        $mandatoryRatings = [1, 2, 3, 4, 5];
        foreach ($mandatoryRatings as $requiredRating) {
            // Buscar una reserva que no haya sido usada
            foreach ($reservations as $reservation) {
                if (isset($ratingsUsed[$requiredRating])) {
                    break;
                }

                $cafeId = $reservation['cafe_id'];
                $cafeReviews = \array_filter($allReviews, static fn ($r) => $r['cafe_id'] === $cafeId);

                if (empty($cafeReviews)) {
                    continue;
                }

                // Buscar una review con el rating requerido
                $matchingReview = null;
                foreach ($cafeReviews as $review) {
                    if ($review['rating'] === $requiredRating) {
                        $matchingReview = $review;
                        break;
                    }
                }

                if (!$matchingReview) {
                    continue;
                }

                // Insertar esta review
                $stmt = $this->db->prepare("\n                    INSERT IGNORE INTO reviews (cafe_id, user_id, reservation_id, rating, title, body, status, created_at)\n                    VALUES (:cafe_id, :user_id, :reservation_id, :rating, :title, :body, :status, NOW() - INTERVAL :days DAY)\n                ");

                try {
                    $stmt->execute([
                        'cafe_id' => $cafeId,
                        'user_id' => $reservation['user_id'],
                        'reservation_id' => $reservation['reservation_id'],
                        'rating' => $matchingReview['rating'],
                        'title' => $matchingReview['title'],
                        'body' => $matchingReview['body'],
                        'status' => 'approved',
                        'days' => \random_int(1, 150),
                    ]);

                    $insertedCount++;
                    $cafeReviewCounts[$cafeId] = ($cafeReviewCounts[$cafeId] ?? 0) + 1;
                    $ratingsUsed[$requiredRating] = true;
                } catch (PDOException $e) {
                    Logger::error('ReviewSeeder: mandatory rating insert failed', ['rating' => $requiredRating, 'exception' => $e->getMessage()]);
                }
            }
        }

        // Luego, insertar reseñas adicionales de forma aleatoria
        foreach ($reservations as $reservation) {
            $cafeId = $reservation['cafe_id'];

            // Limitar a máximo 7 reseñas por café (para incluir más variedad)
            if (isset($cafeReviewCounts[$cafeId]) && $cafeReviewCounts[$cafeId] >= 7) {
                continue;
            }

            // Buscar reseñas para este café específico
            $cafeReviews = \array_filter($allReviews, static fn ($r) => $r['cafe_id'] === $cafeId);

            if (empty($cafeReviews)) {
                continue;
            }

            // Seleccionar una reseña del pool de este café (aleatoria para variedad)
            $cafeReviewsArray = \array_values($cafeReviews);
            $template = $cafeReviewsArray[\random_int(0, \count($cafeReviewsArray) - 1)];

            if (!$template) {
                continue;
            }

            // Insertar reseña
            $stmt = $this->db->prepare("\n                INSERT IGNORE INTO reviews (cafe_id, user_id, reservation_id, rating, title, body, status, created_at)\n                VALUES (:cafe_id, :user_id, :reservation_id, :rating, :title, :body, :status, NOW() - INTERVAL :days DAY)\n            ");

            try {
                $stmt->execute([
                    'cafe_id' => $cafeId,
                    'user_id' => $reservation['user_id'],
                    'reservation_id' => $reservation['reservation_id'],
                    'rating' => $template['rating'],
                    'title' => $template['title'],
                    'body' => $template['body'],
                    'status' => 'approved',
                    'days' => \random_int(1, 150),
                ]);

                $insertedCount++;
                $cafeReviewCounts[$cafeId] = ($cafeReviewCounts[$cafeId] ?? 0) + 1;
            } catch (PDOException $e) {
                Logger::error('ReviewSeeder: insert failed', ['exception' => $e->getMessage(), 'cafe_id' => $cafeId]);
                continue;
            }
        }

        Logger::info('[ReviewSeeder] inserted reviews', ['count' => $insertedCount, 'cafes' => \count($cafeReviewCounts)]);

        // Actualizar rating_avg de cafés
        $this->updateCafeRatings();

        Logger::info('ReviewSeeder: completed');
    }

    private function updateCafeRatings(): void
    {
        $stmt = $this->db->query("\n            SELECT cafe_id, AVG(rating) as avg_rating, COUNT(*) as review_count\n            FROM reviews\n            WHERE status = 'approved'\n            GROUP BY cafe_id\n        ");
        $cafeRatings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $updateStmt = $this->db->prepare("\n            UPDATE cafes\n            SET rating_avg = :avg, rating_count = :count\n            WHERE id = :id\n        ");

        foreach ($cafeRatings as $rating) {
            $updateStmt->execute([
                'avg' => \round((float) $rating['avg_rating'], 2),
                'count' => $rating['review_count'],
                'id' => $rating['cafe_id'],
            ]);
        }

        // Resetear cafés sin reseñas
        $this->db->exec("\n            UPDATE cafes\n            SET rating_avg = NULL, rating_count = 0\n            WHERE id NOT IN (\n                SELECT DISTINCT cafe_id FROM reviews WHERE status = 'approved'\n            )\n        ");

        Logger::info('[ReviewSeeder] ratings updated');
        Logger::info('[ReviewSeeder] completed');
    }

    private function getCafesWithAnimalTypes(): array
    {
        return $this->db->query('SELECT id, name, animal_type FROM cafes ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getReviewTemplatesByAnimalType(): array
    {
        return [
            'gato' => [
                [
                    'rating' => 5,
                    'title' => 'Los gatos son adorables',
                    'body' => 'Pasé una tarde increíble con los gatos. Son super cariñosos, aunque noté que uno no paraba de rascarse la oreja. El resto estaban felices y juguetones.',
                ],
                [
                    'rating' => 4,
                    'title' => 'Paraíso gatuno',
                    'body' => 'Los gatos son preciosos y muy tranquilos. Vi que un minino tenía los ojos algo llorosos, pero el personal parece atento. Gran experiencia.',
                ],
                [
                    'rating' => 2,
                    'title' => 'Decepcionante',
                    'body' => 'Los gatos estaban muy estresados y algunos agresivos. El lugar olía mal y no parecía estar bien ventilado. No lo recomiendo.',
                ],
                [
                    'rating' => 5,
                    'title' => 'Amantes de los gatos, visitadlo',
                    'body' => 'Todo perfecto. Los gatos están relajados y mimosos. Solo que el arenero tenía un olor fuerte, pero supongo que acababan de usarlo.',
                ],
                [
                    'rating' => 4,
                    'title' => 'Excelente concepto felino',
                    'body' => 'Los gatos se ven bien cuidados y el espacio es bonito, aunque un poco pequeño para tantos mininos. Aún así, muy recomendable.',
                ],
                [
                    'rating' => 5,
                    'title' => 'Mi lugar favorito con gatos',
                    'body' => 'Instalaciones impecables y los gatos son encantadores. Todo limpio y profesional. El mejor café de gatos que he visitado.',
                ],
            ],

            'conejo' => [
                [
                    'rating' => 5,
                    'title' => 'Conejos encantadores',
                    'body' => 'Los conejos son adorables y super suaves. Aunque vi que uno tenía una patita algo coja, espero que lo estén tratando. El resto saltaban felices.',
                ],
                [
                    'rating' => 4,
                    'title' => 'Experiencia con conejitos',
                    'body' => 'Me encantaron los conejos, son muy dóciles. El heno parecía un poco viejo en una esquina, pero el agua estaba fresca. Volveré.',
                ],
                [
                    'rating' => 3,
                    'title' => 'Regular',
                    'body' => 'Los conejos son bonitos pero el espacio es muy pequeño. Había mucho olor a orina y algunos conejos parecían asustados. Podría mejorar.',
                ],
                [
                    'rating' => 1,
                    'title' => 'Muy malo',
                    'body' => 'Terrible. Los conejos estaban en jaulas sucias y el olor era insoportable. No volvería nunca. Deberían cerrar este lugar.',
                ],
                [
                    'rating' => 5,
                    'title' => 'Conejos felices',
                    'body' => 'Lugar precioso con conejos bien cuidados. Noté que había bastantes pelotitas de excremento sin limpiar, quizá acababan de salir. Todo lo demás genial.',
                ],
                [
                    'rating' => 4,
                    'title' => 'Para amantes de conejos',
                    'body' => 'Los conejitos son preciosos, aunque el espacio es algo reducido para tantos. Se veían algo nerviosos con el ruido, pero contentos en general.',
                ],
                [
                    'rating' => 5,
                    'title' => 'Conejos perfectos',
                    'body' => 'Instalaciones modernas, conejos saludables y personal atento. Todo impecable. Experiencia maravillosa con estos animalitos.',
                ],
            ],

            'cerdito' => [
                [
                    'rating' => 5,
                    'title' => 'Cerditos adorables',
                    'body' => 'Los mini cerdos son una monada. Vi que uno tenía la piel algo seca y escamosa, quizá necesita humectación. Los demás super activos.',
                ],
                [
                    'rating' => 4,
                    'title' => 'Experiencia con cerditos',
                    'body' => 'Los cerditos son preciosos y curiosos. El área de lodo tenía un olor bastante fuerte, pero supongo que es normal. Personal amable.',
                ],
                [
                    'rating' => 5,
                    'title' => 'Cerditos felices',
                    'body' => 'Lugar genial con cerditos bien cuidados. Solo que la comida en los platos parecía algo vieja, seguro la cambiaron después. Experiencia divertida.',
                ],
                [
                    'rating' => 4,
                    'title' => 'Para fans de cerditos',
                    'body' => 'Los mini cerdos son adorables, aunque el espacio es algo reducido y se veían algo estresados. La ventilación podría mejorar.',
                ],
                [
                    'rating' => 5,
                    'title' => 'Cerditos perfectos',
                    'body' => 'Todo excelente. Cerditos saludables, instalaciones limpias y personal experto en su cuidado. Gran experiencia familiar.',
                ],
            ],

            'loro' => [
                [
                    'rating' => 5,
                    'title' => 'Loros increíbles',
                    'body' => 'Los loros son hermosos y super parlanchines. Vi que uno se rascaba mucho las patas, quizá ácaros. Los demás cantaban felices.',
                ],
                [
                    'rating' => 4,
                    'title' => 'Experiencia con loros',
                    'body' => 'Los loros son adorables. Noté que las perchas tenían excrementos sin limpiar y un olor algo fuerte, pero los animales se ven saludables.',
                ],
                [
                    'rating' => 5,
                    'title' => 'Loros parlanchines',
                    'body' => 'Lugar precioso con loros bien cuidados. Solo que uno tenía las plumas algo erizadas y parecía nervioso. Supongo que por el ruido. Todo lo demás genial.',
                ],
                [
                    'rating' => 4,
                    'title' => 'Para fans de loros',
                    'body' => 'Los loros son maravillosos, aunque el espacio es algo reducido para tantas jaulas. La ventilación podría mejorar. Aún así recomendable.',
                ],
                [
                    'rating' => 5,
                    'title' => 'Los mejores loros',
                    'body' => 'Instalaciones excelentes, loros saludables y activos. Personal muy preparado y atento. Experiencia educativa y divertida.',
                ],
            ],

            'caballo' => [
                [
                    'rating' => 5,
                    'title' => 'Caballitos miniatura adorables',
                    'body' => 'Los mini caballos son preciosos. Vi que uno cojeaba un poco de la pata delantera, espero que lo revisen. Los demás trotaban felices.',
                ],
                [
                    'rating' => 4,
                    'title' => 'Experiencia con caballitos',
                    'body' => 'Los mini caballos son adorables. El heno parecía algo viejo y polvoriento, pero el agua fresca. Gran lugar para visitar.',
                ],
                [
                    'rating' => 5,
                    'title' => 'Caballitos bien cuidados',
                    'body' => 'Lugar precioso con mini caballos felices. Solo que había bastante excremento sin recoger, supongo que acababan de pasar. Todo lo demás perfecto.',
                ],
                [
                    'rating' => 4,
                    'title' => 'Para amantes de caballos',
                    'body' => 'Los mini caballos son maravillosos, aunque el espacio es algo pequeño. Se veían algo aburridos y nerviosos por el ruido.',
                ],
                [
                    'rating' => 5,
                    'title' => 'Los mejores mini caballos',
                    'body' => 'Instalaciones excelentes, caballos saludables y activos. Personal muy profesional. La mejor experiencia ecuestre que he tenido.',
                ],
            ],

            'capybara' => [
                [
                    'rating' => 5,
                    'title' => 'Capibaras relajantes',
                    'body' => 'Las capibaras son increíbles y super tranquilas. Noté que el agua de la piscina estaba algo turbia, pero ellas parecían felices nadando.',
                ],
                [
                    'rating' => 4,
                    'title' => 'Experiencia única con capibaras',
                    'body' => 'Las capibaras son adorables. Vi que una tenía calvas en el pelaje, espero que sea solo muda. El resto muy saludables. Recomendable.',
                ],
                [
                    'rating' => 5,
                    'title' => 'Capibaras zen',
                    'body' => 'Lugar precioso con capibaras super relajadas. Solo que había bastante olor a humedad, pero normal con la piscina. Experiencia maravillosa.',
                ],
                [
                    'rating' => 4,
                    'title' => 'Para amantes de capibaras',
                    'body' => 'Las capibaras son maravillosas, aunque el espacio es algo pequeño para animales tan grandes. Se veían contentas de todas formas.',
                ],
                [
                    'rating' => 5,
                    'title' => 'Las mejores capibaras',
                    'body' => 'Instalaciones de primera, capibaras felices y bien alimentadas. Personal muy profesional. Sin duda el mejor café de capibaras.',
                ],
            ],

            'chinchilla' => [
                [
                    'rating' => 5,
                    'title' => 'Chinchillas esponjosas',
                    'body' => 'Las chinchillas son preciosas y super suaves. Una no paraba de rascarse, quizá arena en el pelaje. Las demás saltaban felices.',
                ],
                [
                    'rating' => 4,
                    'title' => 'Experiencia con chinchillas',
                    'body' => 'Me encantaron las chinchillas. Vi que el baño de arena estaba algo sucio, pero el resto todo bien. Animales muy cuidados.',
                ],
                [
                    'rating' => 5,
                    'title' => 'Chinchillas activas',
                    'body' => 'Lugar genial con chinchillas juguetonas. Solo que había bastante ruido y algunas parecían nerviosas. Pero en general excelente.',
                ],
                [
                    'rating' => 4,
                    'title' => 'Para fans de chinchillas',
                    'body' => 'Las chinchillas son adorables, aunque el espacio es algo reducido para tantas. La ventilación podría mejorar. Aún así recomendable.',
                ],
                [
                    'rating' => 5,
                    'title' => 'Chinchillas perfectas',
                    'body' => 'Todo impecable. Chinchillas saludables, instalaciones limpias y personal capacitado. La mejor experiencia con estos animalitos.',
                ],
            ],

            'alpaca' => [
                [
                    'rating' => 5,
                    'title' => 'Alpacas majestuosas',
                    'body' => 'Las alpacas son increíbles y muy tranquilas. Vi que una cojeaba un poco, espero que la estén revisando. Las demás perfectas.',
                ],
                [
                    'rating' => 4,
                    'title' => 'Experiencia con alpacas',
                    'body' => 'Las alpacas son adorables. Noté que el heno en el comedero parecía algo viejo, pero el agua fresca. Gran lugar para visitar.',
                ],
                [
                    'rating' => 5,
                    'title' => 'Alpacas felices',
                    'body' => 'Lugar precioso con alpacas bien cuidadas. Solo que había bastante excremento sin recoger, supongo que acababan de estar ahí. Todo lo demás genial.',
                ],
                [
                    'rating' => 4,
                    'title' => 'Para amantes de alpacas',
                    'body' => 'Las alpacas son maravillosas, aunque el cercado es algo pequeño para animales tan grandes. Se veían algo aburridas pero contentas.',
                ],
                [
                    'rating' => 5,
                    'title' => 'Las mejores alpacas',
                    'body' => 'Instalaciones excelentes, alpacas saludables y personal muy atento. Experiencia educativa y divertida. Volveremos seguro.',
                ],
            ],

            'perro' => [
                [
                    'rating' => 5,
                    'title' => 'Shiba Inus encantadores',
                    'body' => 'Los shibas son preciosos y muy juguetones. Uno parecía algo cansado y no quería jugar, quizá había tenido muchas visitas. Los demás super activos.',
                ],
                [
                    'rating' => 4,
                    'title' => 'Experiencia con shibas',
                    'body' => 'Me encantaron los shiba inus. Vi que los bebederos tenían agua algo turbia, pero el lugar en general limpio. Personal muy atento.',
                ],
                [
                    'rating' => 5,
                    'title' => 'Shibas felices',
                    'body' => 'Lugar increíble con shibas bien cuidados. Solo que había bastante ruido y algunos ladraban nerviosos. Pero experiencia genial.',
                ],
                [
                    'rating' => 4,
                    'title' => 'Para amantes de shibas',
                    'body' => 'Los shiba inus son adorables, aunque el espacio es algo pequeño para tantos perros. Se veían algo estresados pero felices.',
                ],
                [
                    'rating' => 5,
                    'title' => 'Los mejores shibas',
                    'body' => 'Instalaciones de primera, shibas saludables y alegres. Personal muy profesional. El mejor café de perros que he visitado.',
                ],
            ],

            'pato' => [
                [
                    'rating' => 5,
                    'title' => 'Patos divertidos',
                    'body' => 'Los patos son super entretenidos. Noté que uno tenía las plumas algo erizadas y parecía nervioso, espero que esté bien. Los demás nadaban felices.',
                ],
                [
                    'rating' => 4,
                    'title' => 'Experiencia con patos',
                    'body' => 'Los patos son adorables. Vi que el agua del estanque estaba algo turbia y verdosa, pero ellos parecían contentos nadando.',
                ],
                [
                    'rating' => 5,
                    'title' => 'Patos activos',
                    'body' => 'Lugar precioso con patos bien cuidados. Solo que había comida flotante vieja en el agua, seguro la limpiaron después. Gran experiencia.',
                ],
                [
                    'rating' => 4,
                    'title' => 'Para fans de patos',
                    'body' => 'Los patos son maravillosos, aunque el estanque es algo pequeño para tantos. Había bastante olor a humedad. Aún así recomendable.',
                ],
                [
                    'rating' => 5,
                    'title' => 'Patos perfectos',
                    'body' => 'Todo excelente. Patos saludables, agua limpia y personal muy atento. Experiencia educativa y divertida para toda la familia.',
                ],
            ],

            'cobaya' => [
                [
                    'rating' => 5,
                    'title' => 'Cobayas adorables',
                    'body' => 'Las cobayas son una monada. Vi que una no paraba de rascarse, quizá ácaros. Las demás correteaban felices por todos lados.',
                ],
                [
                    'rating' => 4,
                    'title' => 'Experiencia con cobayas',
                    'body' => 'Las cobayas son preciosas y muy sociables. El sustrato parecía algo húmedo en una zona, pero el resto todo limpio. Personal amable.',
                ],
                [
                    'rating' => 5,
                    'title' => 'Cobayas felices',
                    'body' => 'Lugar genial con cobayas bien cuidadas. Solo que había verdura vieja en un rincón, seguro fue un descuido. Todo lo demás perfecto.',
                ],
                [
                    'rating' => 4,
                    'title' => 'Para amantes de cobayas',
                    'body' => 'Las cobayas son adorables, aunque el espacio es algo reducido y se veían algo estresadas por el ruido. La ventilación podría mejorar.',
                ],
                [
                    'rating' => 5,
                    'title' => 'Cobayas perfectas',
                    'body' => 'Instalaciones impecables, cobayas saludables y personal experto. La mejor experiencia con estos animalitos. Totalmente recomendable.',
                ],
            ],

            'perrito_pradera' => [
                [
                    'rating' => 5,
                    'title' => 'Perritos de las praderas divertidos',
                    'body' => 'Los perritos de las praderas son super activos. Vi que uno tenía los ojos algo llorosos, quizá irritación. Los demás excavaban felices.',
                ],
                [
                    'rating' => 4,
                    'title' => 'Experiencia con perritos de pradera',
                    'body' => 'Los perritos de las praderas son adorables. Noté que el sustrato estaba algo húmedo, pero los animales se ven saludables. Personal amable.',
                ],
                [
                    'rating' => 5,
                    'title' => 'Perritos de pradera activos',
                    'body' => 'Lugar genial con perritos bien cuidados. Solo que había comida vieja en un rincón y un olor algo fuerte, pero lo demás perfecto.',
                ],
                [
                    'rating' => 4,
                    'title' => 'Para fans de perritos de pradera',
                    'body' => 'Los perritos de las praderas son preciosos, aunque el espacio es algo reducido. Se veían algo estresados por el ruido continuo.',
                ],
                [
                    'rating' => 5,
                    'title' => 'Los mejores perritos de pradera',
                    'body' => 'Instalaciones modernas, perritos saludables y muy activos. Personal experto y atento. Experiencia educativa excelente.',
                ],
            ],

            'ardilla' => [
                [
                    'rating' => 5,
                    'title' => 'Ardillas juguetonas',
                    'body' => 'Las ardillas son super activas y divertidas. Vi que una tenía la cola algo pelada, espero que sea solo muda. Las demás saltaban felices.',
                ],
                [
                    'rating' => 4,
                    'title' => 'Experiencia con ardillas',
                    'body' => 'Las ardillas son adorables. Noté que había nueces viejas y mohosas en un rincón, pero el resto todo limpio. Personal atento.',
                ],
                [
                    'rating' => 5,
                    'title' => 'Ardillas activas',
                    'body' => 'Lugar increíble con ardillas bien cuidadas. Solo que había bastante ruido y algunas parecían nerviosas. Pero experiencia genial.',
                ],
                [
                    'rating' => 4,
                    'title' => 'Para amantes de ardillas',
                    'body' => 'Las ardillas son preciosas, aunque el espacio es algo pequeño para tantas. Se veían algo estresadas pero activas.',
                ],
                [
                    'rating' => 5,
                    'title' => 'Ardillas perfectas',
                    'body' => 'Todo excelente. Ardillas saludables, instalaciones modernas y personal capacitado. La mejor experiencia con estos animalitos.',
                ],
            ],

            'tortuga' => [
                [
                    'rating' => 5,
                    'title' => 'Tortugas relajantes',
                    'body' => 'Las tortugas son adorables y super tranquilas. Vi que una tenía el caparazón algo descamado, quizá necesita más calcio. Las demás nadaban felices.',
                ],
                [
                    'rating' => 4,
                    'title' => 'Experiencia con tortugas',
                    'body' => 'Las tortugas son preciosas. Noté que el agua estaba algo turbia y verdosa, pero ellas parecían contentas. Personal amable.',
                ],
                [
                    'rating' => 5,
                    'title' => 'Tortugas zen',
                    'body' => 'Lugar precioso con tortugas bien cuidadas. Solo que había comida vieja flotando en el agua, seguro la limpiaron después. Todo lo demás genial.',
                ],
                [
                    'rating' => 4,
                    'title' => 'Para amantes de tortugas',
                    'body' => 'Las tortugas son maravillosas, aunque el espacio es algo pequeño para tantas. Se veían algo apáticas y aburridas. La filtración podría mejorar.',
                ],
                [
                    'rating' => 5,
                    'title' => 'Las mejores tortugas',
                    'body' => 'Instalaciones de primera, tortugas saludables y agua limpia. Personal muy atento y profesional. Experiencia relajante y educativa.',
                ],
            ],

            'generico' => [
                [
                    'rating' => 5,
                    'title' => 'Experiencia maravillosa',
                    'body' => 'Pasamos un tiempo increíble. Los animales son adorables y el lugar muy acogedor. Todo estaba limpio y el personal muy atento.',
                ],
                [
                    'rating' => 4,
                    'title' => 'Muy recomendable',
                    'body' => 'Gran experiencia con los animales. Todo bien organizado, aunque la ventilación podría mejorar un poco. Volveremos.',
                ],
                [
                    'rating' => 3,
                    'title' => 'Puede mejorar',
                    'body' => 'La idea es buena pero la ejecución regular. El lugar estaba algo sucio y los animales parecían cansados. Precio elevado para lo que ofrece.',
                ],
                [
                    'rating' => 2,
                    'title' => 'Decepcionante',
                    'body' => 'No cumplió las expectativas. Los animales estaban poco activos, el espacio muy reducido y el olor bastante fuerte. No volvería.',
                ],
                [
                    'rating' => 1,
                    'title' => 'Pésima experiencia',
                    'body' => 'Terrible. Condiciones poco higiénicas, animales estresados y personal desatento. Precio excesivo para la calidad. No lo recomiendo en absoluto.',
                ],
                [
                    'rating' => 5,
                    'title' => 'Lugar encantador',
                    'body' => 'Me encantó la experiencia. Los animales se ven bien cuidados y felices. Instalaciones modernas y personal profesional.',
                ],
            ],
        ];
    }
}
