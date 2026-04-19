<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Core\View;
use App\Services\NewsletterService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class NewsletterController
{
    /**
     * Constructor con inyección de dependencias.
     */
    public function __construct(
        private NewsletterService $newsletterService
    ) {
    }

    /**
     * Suscribirse al newsletter (POST /newsletter/subscribe)
     */
    public function subscribe(ServerRequestInterface $request): ?ResponseInterface
    {
        $data = $request->getParsedBody();
        $email = $data['email'] ?? '';

        if (empty($email)) {
            View::render('public/newsletter/subscribe', ['success' => false, 'message' => 'Email requerido']);

            return null;
        }

        $result = $this->newsletterService->subscribe($email);
        View::render('public/newsletter/subscribe', ['success' => $result->ok, 'message' => $result->ok ? ($result->data['message'] ?? '') : ($result->error ?? '')]);

        return null;
    }

    /**
     * Verificar email (GET /newsletter/verify?token=...)
     */
    public function verify(ServerRequestInterface $request): ?ResponseInterface
    {
        $queryParams = $request->getQueryParams();
        $token = $queryParams['token'] ?? '';

        $result = $this->newsletterService->confirm($token);
        View::render('public/newsletter/confirm', [
            'success' => $result['success'],
            'message' => $result['message'],
            'email' => $result['email'] ?? null,
        ]);

        return null;
    }

    /**     * Confirmar suscripción (GET /newsletter/confirm/{token})
     */
    public function confirm(ServerRequestInterface $request): ?ResponseInterface
    {
        $queryParams = $request->getQueryParams();
        $token = $queryParams['token'] ?? '';

        $result = $this->newsletterService->confirm($token);
        View::render('public/newsletter/confirm', [
            'success' => $result['success'],
            'message' => $result['message'],
            'email' => $result['email'] ?? null,
        ]);

        return null;
    }

    /**
     * Cancelar suscripción (GET /newsletter/unsubscribe/{token})
     */
    public function unsubscribe(ServerRequestInterface $request): ?ResponseInterface
    {
        $queryParams = $request->getQueryParams();
        $token = $queryParams['token'] ?? '';

        $result = $this->newsletterService->unsubscribe($token);
        View::render('public/newsletter/unsubscribe', [
            'success' => $result['success'],
            'message' => $result['message'],
        ]);

        return null;
    }
}
