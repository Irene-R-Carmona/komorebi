<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Core\Http\ResponseFactory;
use App\Http\Controllers\Api\AbstractApiController;
use App\Services\Contracts\NewsletterServiceInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class NewsletterApiController extends AbstractApiController
{
    public function __construct(
        private readonly NewsletterServiceInterface $newsletterService,
        ResponseFactory $response,
    ) {
        parent::__construct($response);
    }

    /**
     * Suscribir a newsletter
     * POST /api/newsletter/subscribe
     */
    public function subscribe(ServerRequestInterface $request): ResponseInterface
    {
        $input = \json_decode((string) $request->getBody(), true) ?? [];
        $email = $input['email'] ?? '';

        if (empty($email)) {
            return $this->unprocessable('Email requerido', 'email_required');
        }

        $result = $this->newsletterService->subscribe($email);

        if (!$result->ok) {
            return $this->response->problem(
                $result,
                400
            );
        }

        return $this->success(['message' => $result->data['message'] ?? 'Suscripción procesada']);
    }
}
