<?php

namespace App\Exceptions\IntegrationSecurity;

use RuntimeException;
use App\Models\EmpresaIntegration;

/**
 * Se lanza cuando la integración existe pero su status no es 'active'
 * (puede ser 'suspended' o 'revoked').
 *
 * El Handler la mapea a HTTP 403 para distinguirla de credenciales inválidas.
 */
class IntegrationInactiveException extends RuntimeException
{
    private EmpresaIntegration $integration;

    public function __construct(EmpresaIntegration $integration)
    {
        $this->integration = $integration;

        parent::__construct(
            "Integration [{$integration->public_key}] is {$integration->status} and cannot be used."
        );
    }

    public function getIntegration(): EmpresaIntegration
    {
        return $this->integration;
    }
}
