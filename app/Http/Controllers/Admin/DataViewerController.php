<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Container;
use App\Core\View;
use App\Repositories\Contracts\StatisticsRepositoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

final class DataViewerController
{
    private StatisticsRepositoryInterface $statsRepo;

    public function __construct(?StatisticsRepositoryInterface $statsRepo = null)
    {
        $this->statsRepo = $statsRepo ?? Container::make(StatisticsRepositoryInterface::class);
    }

    public function index(ServerRequestInterface $request): ?ResponseInterface
    {
        try {
            $stats   = $this->statsRepo->getDataViewerStats();
            $samples = $this->statsRepo->getDataViewerSamples();
        } catch (Throwable) {
            $stats = \array_fill_keys(
                ['users', 'staff', 'cafes', 'animals', 'products', 'reservations',
                 'reservations_with_slot', 'time_slots', 'time_slots_available', 'reviews', 'incidents'],
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
