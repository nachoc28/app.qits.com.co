<?php

namespace App\Services\IntegrationSecurity;

use App\Models\EmpresaIntegration;

/**
 * Resultado de emisión o rotación de credenciales.
 */
class IntegrationCredentialIssueResult
{
    public EmpresaIntegration $integration;
    public string $plainSecret;

    public function __construct(EmpresaIntegration $integration, string $plainSecret)
    {
        $this->integration = $integration;
        $this->plainSecret = $plainSecret;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'integration_id' => $this->integration->id,
            'empresa_id' => $this->integration->empresa_id,
            'public_key' => $this->integration->public_key,
            'status' => $this->integration->status,
            'plain_secret' => $this->plainSecret,
        ];
    }
}
