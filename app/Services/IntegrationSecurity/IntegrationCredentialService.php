<?php

namespace App\Services\IntegrationSecurity;

use App\Models\Empresa;
use App\Models\EmpresaIntegration;
use App\Services\IntegrationSecurity\IntegrationCredentialIssueResult;
use App\Support\IntegrationSecurity\IntegrationCredentialGenerator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Ciclo de vida de credenciales de integración.
 *
 * Operaciones:
 *  - creación de integración con public key + secret
 *  - rotación de secreto
 *  - activación/desactivación/revocación
 *
 * Seguridad:
 *  - El secreto plano solo se devuelve en create/rotate.
 *  - En BD se almacena únicamente su hash.
 */
class IntegrationCredentialService
{
    private IntegrationCredentialGenerator $generator;

    public function __construct(IntegrationCredentialGenerator $generator)
    {
        $this->generator = $generator;
    }

    /**
     * Crea una integración nueva para una empresa.
     *
     * @param array<string, mixed> $data
     */
    public function create(Empresa $empresa, array $data): IntegrationCredentialIssueResult
    {
        return DB::transaction(function () use ($empresa, $data) {
            $plainSecret = $this->generator->generateSecret();
            $secretHash = $this->generator->hashSecret($plainSecret);

            $integration = EmpresaIntegration::create([
                'empresa_id' => $empresa->id,
                'name' => (string) Arr::get($data, 'name', 'External Integration'),
                'provider_type' => (string) Arr::get($data, 'provider_type', 'generic'),
                'public_key' => $this->generateUniquePublicKey(),
                'secret_hash' => $secretHash,
                'status' => (string) Arr::get($data, 'status', 'active'),
                'allowed_domains_json' => Arr::get($data, 'allowed_domains_json'),
                'allowed_ips_json' => Arr::get($data, 'allowed_ips_json'),
                'scopes_json' => Arr::get($data, 'scopes_json', []),
                'rate_limit_profile' => Arr::get($data, 'rate_limit_profile', 'normal'),
                'meta_json' => Arr::get($data, 'meta_json'),
            ]);

            return new IntegrationCredentialIssueResult($integration, $plainSecret);
        });
    }

    /**
     * Rota el secreto de una integración existente.
     */
    public function rotateSecret(EmpresaIntegration $integration): IntegrationCredentialIssueResult
    {
        $plainSecret = $this->generator->generateSecret();
        $secretHash = $this->generator->hashSecret($plainSecret);

        $integration->forceFill([
            'secret_hash' => $secretHash,
            'status' => $integration->status === 'revoked' ? 'active' : $integration->status,
        ])->save();

        return new IntegrationCredentialIssueResult($integration->fresh(), $plainSecret);
    }

    /**
     * Revoca permanentemente una integración.
     */
    public function revoke(EmpresaIntegration $integration): EmpresaIntegration
    {
        $integration->forceFill(['status' => 'revoked'])->save();

        return $integration->fresh();
    }

    /**
     * Desactiva temporalmente una integración.
     */
    public function deactivate(EmpresaIntegration $integration): EmpresaIntegration
    {
        $integration->forceFill(['status' => 'suspended'])->save();

        return $integration->fresh();
    }

    /**
     * Activa una integración.
     */
    public function activate(EmpresaIntegration $integration): EmpresaIntegration
    {
        $integration->forceFill(['status' => 'active'])->save();

        return $integration->fresh();
    }

    private function generateUniquePublicKey(): string
    {
        do {
            $key = $this->generator->generatePublicKey();
        } while (EmpresaIntegration::query()->where('public_key', $key)->exists());

        return $key;
    }
}
