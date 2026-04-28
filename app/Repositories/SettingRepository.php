<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Domain\DTO\SettingDTO;
use App\Domain\Mappers\SettingMapper;
use App\Repositories\Contracts\SettingRepositoryInterface;
use PDO;

final class SettingRepository implements SettingRepositoryInterface
{
    private PDO $db;

    private SettingMapper $mapper;

    public function __construct(?PDO $db = null, ?SettingMapper $mapper = null)
    {
        $this->db = $db ?? Database::getConnection();
        $this->mapper = $mapper ?? new SettingMapper();
    }

    /**
     * @return SettingDTO[]
     */
    public function findAll(): array
    {
        $rows = $this->db->query(
            'SELECT * FROM settings ORDER BY group_name, `key`'
        )->fetchAll(PDO::FETCH_ASSOC);

        return \array_map(fn(array $row): SettingDTO => $this->mapper->toDTO($row), $rows);
    }

    /**
     * @return SettingDTO[]
     */
    public function findByGroup(string $group): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM settings WHERE group_name = :group ORDER BY `key`'
        );
        $stmt->execute(['group' => $group]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return \array_map(fn(array $row): SettingDTO => $this->mapper->toDTO($row), $rows);
    }
}
