<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Core\Container;
use App\Core\CookieManager;
use App\Core\Http\ResponseFactory;
use App\Http\Controllers\Api\AbstractApiController;
use App\Repositories\Contracts\CafeRepositoryInterface;
use App\Services\Contracts\RecentlyViewedServiceInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class CookieController extends AbstractApiController
{
    private RecentlyViewedServiceInterface $recentlyViewed;
    private CafeRepositoryInterface $cafeRepo;

    public function __construct(
        ResponseFactory $response,
        ?RecentlyViewedServiceInterface $recentlyViewed = null,
        ?CafeRepositoryInterface $cafeRepo = null,
    ) {
        parent::__construct($response);
        $this->recentlyViewed = $recentlyViewed ?? Container::make(RecentlyViewedServiceInterface::class);
        $this->cafeRepo = $cafeRepo ?? Container::make(CafeRepositoryInterface::class);
    }

    /**
     * Gestionar consentimiento de cookies (consolida accept/reject/update)
     * PATCH /api/v1/cookies
     * Body: {"consent": "all"|"none"|"custom", [essential, functional, analytics]}
     */
    public function consent(ServerRequestInterface $request): ResponseInterface
    {
        $body = (array) ($request->getParsedBody() ?? []);
        $type = (string) ($body['consent'] ?? '');

        if ($type === '') {
            return $this->unprocessable('El campo consent es requerido');
        }

        return match ($type) {
            'all'  => $this->handleAcceptAll(),
            'none' => $this->handleRejectOptional(),
            'custom' => $this->handleCustomConsent($body),
            default => $this->unprocessable('consent debe ser all, none o custom'),
        };
    }

    private function handleAcceptAll(): ResponseInterface
    {
        CookieManager::acceptAll();

        return $this->success(['message' => 'Todas las cookies funcionales han sido aceptadas']);
    }

    private function handleRejectOptional(): ResponseInterface
    {
        CookieManager::rejectOptional();

        return $this->success(['message' => 'Solo se utilizarán cookies esenciales']);
    }

    private function handleCustomConsent(array $body): ResponseInterface
    {
        if (!isset($body['essential'], $body['functional'], $body['analytics'])) {
            return $this->unprocessable('Faltan campos requeridos: essential, functional, analytics');
        }

        CookieManager::saveConsent($body);

        if (!$body['functional']) {
            CookieManager::delete(CookieManager::FILTER_PREFERENCES);
            CookieManager::delete(CookieManager::RECENTLY_VIEWED);
            CookieManager::delete(CookieManager::NEWSLETTER_PROMPTED);
            CookieManager::delete(CookieManager::DIETARY_PREFERENCES);
        }

        return $this->success(['message' => 'Preferencias guardadas correctamente']);
    }

    /**
     * Aceptar todas las cookies funcionales
     * POST /api/cookies/accept
     */
    public function accept(ServerRequestInterface $request): ResponseInterface
    {
        CookieManager::acceptAll();

        return $this->success(['message' => 'Todas las cookies funcionales han sido aceptadas']);
    }

    /**
     * Rechazar cookies opcionales (solo esenciales)
     * POST /api/cookies/reject
     */
    public function reject(ServerRequestInterface $request): ResponseInterface
    {
        CookieManager::rejectOptional();

        return $this->success(['message' => 'Solo se utilizarán cookies esenciales']);
    }

    /**
     * Guardar preferencias personalizadas
     * POST /api/cookies/update
     * Body: {"essential": true, "functional": true/false, "analytics": true/false}
     */
    public function update(ServerRequestInterface $request): ResponseInterface
    {
        $preferences = $request->getParsedBody();

        if (!\is_array($preferences)) {
            return $this->unprocessable('Datos inválidos');
        }

        if (!isset($preferences['essential'], $preferences['functional'], $preferences['analytics'])) {
            return $this->unprocessable('Faltan campos requeridos');
        }

        CookieManager::saveConsent($preferences);

        if (!$preferences['functional']) {
            CookieManager::delete(CookieManager::FILTER_PREFERENCES);
            CookieManager::delete(CookieManager::RECENTLY_VIEWED);
            CookieManager::delete(CookieManager::NEWSLETTER_PROMPTED);
            CookieManager::delete(CookieManager::DIETARY_PREFERENCES);
        }

        return $this->success(['message' => 'Preferencias guardadas correctamente']);
    }

    /**
     * Obtener preferencias actuales
     * GET /api/cookies/get-preferences
     */
    public function getPreferences(ServerRequestInterface $request): ResponseInterface
    {
        $consent = CookieManager::get(CookieManager::COOKIE_CONSENT, '{}');
        $preferences = \json_decode((string) $consent, true) ?: [
            'essential' => true,
            'functional' => false,
            'analytics' => false,
        ];

        return $this->success(['preferences' => $preferences]);
    }

    /**
     * Guardar filtros del catálogo
     * POST /api/cookies/save-filters
     * Body: {"origen": [...], "tostado": [...], "precio": {"min": 10, "max": 25}}
     */
    public function saveFilters(ServerRequestInterface $request): ResponseInterface
    {
        $filters = $request->getParsedBody();

        if (!\is_array($filters)) {
            return $this->unprocessable('Datos inválidos');
        }

        CookieManager::saveFilters($filters);

        return $this->success(['message' => 'Filtros guardados']);
    }

    /**
     * Obtener filtros guardados
     * GET /api/cookies/get-filters
     */
    public function getFilters(ServerRequestInterface $request): ResponseInterface
    {
        return $this->success(['filters' => CookieManager::getFilters()]);
    }

    /**
     * Limpiar filtros guardados
     * DELETE /api/v1/cookies/filters
     */
    public function clearFilters(ServerRequestInterface $request): ResponseInterface
    {
        CookieManager::delete(CookieManager::FILTER_PREFERENCES);

        return $this->noContent();
    }

    /**
     * Guardar preferencias dietéticas
     * POST /api/cookies/save-dietary
     * Body: {"alergias": [...], "vegano": bool, "sinGluten": bool, "notas": "..."}
     */
    public function saveDietary(ServerRequestInterface $request): ResponseInterface
    {
        $prefs = $request->getParsedBody();

        if (!\is_array($prefs)) {
            return $this->unprocessable('Datos inválidos');
        }

        CookieManager::saveDietaryPreferences($prefs);

        return $this->success(['message' => 'Preferencias dietéticas guardadas']);
    }

    /**
     * Obtener preferencias dietéticas
     * GET /api/cookies/get-dietary
     */
    public function getDietary(ServerRequestInterface $request): ResponseInterface
    {
        return $this->success(['preferences' => CookieManager::getDietaryPreferences()]);
    }

    /**
     * Añadir café a vistos recientemente
     * POST /api/cookies/recently-viewed/add
     * Body: { "cafeId": number }
     */
    public function addRecentlyViewed(ServerRequestInterface $request): ResponseInterface
    {
        $input = $request->getParsedBody();

        if (!isset($input['cafeId']) || !\is_numeric($input['cafeId'])) {
            return $this->unprocessable('cafeId es requerido y debe ser numérico');
        }

        $result = $this->recentlyViewed->add((int) $input['cafeId']);

        return $this->success([
            'added' => $result,
            'message' => $result ? 'Café añadido a vistos recientemente' : 'Error al guardar',
        ]);
    }

    /**
     * Obtener cafés vistos recientemente
     * GET /api/cookies/recently-viewed
     */
    public function getRecentlyViewed(ServerRequestInterface $request): ResponseInterface
    {
        return $this->success([
            'cafeIds' => $this->recentlyViewed->getAll(),
            'maxItems' => $this->recentlyViewed->getMaxItems(),
        ]);
    }

    /**
     * Obtener datos completos de cafés vistos recientemente
     * GET /api/cookies/recently-viewed/data
     */
    public function getRecentlyViewedData(ServerRequestInterface $request): ResponseInterface
    {
        $cafeIds = $this->recentlyViewed->getAll();

        if (empty($cafeIds)) {
            return $this->success(['cafes' => []]);
        }

        return $this->success(['cafes' => $this->cafeRepo->findByIds($cafeIds)]);
    }

    /**
     * Limpiar historial de vistos recientemente
     * DELETE /api/v1/cookies/recently-viewed
     */
    public function clearRecentlyViewed(ServerRequestInterface $request): ResponseInterface
    {
        $this->recentlyViewed->clear();

        return $this->noContent();
    }

    /**
     * Verificar si ya se mostró el popup de newsletter
     * GET /api/cookies/newsletter-prompted
     */
    public function newsletterPrompted(ServerRequestInterface $request): ResponseInterface
    {
        $prompted = isset($_COOKIE['newsletter_prompted']) && $_COOKIE['newsletter_prompted'] === '1';

        return $this->success(['prompted' => $prompted]);
    }

    /**
     * Marcar que se mostró el popup de newsletter
     * POST /api/cookies/newsletter-prompted
     */
    public function markNewsletterPrompted(ServerRequestInterface $request): ResponseInterface
    {
        $body = $request->getParsedBody();
        $permanent = !empty($body['permanent']);
        $expire = $permanent ? \time() + 60 * 60 * 24 * 365 * 2 : \time() + 60 * 60 * 24 * 180;
        \setcookie('newsletter_prompted', '1', $expire, '/');

        return $this->success(['message' => 'Preferencia registrada']);
    }
}
