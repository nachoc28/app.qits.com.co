<?php

namespace App\Exceptions\IntegrationSecurity;

use RuntimeException;
use App\Models\EmpresaIntegration;

/**
 * Se lanza cuando la integración no posee el scope técnico requerido
 * para acceder al módulo solicitado.
 *
 * Diferencia con IntegrationInactiveException:
 *   - La integración está activa, pero su grant de scopes no incluye este módulo.
 *
 * El Handler la mapea a HTTP 403.
 */
class ScopeDeniedException extends RuntimeException
{
    private EmpresaIntegration $integration;
    private string $requiredScope;
    private string $moduleKey;

    public function __construct(EmpresaIntegration $integration, string $requiredScope, string $moduleKey)
    {
        $this->integration   = $integration;
        $this->requiredScope = $requiredScope;
        $this->moduleKey     = $moduleKey;

        parent::__construct(
            "Integration [{$integration->public_key}] does not have the required scope"
            . " [{$requiredScope}] for module [{$moduleKey}]."
        );
    }

    public function getIntegration(): EmpresaIntegration
    {
        return $this->integration;
    }

    public function getRequiredScope(): string
    {
        return $this->requiredScope;
    }

    public function getModuleKey(): string
    {
        return $this->moduleKey;
    }
}
