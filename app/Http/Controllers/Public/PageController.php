<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Core\Logger;
use App\Core\View;

/**
 * Controlador de Páginas Estáticas
 *
 * Carga páginas de contenido estático (historia, faq, contacto, legal)
 * desde archivos con whitelisting y validación de seguridad.
 */
final class PageController
{
    /** @var array<string> Páginas permitidas (whitelist estricta) */
    private const ALLOWED_PAGES = ['historia', 'faq', 'contacto', 'legal'];

    /**
     * GET /historia
     * Página de Historia y Cultura
     */
    public function historia(): void
    {
        $this->view('historia');
    }

    /**
     * GET /faq
     * Página de Preguntas Frecuentes
     */
    public function faq(): void
    {
        $this->view('faq');
    }

    /**
     * GET /contacto
     * Página de Contacto
     */
    public function contacto(): void
    {
        $this->view('contacto');
    }

    /**
     * Carga una página estática basada en su slug.
     *
     * @param string $page Slug de la página
     */
    private function view(string $page): void
    {
        // Validar contra whitelist con strict comparison
        if (!\in_array($page, self::ALLOWED_PAGES, true)) {
            Logger::error("Security Warning: Intento de acceso a página no permitida: $page", ['page' => $page]);
            if (!\headers_sent()) {
                @\http_response_code(404);
            } else {
                Logger::error('[PageController::view] headers already sent; skipping http_response_code(404)', ['page' => $page]);
            }
            View::render('errors/404');

            return;
        }

        // Ruta segura del archivo de contenido (Content está en app/, no en Controllers/)
        $contentFile = \dirname(__DIR__, 3) . "/Content/$page.php";

        if (!\file_exists($contentFile)) {
            if (!\headers_sent()) {
                @\http_response_code(404);
            } else {
                Logger::error('[PageController::view] headers already sent; skipping http_response_code(404)', ['page' => $page]);
            }
            View::render('errors/404');

            return;
        }

        // Cargar datos del archivo de contenido
        $data = include $contentFile;

        // Validar que el archivo retorne array válido
        if (!\is_array($data) || !isset($data['titulo'])) {
            Logger::error("Error: Content file $page.php no retornó data válida", ['page' => $page]);
            if (!\headers_sent()) {
                @\http_response_code(500);
            } else {
                Logger::error('[PageController::view] headers already sent; skipping http_response_code(500)', ['page' => $page]);
            }
            View::render('errors/500');

            return;
        }

        // Renderizar página con datos seguros (usando CSS centralizado)
        View::render('public/pages/' . $page, [
            'titulo' => $data['titulo'],
            'datos' => $data,
        ], ['static-pages.css']);
    }
}
