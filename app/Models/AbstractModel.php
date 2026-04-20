<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Clase base abstracta para los modelos Active Record del proyecto.
 *
 * Centraliza el patrón de inyección opcional de PDO con lazy-init
 * via Database::getConnection() que se repite en todos los modelos.
 *
 * Modelos que extienden esta clase:
 *   Animal, Allergen, Cafe, MenuCategory, Permission, Product,
 *   ReservationItem, Role, Setting, Tracker, Waitlist.
 *
 * Modelos que NO extienden (razones válidas):
 *   - AuditLog: 100% estático, no instancia PDO por constructor.
 *   - User: constructor non-nullable + lógica de seguridad adicional.
 *   - LoyaltyCard/LoyaltyReward/LoyaltyRewardCatalog: requieren PDO non-nullable.
 *   - Reservation, TimeSlot, Review: dependencias adicionales en constructor.
 */
abstract class AbstractModel
{
    protected ?PDO $db = null;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db;
    }

    /**
     * Devuelve la conexión PDO activa.
     * Si no se inyectó en el constructor, obtiene la instancia global del singleton.
     */
    protected function getDb(): PDO
    {
        return $this->db ??= Database::getConnection();
    }
}
