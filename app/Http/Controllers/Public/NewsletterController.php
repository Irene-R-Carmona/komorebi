<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Services\NewsletterService;
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
    public function subscribe(ServerRequestInterface $request): void
    {
        $data = $request->getParsedBody();
        $email = $data['email'] ?? '';

        if (empty($email)) {
            $success = false;
            $message = 'Email requerido';
            require __DIR__ . '/../../../../resources/views/public/newsletter/subscribe.php';
            exit;
        }

        $result = $this->newsletterService->subscribe($email);
        $success = $result['success'];
        $message = $result['message'];

        require __DIR__ . '/../../../../resources/views/public/newsletter/subscribe.php';
        exit;
    }

    /**
     * Verificar email (GET /newsletter/verify?token=...)
     */
    public function verify(ServerRequestInterface $request): void
    {
        $queryParams = $request->getQueryParams();
        $token = $queryParams['token'] ?? '';

        $result = $this->newsletterService->confirm($token);

        // Renderizar página de confirmación
        $success = $result['success'];
        $message = $result['message'];
        $email = $result['email'] ?? null;

        require __DIR__ . '/../../../resources/views/public/newsletter/confirm.php';
        exit;
    }

    /**     * Confirmar suscripción (GET /newsletter/confirm/{token})
     */
    public function confirm(array $params): void
    {
        $token = $params['token'] ?? '';

        $result = $this->newsletterService->confirm($token);

        // Renderizar página de confirmación
        $success = $result['success'];
        $message = $result['message'];
        $email = $result['email'] ?? null;

        require __DIR__ . '/../../resources/views/public/newsletter/confirm.php';
        exit;
    }

    /**
     * Cancelar suscripción (GET /newsletter/unsubscribe/{token})
     */
    public function unsubscribe(array $params): void
    {
        $token = $params['token'] ?? '';

        $result = $this->newsletterService->unsubscribe($token);

        // Renderizar página de baja
        $success = $result['success'];
        $message = $result['message'];

        require __DIR__ . '/../../resources/views/public/newsletter/unsubscribe.php';
        exit;
    }
}
