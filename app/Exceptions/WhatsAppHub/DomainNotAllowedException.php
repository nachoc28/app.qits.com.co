<?php

namespace App\Exceptions\WhatsAppHub;

use RuntimeException;

/**
 * Se lanza cuando el dominio del request no coincide
 * con el allowed_domain de la FormForwardingRule.
 */
class DomainNotAllowedException extends RuntimeException
{
    public function __construct(string $origin, string $allowed)
    {
        parent::__construct(
            "Dominio de origen [{$origin}] no está permitido. Dominio esperado: [{$allowed}]"
        );
    }
}
