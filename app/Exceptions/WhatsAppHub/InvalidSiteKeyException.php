<?php

namespace App\Exceptions\WhatsAppHub;

use RuntimeException;

/**
 * Se lanza cuando el site_key recibido no existe,
 * no está activo, o no pertenece a una empresa activa.
 */
class InvalidSiteKeyException extends RuntimeException
{
    public function __construct(string $siteKey)
    {
        parent::__construct("Site key inválida o inactiva: [{$siteKey}]");
    }
}
