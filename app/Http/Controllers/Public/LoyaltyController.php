<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Services\LoyaltyService;
use App\Core\View;

/**
 * Controlador público de fidelización
 */
final class LoyaltyController
{
    private LoyaltyService $loyaltyService;

    public function __construct()
    {
        $this->loyaltyService = new LoyaltyService();
    }

    /**
     * Vista de tarjeta de fidelización
     * GET /loyalty/card
     */
    public function card(): void
    {
        $userId = $_SESSION['user_id'] ?? null;

        if (!$userId) {
            $_SESSION['flash_error'] = 'Debes iniciar sesión para ver tu tarjeta';
            header('Location: /login');
            exit;
        }

        $result = $this->loyaltyService->getCardStatus((int)$userId);

        if (!$result->ok) {
            $_SESSION['flash_error'] = $result->error;
            header('Location: /');
            exit;
        }

        View::render('public/loyalty/card', [
            'card' => $result->data['card'],
            'available_rewards' => $result->data['available_rewards'],
            'redeemed_rewards' => $result->data['redeemed_rewards'],
            'tier_progress' => $result->data['tier_progress'],
            'page_title' => '🎴 Mi Tarjeta de Fidelización - Komorebi Café',
            'extraCss' => ['loyalty.css']
        ]);
    }
}
