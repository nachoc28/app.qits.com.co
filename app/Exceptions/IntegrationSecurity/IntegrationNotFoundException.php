<?php

namespace App\Exceptions\IntegrationSecurity;

use RuntimeException;

/**
 * Se lanza cuando el public_key recibido no corresponde a ninguna
 * integración registrada en empresa_integrations.
 *
 * Intencionalmente no revela si la clave existe pero está inactiva
 * para evitar enumeración de credenciales.
 * El Handler la mapea a HTTP 401.
 */
class IntegrationNotFoundException extends RuntimeException
{
    private string $publicKey;

    public function __construct(string $publicKey)
    {
        $this->publicKey = $publicKey;

        parent::__construct('Integration key not recognized or not found.');
    }

    public function getPublicKey(): string
    {
        return $this->publicKey;
    }
}
