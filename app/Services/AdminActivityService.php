<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Container;
use App\Core\Database;
use App\Core\Env;
use App\Core\Logger;
use App\Repositories\Contracts\StatisticsRepositoryInterface;
use App\Repositories\Contracts\UserManagementRepositoryInterface;
use App\Services\Contracts\AdminActivityServiceInterface;
use Exception;
use Override;
use PDOException;
use Redis;

final class AdminActivityService implements AdminActivityServiceInterface
{
    private StatisticsRepositoryInterface $statsRepo;
    private UserManagementRepositoryInterface $userMgmtRepo;

    public function __construct(
        ?StatisticsRepositoryInterface $statsRepo = null,
        ?UserManagementRepositoryInterface $userMgmtRepo = null,
    ) {
        $this->statsRepo    = $statsRepo    ?? Container::make(StatisticsRepositoryInterface::class);
        $this->userMgmtRepo = $userMgmtRepo ?? Container::make(UserManagementRepositoryInterface::class);
    }

    #[Override]
    public function getRecentReservations(int $limit = 10): array
    {
        try {
            return $this->statsRepo->getRecentReservations($limit);
        } catch (PDOException $e) {
            Logger::error('[AdminActivityService] getRecentReservations: ' . $e->getMessage());

            return [];
        }
    }

    #[Override]
    public function getUsersWithRoles(): array
    {
        return $this->userMgmtRepo->getUsersWithRoles();
    }

    #[Override]
    public function getProductsWithCategories(): array
    {
        return $this->statsRepo->getProductsWithCategories();
    }

    #[Override]
    public function getReservationsWithDetails(int $limit = 100): array
    {
        return $this->statsRepo->getReservationsWithDetails($limit);
    }

    #[Override]
    public function getRecentActivity(int $limit = 10): array
    {
        try {
            return $this->statsRepo->getRecentActivity($limit);
        } catch (PDOException $e) {
            Logger::error('[AdminActivityService] getRecentActivity: ' . $e->getMessage());

            return [];
        }
    }

    /**
     * @return array{database: string, cache: string, email: string}
     */
    #[Override]
    public function getSystemStatus(): array
    {
        return [
            'database' => $this->checkDatabase(),
            'cache'    => $this->checkRedis(),
            'email'    => $this->checkSmtp(),
        ];
    }

    private function checkDatabase(): string
    {
        try {
            Database::getConnection()->query('SELECT 1')->execute();

            return 'online';
        } catch (PDOException $e) {
            Logger::error('[AdminActivityService] DB check failed: ' . $e->getMessage());

            return 'offline';
        }
    }

    private function checkRedis(): string
    {
        if (!\extension_loaded('redis')) {
            return 'offline';
        }

        $status = 'offline';

        try {
            $redis    = new Redis();
            $host     = Env::get('REDIS_HOST', 'cache');
            $port     = (int) Env::get('REDIS_PORT', '6379');
            $password = Env::get('REDIS_PASSWORD');

            if ($redis->connect($host, $port, 1)) {
                if ($password && $password !== '') {
                    $redis->auth($password);
                }
                $redis->ping();
                $redis->close();
                $status = 'online';
            }
        } catch (Exception $e) {
            Logger::error('[AdminActivityService] Redis check failed: ' . $e->getMessage());
        }

        return $status;
    }

    private function checkSmtp(): string
    {
        $smtpHost = Env::get('MAIL_HOST');
        if (!$smtpHost || $smtpHost === 'mailpit') {
            return 'online';
        }

        $status = 'offline';

        try {
            $socket = @\fsockopen($smtpHost, (int) Env::get('MAIL_PORT', '587'), $errno, $errstr, 5);
            if ($socket) {
                \fclose($socket);
                $status = 'online';
            }
        } catch (Exception) {
            // status stays 'offline'
        }

        return $status;
    }

    /**
     * @return array{labels: array<int, string>, values: array<int, int>}
     */
    #[Override]
    public function getReservationsChartData(): array
    {
        try {
            return $this->statsRepo->getReservationsChartData();
        } catch (Exception $e) {
            Logger::error('[AdminActivityService] Chart data error: ' . $e->getMessage());

            return [
                'labels' => ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'],
                'values' => [0, 0, 0, 0, 0, 0, 0],
            ];
        }
    }
}
