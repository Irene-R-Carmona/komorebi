<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Database;
use App\Core\View;
use PDO;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class DataViewerController
{
    public function index(ServerRequestInterface $request): ?ResponseInterface
    {
        try {
            $db = Database::getConnection();
            $stats = [
                'users' => (int) $db->query('SELECT COUNT(*) FROM users')->fetchColumn(),
                'staff' => (int) $db->query("SELECT COUNT(*) FROM users WHERE role != 'user'")->fetchColumn(),
                'cafes' => (int) $db->query('SELECT COUNT(*) FROM cafes')->fetchColumn(),
                'animals' => (int) $db->query('SELECT COUNT(*) FROM animals')->fetchColumn(),
                'products' => (int) $db->query('SELECT COUNT(*) FROM products')->fetchColumn(),
                'reservations' => (int) $db->query('SELECT COUNT(*) FROM reservations')->fetchColumn(),
                'reservations_with_slot' => (int) $db->query('SELECT COUNT(*) FROM reservations WHERE time_slot_id IS NOT NULL')->fetchColumn(),
                'time_slots' => (int) $db->query('SELECT COUNT(*) FROM time_slots')->fetchColumn(),
                'time_slots_available' => (int) $db->query('SELECT COUNT(*) FROM time_slots WHERE slot_date >= CURDATE() AND is_blocked = 0')->fetchColumn(),
                'reviews' => (int) $db->query('SELECT COUNT(*) FROM reviews')->fetchColumn(),
                'incidents' => (int) $db->query('SELECT COUNT(*) FROM animal_health_checks')->fetchColumn(),
            ];
            $samples = [
                'cafes' => $db->query('SELECT name, animal_type, capacity_max, opening_time, closing_time, NULL AS rating_avg FROM cafes LIMIT 10')->fetchAll(PDO::FETCH_ASSOC),
                'products' => $db->query('SELECT name, japanese_name, price, duration_minutes AS duration, min_pax, max_pax FROM products LIMIT 10')->fetchAll(PDO::FETCH_ASSOC),
                'staff' => $db->query("SELECT u.name, u.email, u.role AS roles, NULL AS cafe FROM users u WHERE u.role != 'user' LIMIT 10")->fetchAll(PDO::FETCH_ASSOC),
                'users' => $db->query("SELECT name, email, role AS roles FROM users WHERE role = 'user' LIMIT 10")->fetchAll(PDO::FETCH_ASSOC),
                'reservations' => $db->query('SELECT u.name AS user, c.name AS cafe, p.name AS pass_name, p.price AS pass_unit_price, r.reservation_date, r.reservation_time, r.guest_count, r.status, r.time_slot_id FROM reservations r LEFT JOIN users u ON u.id = r.user_id LEFT JOIN cafes c ON c.id = r.cafe_id LEFT JOIN products p ON p.id = r.pass_product_id ORDER BY r.id DESC LIMIT 10')->fetchAll(PDO::FETCH_ASSOC),
                'time_slots' => $db->query('SELECT id, cafe_id, slot_date, start_time, end_time, capacity_max, booked_count, is_blocked FROM time_slots ORDER BY slot_date DESC LIMIT 10')->fetchAll(PDO::FETCH_ASSOC),
                'reviews' => $db->query('SELECT id, user_id, cafe_id, rating, comment, is_approved FROM reviews ORDER BY id DESC LIMIT 10')->fetchAll(PDO::FETCH_ASSOC),
                'incidents' => $db->query('SELECT id, animal_id, check_date, health_status, notes FROM animal_health_checks ORDER BY id DESC LIMIT 10')->fetchAll(PDO::FETCH_ASSOC),
            ];
        } catch (\Throwable) {
            $stats = \array_fill_keys(
                [
                    'users',
                    'staff',
                    'cafes',
                    'animals',
                    'products',
                    'reservations',
                    'reservations_with_slot',
                    'time_slots',
                    'time_slots_available',
                    'reviews',
                    'incidents',
                ],
                0
            );
            $samples = \array_fill_keys(
                ['cafes', 'products', 'staff', 'users', 'reservations', 'time_slots', 'reviews', 'incidents'],
                []
            );
        }

        View::render('admin/data-viewer', ['stats' => $stats, 'samples' => $samples], ['admin/data-viewer.css'], 'backoffice');

        return null;
    }
}
