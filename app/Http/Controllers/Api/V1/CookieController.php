<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Core\CookieManager;
use App\Core\Http\ResponseFactory;
use App\Http\Controllers\Api\AbstractApiController;
use App\Models\Cafe;
use App\Services\RecentlyViewedService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class CookieController extends AbstractApiController
{
    public function __construct(ResponseFactory $response)
    {
        parent::__construct($response);
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
     * POST /api/cookies/clear-filters
     */
    public function clearFilters(ServerRequestInterface $request): ResponseInterface
    {
        CookieManager::delete(CookieManager::FILTER_PREFERENCES);

        return $this->success(['message' => 'Filtros eliminados']);
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

        $service = new RecentlyViewedService();
        $result = $service->add((int) $input['cafeId']);

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
        $service = new RecentlyViewedService();

        return $this->success([
            'cafeIds' => $service->getAll(),
            'maxItems' => $service->getMaxItems(),
        ]);
    }

    /**
     * Obtener datos completos de cafés vistos recientemente
     * GET /api/cookies/recently-viewed/data
     */
    public function getRecentlyViewedData(ServerRequestInterface $request): ResponseInterface
    {
        $service = new RecentlyViewedService();
        $cafeIds = $service->getAll();

        if (empty($cafeIds)) {
            return $this->success(['cafes' => []]);
        }

        $cafeModel = new Cafe();

        return $this->success(['cafes' => $cafeModel->findByIds($cafeIds)]);
    }

    /**
     * Limpiar historial de vistos recientemente
     * DELETE /api/cookies/recently-viewed/clear
     */
    public function clearRecentlyViewed(ServerRequestInterface $request): ResponseInterface
    {
        $service = new RecentlyViewedService();
        $result = $service->clear();

        return $this->success([
            'cleared' => $result,
            'message' => $result ? 'Historial eliminado' : 'Error al eliminar',
        ]);
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
