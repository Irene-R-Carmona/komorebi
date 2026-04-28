<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Container;
use App\Core\Http\ResponseFactory;
use App\Core\View;
use App\Http\Transformers\WaitlistTransformer;
use App\Repositories\Contracts\WaitlistRepositoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * WaitlistController - Panel de administración de listas de espera
 */
final class WaitlistController
{
    private WaitlistRepositoryInterface $waitlistRepo;
    private WaitlistTransformer $waitlistTransformer;
    private ResponseFactory $response;

    public function __construct(
        ?WaitlistRepositoryInterface $waitlistRepo = null,
        ?ResponseFactory $response = null,
        ?WaitlistTransformer $waitlistTransformer = null,
    ) {
        $this->waitlistRepo = $waitlistRepo ?? Container::make(WaitlistRepositoryInterface::class);
        $this->response = $response ?? new ResponseFactory();
        $this->waitlistTransformer = $waitlistTransformer ?? new WaitlistTransformer();
    }

    public function index(ServerRequestInterface $request): ?ResponseInterface
    {
        $queryParams = $request->getQueryParams();
        $filters = [
            'cafe_id' => $queryParams['cafe_id'] ?? null,
            'status' => $queryParams['status'] ?? 'waiting',
            'date' => $queryParams['date'] ?? null,
        ];

        $waitlists = $this->waitlistTransformer->collection(
            $this->waitlistRepo->getAllWithDetails($filters)
        );

        $summary = $this->waitlistRepo->getSummaryByStatus();

        View::render('admin/waitlist/index', [
            'waitlists' => $waitlists,
            'summary' => $summary,
            'filters' => $filters,
        ], [], 'backoffice');

        return null;
    }

    public function show(ServerRequestInterface $request, int $id): ?ResponseInterface
    {
        $rawWaitlist = $this->waitlistRepo->findById($id);

        if (!$rawWaitlist) {
            View::render('errors/404', [], [], 'errors');

            return null;
        }

        View::render('admin/waitlist/show', [
            'waitlist' => $this->waitlistTransformer->transform($rawWaitlist->toViewArray()),
        ], [], 'backoffice');

        return null;
    }

    public function cancel(ServerRequestInterface $request, int $id): ?ResponseInterface
    {
        if ($request->getMethod() !== 'POST') {
            return $this->response->html('Method not allowed', 405);
        }

        if ($this->waitlistRepo->cancelById($id)) {
            return $this->response->redirect('/admin/waitlists?msg=cancelled');
        }

        return $this->response->html('Error cancelando waitlist', 500);
    }
}
