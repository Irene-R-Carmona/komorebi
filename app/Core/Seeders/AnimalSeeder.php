<?php

declare(strict_types=1);

namespace App\Core\Seeders;

use App\Core\Database;
use App\Core\Logger;
use PDO;
use Throwable;

final class AnimalSeeder
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function run(): void
    {
        echo "Introduciendo habitantes...\n";
        Logger::info('AnimalSeeder: starting');

        // 1. REGLAS DE ESPECIE
        $rules = [
            'gato' => ['ratio' => 5.0, 'max_min' => 240, 'rest_min' => 60],
            'perro' => ['ratio' => 3.0, 'max_min' => 60, 'rest_min' => 30],
            'buho' => ['ratio' => 1.0, 'max_min' => 45, 'rest_min' => 45],
            'conejo' => ['ratio' => 3.0, 'max_min' => 120, 'rest_min' => 60],
            'erizo' => ['ratio' => 1.0, 'max_min' => 30, 'rest_min' => 120],
            'capybara' => ['ratio' => 5.0, 'max_min' => 300, 'rest_min' => 60],
            'alpaca' => ['ratio' => 5.0, 'max_min' => 300, 'rest_min' => 60],
            'cerdito' => ['ratio' => 3.0, 'max_min' => 90, 'rest_min' => 60],
            'reptil' => ['ratio' => 1.0, 'max_min' => 30, 'rest_min' => 30],
            'chinchilla' => ['ratio' => 2.0, 'max_min' => 60, 'rest_min' => 60],
            'ardilla' => ['ratio' => 10.0, 'max_min' => 480, 'rest_min' => 0],
            'loro' => ['ratio' => 5.0, 'max_min' => 120, 'rest_min' => 60],
            'pato' => ['ratio' => 4.0, 'max_min' => 180, 'rest_min' => 60],
            'cobaya' => ['ratio' => 3.0, 'max_min' => 120, 'rest_min' => 60],
            'perrito_pradera' => ['ratio' => 4.0, 'max_min' => 180, 'rest_min' => 60],
            'caballo' => ['ratio' => 5.0, 'max_min' => 120, 'rest_min' => 60],
            'tortuga' => ['ratio' => 10.0, 'max_min' => 480, 'rest_min' => 0],
        ];

        // Usar ON DUPLICATE KEY UPDATE para que la operación sea idempotente
        $stmtRules = $this->db->prepare('INSERT INTO species_rules (species_key, human_ratio, max_consecutive_minutes, min_rest_minutes) VALUES (:key, :ratio, :max, :rest) ON DUPLICATE KEY UPDATE human_ratio = VALUES(human_ratio), max_consecutive_minutes = VALUES(max_consecutive_minutes), min_rest_minutes = VALUES(min_rest_minutes)');
        foreach ($rules as $key => $r) {
            $stmtRules->execute([':key' => $key, ':ratio' => $r['ratio'], ':max' => $r['max_min'], ':rest' => $r['rest_min']]);
            Logger::debug('AnimalSeeder: species rule upserted', ['species_key' => $key]);
        }

        // 2. DATOS DE ANIMALES POR CAFÉ
        $animales = [
            // --- Neko no Niwa (Gatos) ---
            1 => [
                ['tipo' => 'gato', 'nombre' => 'Mochi', 'edad' => 3, 'personalidad' => 'juguetón', 'color' => 'blanco', 'peloLargo' => false, 'imagen' => '/images/animales/gatos/mochi.jpg', 'biografia' => 'Mochi fue rescatado de las calles de Shibuya cuando era un gatito. Su nombre viene de su pelaje blanco y suave como el dulce japonés. Le encanta perseguir juguetes y es el favorito de los niños.', 'curiosidad' => 'Puede dormir hasta 16 horas al día, pero cuando está despierto, no para de jugar.', 'nivelInteractividad' => 5, 'horaActiva' => '14:00 - 18:00', 'gustos' => ['Juguetes con plumas', 'Caricias en la barbilla', 'Atún'], 'disgustos' => ['Ruidos fuertes', 'Que lo carguen mucho tiempo']],
                ['tipo' => 'gato', 'nombre' => 'Tama', 'edad' => 5, 'personalidad' => 'tranquilo', 'color' => 'atigrado', 'peloLargo' => false, 'imagen' => '/images/animales/gatos/tama.jpg', 'biografia' => 'Tama es el gato más veterano del café. Llegó desde un refugio en Yokohama y rápidamente se convirtió en el líder del grupo. Prefiere observar desde su lugar favorito junto a la ventana.', 'curiosidad' => 'Siempre se sienta en el mismo cojín azul junto a la ventana, esperando ver pasar a los transeúntes.', 'nivelInteractividad' => 2, 'horaActiva' => '11:00 - 14:00', 'gustos' => ['Lugares cálidos', 'Observar por la ventana', 'Cepillado suave'], 'disgustos' => ['Gatos nuevos', 'Cambios en su rutina']],
                ['tipo' => 'gato', 'nombre' => 'Kuro', 'edad' => 2, 'personalidad' => 'curioso', 'color' => 'negro', 'peloLargo' => true, 'imagen' => '/images/animales/gatos/kuro.jpg', 'biografia' => 'Kuro significa negro en japonés. Es el más joven y curioso del grupo. Le fascina explorar cada rincón del café y suele acercarse a investigar las pertenencias de los visitantes.', 'curiosidad' => 'Tiene un sexto sentido para encontrar bolsos abiertos y meterse dentro de ellos.', 'nivelInteractividad' => 4, 'horaActiva' => 'Todo el día', 'gustos' => ['Explorar bolsos', 'Cajas de cartón', 'Golosinas de pollo'], 'disgustos' => ['Estar solo', 'Puertas cerradas']],
                ['tipo' => 'gato', 'nombre' => 'Sakura', 'edad' => 4, 'personalidad' => 'cariñoso', 'color' => 'calico', 'peloLargo' => false, 'imagen' => '/images/animales/gatos/sakura.jpg', 'biografia' => 'Sakura fue nombrada así por su pelaje tricolor que recuerda a los cerezos en flor. Es la gata más cariñosa del café y busca activamente el contacto con los visitantes.', 'curiosidad' => 'Ronronea tan fuerte que se puede escuchar desde el otro lado del café.', 'nivelInteractividad' => 5, 'horaActiva' => '12:00 - 19:00', 'gustos' => ['Regazos cálidos', 'Caricias', 'Ronronear'], 'disgustos' => ['Que la ignoren', 'El frío']],
            ],
            // --- Usagi Paradise (Conejos) ---
            2 => [
                ['tipo' => 'conejo', 'nombre' => 'Mimi', 'edad' => 2, 'personalidad' => 'activo', 'raza' => 'Holland Lop', 'peso' => 1.8, 'imagen' => '/images/animales/conejos/mimi.jpg', 'biografia' => 'Mimi es la estrella del café y le da su nombre. Sus orejas caídas y su naturaleza juguetona la hacen irresistible. Le encanta correr por el café y hacer binkies (saltos de alegría).', 'curiosidad' => 'Puede saltar hasta 1 metro de altura cuando está muy feliz.', 'nivelInteractividad' => 5, 'horaActiva' => '10:00 - 18:00', 'gustos' => ['Correr libre', 'Zanahorias', 'Caricias en la frente'], 'disgustos' => ['Que la persigan', 'Estar en brazos mucho tiempo']],
                ['tipo' => 'conejo', 'nombre' => 'Fuwari', 'edad' => 3, 'personalidad' => 'dócil', 'raza' => 'Angora', 'peso' => 3.5, 'imagen' => '/images/animales/conejos/fuwari.jpg', 'biografia' => 'Fuwari es una coneja Angora con un pelaje esponjoso espectacular. Su nombre significa esponjoso en japonés. Es muy tranquila y perfecta para quienes buscan una experiencia relajante.', 'curiosidad' => 'Su pelaje necesita cepillado diario y produce lana de la más alta calidad.', 'nivelInteractividad' => 4, 'horaActiva' => '11:00 - 17:00', 'gustos' => ['Ser cepillada', 'Heno fresco', 'Lugares frescos'], 'disgustos' => ['Calor excesivo', 'Pelaje mojado'], 'cuidadosEspeciales' => 'Necesita cepillado suave para evitar nudos.'],
                ['tipo' => 'conejo', 'nombre' => 'Choco', 'edad' => 1, 'personalidad' => 'travieso', 'raza' => 'Mini Rex', 'peso' => 1.5, 'imagen' => '/images/animales/conejos/choco.jpg', 'biografia' => 'Choco es el bebé travieso del café. Su pelaje aterciopelado color chocolate es irresistiblemente suave. Le encanta explorar y a veces mordisquea los cordones de los zapatos.', 'curiosidad' => 'El pelaje Rex es tan suave porque los pelos son más cortos y densos que en otras razas.', 'nivelInteractividad' => 5, 'horaActiva' => 'Todo el día', 'gustos' => ['Explorar', 'Mordisquear cosas', 'Jugar con pelotas'], 'disgustos' => ['Estar quieto', 'Que lo restrinjan']],
                ['tipo' => 'conejo', 'nombre' => 'Shiro', 'edad' => 4, 'personalidad' => 'relajado', 'raza' => 'Gigante de Flandes', 'peso' => 5.2, 'imagen' => '/images/animales/conejos/shiro.jpg', 'biografia' => 'Shiro es el gigante gentil del café. Con más de 5kg, impresiona por su tamaño pero es el más tranquilo de todos. Le gusta estirarse y dejarse acariciar durante horas.', 'curiosidad' => 'Los conejos gigantes de Flandes pueden llegar a pesar más de 10kg.', 'nivelInteractividad' => 4, 'horaActiva' => '12:00 - 16:00', 'gustos' => ['Estirarse', 'Caricias largas', 'Verduras frescas'], 'disgustos' => ['Movimientos rápidos', 'Que lo levanten']],
            ],
            // --- Soft Cloud (Chinchillas) ---
            3 => [
                ['tipo' => 'chinchilla', 'nombre' => 'Nube', 'edad' => 2, 'personalidad' => 'suave', 'color' => 'gris perla', 'imagen' => '/images/animales/chinchillas/nube.jpg', 'biografia' => 'La chinchilla más suave del mundo. Le encanta saltar de hombro en hombro.', 'nivelInteractividad' => 4, 'gustos' => ['Baños de arena', 'Pasas secas'], 'disgustos' => ['Calor', 'Agua'], 'cuidadosEspeciales' => 'No mojar nunca su pelaje.'],
                ['tipo' => 'chinchilla', 'nombre' => 'Niebla', 'edad' => 3, 'personalidad' => 'rápida', 'color' => 'gris oscuro', 'imagen' => '/images/animales/chinchillas/niebla.jpg', 'biografia' => 'Experta saltadora. Es difícil seguirla con la mirada cuando se activa al atardecer.', 'nivelInteractividad' => 3, 'gustos' => ['Escalar', 'Saltar'], 'disgustos' => ['Ruidos fuertes']],
                ['tipo' => 'chinchilla', 'nombre' => 'Copo', 'edad' => 1, 'personalidad' => 'tímida', 'color' => 'blanco', 'imagen' => '/images/animales/chinchillas/copo.jpg', 'biografia' => 'Un pequeño copo de nieve que prefiere observar desde su casa de madera.', 'nivelInteractividad' => 2, 'gustos' => ['Tranquilidad', 'Heno'], 'disgustos' => ['Manos bruscas']],
            ],
            // --- Chipmunk Forest (Ardillas) ---
            4 => [
                ['tipo' => 'ardilla', 'nombre' => 'Chip', 'edad' => 1, 'personalidad' => 'veloz', 'imagen' => '/images/animales/ardillas/chip.jpg', 'biografia' => 'Nunca para quieto. Corre por los tubos transparentes del techo a toda velocidad.', 'nivelInteractividad' => 2, 'gustos' => ['Nueces', 'Correr'], 'disgustos' => ['Que intenten agarrarlo']],
                ['tipo' => 'ardilla', 'nombre' => 'Dale', 'edad' => 1, 'personalidad' => 'glotón', 'imagen' => '/images/animales/ardillas/dale.jpg', 'biografia' => 'Siempre tiene los mofletes llenos de comida. Es el hermano tranquilo de Chip.', 'nivelInteractividad' => 3, 'gustos' => ['Pipas', 'Esconder comida'], 'disgustos' => ['Que le quiten su tesoro']],
                ['tipo' => 'ardilla', 'nombre' => 'Alvin', 'edad' => 2, 'personalidad' => 'líder', 'imagen' => '/images/animales/ardillas/alvin.jpg', 'biografia' => 'Vigila todo el café desde la rama más alta.', 'nivelInteractividad' => 2, 'gustos' => ['Alturas', 'Observar'], 'disgustos' => ['Suelo']],
            ],
            // --- Mame Shiba Café (Perros) ---
            5 => [
                ['tipo' => 'perro', 'nombre' => 'Hachiko', 'edad' => 2, 'personalidad' => 'enérgico', 'raza' => 'Mame Shiba', 'color' => 'sésamo rojo', 'imagen' => '/images/animales/perros/hachiko.jpg', 'biografia' => 'Hachiko es un remolino de energía. Te recibirá en la puerta con su característico baile de patas. Es el más pequeño de la manada pero tiene el espíritu más grande.', 'curiosidad' => 'Los Mame Shiba no son una raza oficial, sino Shibas criados selectivamente para ser un 30% más pequeños.', 'nivelInteractividad' => 5, 'horaActiva' => 'Todo el día', 'gustos' => ['Pelotas de tenis', 'Queso seco', 'Rascarse la barriga'], 'disgustos' => ['Lluvia', 'Que le soplen en la cara']],
                ['tipo' => 'perro', 'nombre' => 'Kenta', 'edad' => 4, 'personalidad' => 'digno', 'raza' => 'Mame Shiba', 'color' => 'negro y fuego', 'imagen' => '/images/animales/perros/kenta.jpg', 'biografia' => 'Kenta tiene el alma de un antiguo samurái. Es serio, leal y observa todo con calma desde su cojín elevado. Cuando decide que te quiere, no se separará de tu lado.', 'curiosidad' => 'Tiene unas marcas blancas sobre los ojos que parecen cejas (maro), dándole una expresión muy expresiva.', 'nivelInteractividad' => 3, 'horaActiva' => '12:00 - 16:00', 'gustos' => ['Dormir al sol', 'Masajes en el cuello', 'Carne seca'], 'disgustos' => ['Abrazos fuertes', 'Ruidos muy agudos']],
                ['tipo' => 'perro', 'nombre' => 'Momo', 'edad' => 1, 'personalidad' => 'cariñosa', 'raza' => 'Mame Shiba', 'color' => 'crema', 'imagen' => '/images/animales/perros/momo.jpg', 'biografia' => 'Momo (melocotón) es dulce como su nombre. A diferencia de la mayoría de Shibas, que son independientes, ella busca constantemente besos y abrazos de los visitantes.', 'curiosidad' => 'Su cola está tan enroscada que da casi dos vueltas completas.', 'nivelInteractividad' => 5, 'horaActiva' => 'Mañanas', 'gustos' => ['Regazos humanos', 'Juguetes de cuerda', 'Todos los humanos'], 'disgustos' => ['Estar sola', 'El aspirador']],
            ],
            // --- Mipig Cafe (Cerditos) ---
            6 => [
                ['tipo' => 'cerdito', 'nombre' => 'Babe', 'edad' => 1, 'personalidad' => 'mimoso', 'color' => 'rosa', 'peso' => 4.5, 'imagen' => '/images/animales/cerditos/babe.jpg', 'biografia' => 'Babe es un cerdito de regazo profesional. En cuanto te sientes en el suelo y cubras tus piernas con la manta, él vendrá corriendo para echarse una siesta sobre ti.', 'curiosidad' => 'Los cerdos son increíblemente limpios y no sudan, por lo que no tienen mal olor corporal.', 'nivelInteractividad' => 5, 'horaActiva' => 'Todo el día', 'gustos' => ['Mantas polares', 'Calor corporal', 'Leche tibia'], 'disgustos' => ['Suelos fríos', 'Movimientos bruscos']],
                ['tipo' => 'cerdito', 'nombre' => 'Trufa', 'edad' => 2, 'personalidad' => 'glotona', 'color' => 'manchado', 'peso' => 6.0, 'imagen' => '/images/animales/cerditos/trufa.jpg', 'biografia' => 'Trufa hace honor a su nombre: tiene un olfato increíble para detectar comida. Es la líder inteligente del grupo y ha aprendido a sentarse a cambio de premios.', 'curiosidad' => 'Los micro-pigs siguen creciendo hasta los 3 años, no se quedan bebés para siempre.', 'nivelInteractividad' => 4, 'horaActiva' => '10:00 - 18:00', 'gustos' => ['Manzanas', 'Zanahorias', 'Hocicar'], 'disgustos' => ['Esperar la cena', 'Que la despierten']],
                ['tipo' => 'cerdito', 'nombre' => 'Pinky', 'edad' => 0.5, 'personalidad' => 'tímida', 'color' => 'negro', 'peso' => 2.5, 'imagen' => '/images/animales/cerditos/pinky.jpg', 'biografia' => 'Pinky es la bebé de la familia, tan pequeña que cabe en dos manos. Aún está aprendiendo a confiar, pero si eres paciente, te dará pequeños empujoncitos con su nariz.', 'curiosidad' => 'Duerme amontonada con sus hermanos para mantener el calor (montaña de cerditos).', 'nivelInteractividad' => 3, 'horaActiva' => '11:00 - 15:00', 'gustos' => ['Calor', 'Paja fresca', 'Sus hermanos'], 'disgustos' => ['Ruidos fuertes', 'Estar sola']],
            ],
            // --- Parrot Talk (Loros) ---
            7 => [
                ['tipo' => 'loro', 'nombre' => 'Rio', 'edad' => 10, 'personalidad' => 'hablador', 'especie' => 'Guacamayo Azul', 'imagen' => '/images/animales/loros/rio.jpg', 'biografia' => 'La estrella del local. Saluda a todos los clientes con un "¡Konnichiwa!".', 'nivelInteractividad' => 5, 'gustos' => ['Hablar', 'Nueces'], 'disgustos' => ['Silencio']],
                ['tipo' => 'loro', 'nombre' => 'Kiwi', 'edad' => 5, 'personalidad' => 'bailarín', 'especie' => 'Agapornis', 'imagen' => '/images/animales/loros/kiwi.jpg', 'biografia' => 'Si le cantas, empieza a mover la cabeza al ritmo.', 'nivelInteractividad' => 4, 'gustos' => ['Música', 'Semillas'], 'disgustos' => ['Soledad']],
                ['tipo' => 'loro', 'nombre' => 'Mango', 'edad' => 3, 'personalidad' => 'curioso', 'especie' => 'Ninfa', 'imagen' => '/images/animales/loros/mango.jpg', 'biografia' => 'Le encanta aterrizar en la cabeza de los clientes desprevenidos.', 'nivelInteractividad' => 3, 'gustos' => ['Pelo humano', 'Brillos'], 'disgustos' => ['Manos rápidas']],
            ],
            // --- Capyba Land (Capibaras) ---
            8 => [
                ['tipo' => 'capybara', 'nombre' => 'Marron', 'edad' => 5, 'personalidad' => 'zen', 'peso' => 55, 'leGustaAgua' => true, 'imagen' => '/images/animales/capibaras/marron.jpg', 'biografia' => 'Marron es el patriarca del grupo y el capibara más famoso del café. Su expresión serena y su amor por los baños termales lo han convertido en una celebridad local.', 'curiosidad' => 'Los capibaras pueden contener la respiración bajo el agua hasta 5 minutos.', 'nivelInteractividad' => 5, 'horaActiva' => '09:00 - 17:00', 'gustos' => ['Onsen caliente', 'Sandía', 'Caricias en el lomo'], 'disgustos' => ['Agua fría', 'Estar solo'], 'cuidadosEspeciales' => 'Le encanta que le froten detrás de las orejas mientras está en el agua.'],
                ['tipo' => 'capybara', 'nombre' => 'Gonta', 'edad' => 3, 'personalidad' => 'glotón', 'peso' => 48, 'leGustaAgua' => true, 'imagen' => '/images/animales/capibaras/gonta.jpg', 'biografia' => 'Gonta es el capibara más joven y con mayor apetito. Siempre está buscando comida y es muy amigable con los visitantes.', 'curiosidad' => 'Un capibara adulto puede comer hasta 3.5kg de vegetales al día.', 'nivelInteractividad' => 5, 'horaActiva' => 'Todo el día', 'gustos' => ['Comer', 'Más comida', 'Nadar', 'Snacks'], 'disgustos' => ['Esperar por comida']],
                ['tipo' => 'capybara', 'nombre' => 'Fuku', 'edad' => 4, 'personalidad' => 'sociable', 'peso' => 52, 'leGustaAgua' => false, 'imagen' => '/images/animales/capibaras/fuku.jpg', 'biografia' => 'Fuku es único entre los capibaras: prefiere estar en tierra que en el agua. Le encanta socializar y suele acercarse a los grupos de visitantes.', 'curiosidad' => 'Fuku hace un sonido similar a un click cuando está contento.', 'nivelInteractividad' => 5, 'horaActiva' => '10:00 - 16:00', 'gustos' => ['Compañía humana', 'Césped fresco'], 'disgustos' => ['Agua profunda', 'Soledad']],
            ],
            // --- Alpaca Hill (Alpacas) ---
            9 => [
                ['tipo' => 'alpaca', 'nombre' => 'Paca', 'edad' => 3, 'personalidad' => 'suave', 'color' => 'blanco', 'imagen' => '/images/animales/alpacas/paca.jpg', 'biografia' => 'Parece una nube con patas. Es la alpaca más suave y le encanta que le cepillen el cuello.', 'nivelInteractividad' => 4, 'gustos' => ['Zanahorias', 'Cepillado'], 'disgustos' => ['Movimientos bruscos']],
                ['tipo' => 'alpaca', 'nombre' => 'Andes', 'edad' => 4, 'personalidad' => 'orgullosa', 'color' => 'marrón', 'imagen' => '/images/animales/alpacas/andes.jpg', 'biografia' => 'La reina del rebaño. Es muy fotogénica pero decide ella cuándo quiere interactuar.', 'nivelInteractividad' => 2, 'gustos' => ['Respeto', 'Comida'], 'disgustos' => ['Que la toquen sin permiso'], 'cuidadosEspeciales' => 'No colocarse detrás de ella (puede cocear si se asusta).'],
                ['tipo' => 'alpaca', 'nombre' => 'Cuzco', 'edad' => 2, 'personalidad' => 'curioso', 'color' => 'negro', 'imagen' => '/images/animales/alpacas/cuzco.jpg', 'biografia' => 'Muy curioso, te olerá el pelo y la ropa buscando snacks escondidos.', 'nivelInteractividad' => 4, 'gustos' => ['Heno', 'Oler cosas'], 'disgustos' => ['Sombreros grandes']],
            ],
            // --- Little Hooves (Caballos Mini) ---
            10 => [
                ['tipo' => 'caballo', 'nombre' => 'Spirit', 'edad' => 5, 'personalidad' => 'noble', 'raza' => 'Falabella', 'imagen' => '/images/animales/caballos/spirit.jpg', 'biografia' => 'Un pequeño caballo con un gran corazón. Perfecto para que los niños aprendan a cepillar.', 'nivelInteractividad' => 5, 'gustos' => ['Manzanas', 'Cepillado'], 'disgustos' => ['Gritos'], 'cuidadosEspeciales' => 'Usar cepillo suave siempre.'],
                ['tipo' => 'caballo', 'nombre' => 'Pegaso', 'edad' => 3, 'personalidad' => 'saltarín', 'raza' => 'Falabella', 'imagen' => '/images/animales/caballos/pegaso.jpg', 'biografia' => 'Le encanta saltar pequeños obstáculos en la pista de entrenamiento.', 'nivelInteractividad' => 4, 'gustos' => ['Ejercicio', 'Terrones de azúcar'], 'disgustos' => ['Estar quieto mucho tiempo']],
            ],
            // --- Quack Club (Patos) ---
            11 => [
                ['tipo' => 'pato', 'nombre' => 'Donald', 'edad' => 1, 'personalidad' => 'ruidoso', 'raza' => 'Call Duck', 'imagen' => '/images/animales/patos/donald.jpg', 'biografia' => 'El pato más vocal del grupo. Te saludará con un fuerte cuac en cuanto entres.', 'nivelInteractividad' => 3, 'gustos' => ['Guisantes', 'Nadar'], 'disgustos' => ['Que lo cojan en brazos']],
                ['tipo' => 'pato', 'nombre' => 'Daisy', 'edad' => 1, 'personalidad' => 'elegante', 'raza' => 'Call Duck', 'imagen' => '/images/animales/patos/daisy.jpg', 'biografia' => 'Siempre lleva un lazo rosa (de tela suave). Es la compañera inseparable de Donald.', 'nivelInteractividad' => 4, 'gustos' => ['Lechuga en agua', 'Lazos'], 'disgustos' => ['Suciedad']],
            ],
            // --- Pui Pui House (Cobayas) ---
            12 => [
                ['tipo' => 'cobaya', 'nombre' => 'Pui', 'edad' => 2, 'personalidad' => 'vocal', 'imagen' => '/images/animales/cobayas/pui.jpg', 'biografia' => 'En cuanto oye una bolsa de plástico, empieza a chillar pidiendo comida.', 'nivelInteractividad' => 5, 'gustos' => ['Pimiento rojo', 'Heno'], 'disgustos' => ['Soledad']],
                ['tipo' => 'cobaya', 'nombre' => 'Kui', 'edad' => 2, 'personalidad' => 'tímido', 'imagen' => '/images/animales/cobayas/kui.jpg', 'biografia' => 'Prefiere observar desde su túnel de heno, pero sale si tienes perejil.', 'nivelInteractividad' => 2, 'gustos' => ['Perejil', 'Túneles'], 'disgustos' => ['Espacios abiertos']],
                ['tipo' => 'cobaya', 'nombre' => 'Mui', 'edad' => 1, 'personalidad' => 'rápido', 'imagen' => '/images/animales/cobayas/mui.jpg', 'biografia' => 'El atleta del grupo. Corre haciendo "popcorning" cuando está feliz.', 'nivelInteractividad' => 4, 'gustos' => ['Correr', 'Escarola'], 'disgustos' => ['Que lo atrapen']],
            ],
            // --- Prairie Town (Perritos de la Pradera) ---
            13 => [
                ['tipo' => 'perrito_pradera', 'nombre' => 'Timon', 'edad' => 3, 'personalidad' => 'vigía', 'imagen' => '/images/animales/perritos/timon.jpg', 'biografia' => 'Siempre de pie sobre sus patas traseras vigilando el café.', 'nivelInteractividad' => 3, 'gustos' => ['Vigilar', 'Nueces'], 'disgustos' => ['Sorpresas por la espalda']],
                ['tipo' => 'perrito_pradera', 'nombre' => 'Pumba', 'edad' => 3, 'personalidad' => 'relajado', 'imagen' => '/images/animales/perritos/pumba.jpg', 'biografia' => 'Al contrario que Timon, Pumba prefiere dormir panza arriba pidiendo masajes.', 'nivelInteractividad' => 4, 'gustos' => ['Masajes de barriga', 'Raíces'], 'disgustos' => ['Trabajar']],
            ],
            // --- Slow Life (Tortugas) ---
            14 => [
                ['tipo' => 'tortuga', 'nombre' => 'Flash', 'edad' => 50, 'personalidad' => 'lento', 'especie' => 'Sulcata', 'imagen' => '/images/animales/reptiles/flash.jpg', 'biografia' => 'Lleva 50 años tomándose la vida con calma. Es una roca que camina.', 'nivelInteractividad' => 1, 'gustos' => ['Calor', 'Lechuga'], 'disgustos' => ['Frío', 'Ruido'], 'cuidadosEspeciales' => 'No intentar levantarla (pesa 40kg).'],
                ['tipo' => 'tortuga', 'nombre' => 'Tank', 'edad' => 30, 'personalidad' => 'pesado', 'especie' => 'Aldabra', 'imagen' => '/images/animales/reptiles/tank.jpg', 'biografia' => 'Una tortuga gigante joven (para su especie). Le encanta que le rasquen el caparazón.', 'nivelInteractividad' => 2, 'gustos' => ['Hibisco', 'Rascado'], 'disgustos' => ['Cambios rápidos']],
            ],
        ];

        // 3. INSERCIÓN EN BASE DE DATOS
        $stmt = $this->db->prepare("
            INSERT INTO animals (cafe_id, current_zone_id, name, species_type, age, personality, description, interaction_level, attributes, image_url, current_status)
            VALUES (:cid, :zid, :name, :type, :age, :pers, :desc, :level, :attrs, :img, 'active')
        ");

        foreach ($animales as $cafeId => $lista) {
            // Buscamos la zona de interacción de este café
            $stmtZone = $this->db->prepare("SELECT id FROM cafe_zones WHERE cafe_id = ? AND type = 'interaction' LIMIT 1");
            $stmtZone->execute([$cafeId]);
            $zoneId = $stmtZone->fetchColumn();

            // Si no hay zona, usar NULL
            $zoneId = $zoneId ?: null;

            $inserted = 0;
            foreach ($lista as $a) {
                // Separamos columnas SQL de atributos JSON
                $sqlFields = ['nombre', 'tipo', 'edad', 'personalidad', 'biografia', 'nivelInteractividad', 'imagen'];

                $attributes = array_filter($a, static function ($key) use ($sqlFields) {
                    return !in_array($key, $sqlFields, true);
                }, ARRAY_FILTER_USE_KEY);

                try {
                    $stmt->execute([
                        ':cid' => $cafeId,
                        ':zid' => $zoneId,
                        ':name' => $a['nombre'],
                        ':type' => $a['tipo'],
                        ':age' => $a['edad'],
                        ':pers' => $a['personalidad'],
                        ':desc' => $a['biografia'], // La biografía va al campo description
                        ':level' => $a['nivelInteractividad'],
                        ':attrs' => json_encode($attributes, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                        ':img' => $a['imagen'],
                    ]);
                    $inserted++;
                } catch (Throwable $e) {
                    Logger::error('AnimalSeeder: insert failed', ['cafe' => $cafeId, 'animal' => $a['nombre'] ?? null, 'exception' => $e->getMessage()]);
                }
            }

            Logger::info('AnimalSeeder: cafe processed', ['cafe_id' => $cafeId, 'inserted' => $inserted]);
        }
        echo "Animales migrados.\n";
        Logger::info('AnimalSeeder: completed');
    }
}
