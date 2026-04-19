<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Repositories\Contracts\SettingRepositoryInterface;
use PDO;

final class SettingRepository implements SettingRepositoryInterface
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    public function findAll(): array
    {
        return $this->db->query(
            'SELECT * FROM settings ORDER BY group_name, `key`'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findByGroup(string $group): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM settings WHERE group_name = :group ORDER BY `key`'
        );
        $stmt->execute(['group' => $group]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
