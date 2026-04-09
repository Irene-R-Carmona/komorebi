<?php

declare(strict_types=1);

namespace App\Core\Http;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Factory para crear ServerRequest PSR-7 desde superglobals.
 *
 * Encapsula la creación de requests PSR-7 eliminando la dependencia
 * directa de superglobals ($_GET, $_POST, $_SERVER, etc.).
 */
final class RequestFactory
{
    /**
     * Crea un ServerRequest PSR-7 desde superglobals PHP.
     *
     * @return ServerRequestInterface Request PSR-7 inmutable
     */
    public static function fromGlobals(): ServerRequestInterface
    {
        $psr17Factory = new Psr17Factory();

        $creator = new ServerRequestCreator(
            $psr17Factory, // ServerRequestFactory
            $psr17Factory, // UriFactory
            $psr17Factory, // UploadedFileFactory
            $psr17Factory  // StreamFactory
        );

        $request = $creator->fromGlobals();

        // Nyholm/psr7-server solo parsea automáticamente JSON.
        // Para formularios HTML (application/x-www-form-urlencoded y multipart/form-data),
        // necesitamos parsear $_POST manualmente.
        if (!empty($_POST)) {
            $request = $request->withParsedBody($_POST);
        }

        return $request;
    }
}
