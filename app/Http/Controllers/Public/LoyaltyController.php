<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Core\Flash;
use App\Core\Http\ResponseFactory;
use App\Core\Session;
use App\Core\View;
use App\Services\LoyaltyService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Controlador público de fidelización
 */
final class LoyaltyController
{
    private LoyaltyService $loyaltyService;

    public function __construct(private readonly ResponseFactory $response)
    {
        $this->loyaltyService = new LoyaltyService();
    }

    /**
     * Vista de tarjeta de fidelización
     * GET /loyalty/card
     */
    public function card(ServerRequestInterface $request): ?ResponseInterface
    {
        $userId = Session::get('user_id');

        if (!$userId) {
            Flash::error('Debes iniciar sesión para ver tu tarjeta');
            return $this->response->redirect('/login');
        }

        $result = $this->loyaltyService->getCardStatus((int) $userId);

        if (!$result->ok) {
            Flash::error($result->error);
            return $this->response->redirect('/');
        }

        View::render('public/loyalty/card', [
            'card' => $result->data['card'],
            'available_rewards' => $result->data['available_rewards'],
            'redeemed_rewards' => $result->data['redeemed_rewards'],
            'tier_progress' => $result->data['tier_progress'],
            'page_title' => '🎴 Mi Tarjeta de Fidelización - Komorebi Café',
            'extraCss' => ['loyalty.css']
        ]);
        return null;
    }
}
