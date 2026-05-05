<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Core\Http\ResponseFactory;
use App\Http\Controllers\Api\AbstractApiController;
use App\Repositories\Contracts\NewsletterSubscriptionRepositoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * API REST: Gestión de newsletter (Admin)
 *
 * Rutas:
 * - DELETE /api/v1/admin/newsletter/subscribers/{email} → delete()
 * - GET    /api/v1/admin/newsletter/export              → export()
 */
final class NewsletterApiController extends AbstractApiController
{
    public function __construct(
        ResponseFactory $response,
        private readonly NewsletterSubscriptionRepositoryInterface $newsletterRepo,
    ) {
        parent::__construct($response);
    }

    /**
     * DELETE /api/v1/admin/newsletter/subscribers/{email}
     */
    public function delete(ServerRequestInterface $request, string $email): ResponseInterface
    {
        $email = \urldecode($email);

        if ($email === '' || !\filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            return $this->response->json(['ok' => false, 'error' => 'Email inválido'], 422);
        }

        $deleted = $this->newsletterRepo->deleteByEmail($email);

        if (!$deleted) {
            return $this->response->json(['ok' => false, 'error' => 'Suscriptor no encontrado'], 404);
        }

        return $this->success(['deleted' => true]);
    }

    /**
     * GET /api/v1/admin/newsletter/export
     * Exporta todos los suscriptores confirmados en CSV con BOM UTF-8.
     */
    public function export(ServerRequestInterface $request): ResponseInterface
    {
        $emails = $this->newsletterRepo->getConfirmedEmails(10000);

        $tmp = \fopen('php://temp', 'rw+');
        \fwrite($tmp, \chr(0xEF) . \chr(0xBB) . \chr(0xBF));
        \fputcsv($tmp, ['Email', 'Confirmado en', 'Suscrito en'], ',', '"');

        foreach ($emails as $row) {
            \fputcsv($tmp, [(string) $row, '', ''], ',', '"');
        }

        \rewind($tmp);
        $csv = (string) \stream_get_contents($tmp);
        \fclose($tmp);

        $filename = 'newsletter_suscriptores_' . \date('Y-m-d') . '.csv';

        return $this->response->html($csv, 200)
            ->withHeader('Content-Type', 'text/csv; charset=utf-8')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }
}
