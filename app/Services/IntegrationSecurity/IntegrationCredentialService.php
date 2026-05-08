<?php

namespace App\Services\IntegrationSecurity;

use App\Models\Empresa;
use App\Models\EmpresaIntegration;
use App\Services\IntegrationSecurity\IntegrationCredentialIssueResult;
use App\Support\IntegrationSecurity\IntegrationCredentialGenerator;
use App\Support\IntegrationSecurity\IntegrationModule;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
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

    /**
     * Crea una integración preconfigurada para el plugin WordPress UTM.
     */
    public function createWordpressUtm(Empresa $empresa): IntegrationCredentialIssueResult
    {
        $issued = $this->create($empresa, [
            'name' => 'WordPress UTM Tracker',
            'provider_type' => 'wordpress',
            'status' => 'active',
            'scopes_json' => [IntegrationModule::SEO_UTM_CONVERSIONS_INGEST],
            'rate_limit_profile' => 'normal',
        ]);

        $this->ensureWordpressIntegrationScopes($issued->integration, $empresa);

        return new IntegrationCredentialIssueResult($issued->integration->fresh(), $issued->plainSecret);
    }

    /**
     * Asegura los scopes esperados para integraciones WordPress/QITS.
     *
     * Reglas:
     * - Siempre conservar/agregar seo.utm_conversions_ingest.
     * - Agregar wordpress.form_notifications_ingest solo si la empresa tiene
     *   activo el servicio formularios-whatsapp-api.
     * - No elimina scopes existentes ni remueve scopes cuando el servicio se desactiva.
     * - Persiste solo si hubo cambios efectivos.
     */
    public function ensureWordpressIntegrationScopes(EmpresaIntegration $integration, ?Empresa $empresa = null): EmpresaIntegration
    {
        $empresa = $empresa ?: ($integration->relationLoaded('empresa')
            ? $integration->empresa
            : $integration->load('empresa')->empresa);

        $currentScopes = $this->normalizeScopes($integration->scopes_json);
        $newScopes = $currentScopes;

        if (! in_array(IntegrationModule::SEO_UTM_CONVERSIONS_INGEST, $newScopes, true)) {
            $newScopes[] = IntegrationModule::SEO_UTM_CONVERSIONS_INGEST;
        }

        $serviceSlug = 'formularios-whatsapp-api';
        $serviceActive = $empresa ? $empresa->hasActiveServiceBySlug($serviceSlug) : false;

        if ($serviceActive && ! in_array('wordpress.form_notifications_ingest', $newScopes, true)) {
            $newScopes[] = 'wordpress.form_notifications_ingest';

            Log::info('[IntegrationCredentialService] Scope auto-granted for WordPress integration.', [
                'integration_id' => $integration->id,
                'empresa_id' => $integration->empresa_id,
                'scope' => 'wordpress.form_notifications_ingest',
                'service_slug' => $serviceSlug,
            ]);
        }

        $newScopes = array_values(array_unique($newScopes));

        if ($newScopes !== $currentScopes) {
            $integration->forceFill([
                'scopes_json' => $newScopes,
            ])->save();

            return $integration->fresh();
        }

        return $integration;
    }

    /**
     * Normaliza scopes desde scopes_json a arreglo seguro de strings únicos.
     *
     * @param mixed $rawScopes
     * @return string[]
     */
    private function normalizeScopes($rawScopes): array
    {
        if (is_array($rawScopes)) {
            $scopes = $rawScopes;
        } elseif (is_string($rawScopes) && $rawScopes !== '') {
            $decoded = json_decode($rawScopes, true);
            $scopes = is_array($decoded) ? $decoded : [];
        } else {
            $scopes = [];
        }

        $scopes = array_map(function ($scope) {
            return trim((string) $scope);
        }, $scopes);

        $scopes = array_filter($scopes, function ($scope) {
            return $scope !== '';
        });

        return array_values(array_unique($scopes));
    }

    /**
     * Garantiza que la integración tenga un scope específico sin duplicados.
     */
    public function ensureScope(EmpresaIntegration $integration, string $scope): EmpresaIntegration
    {
        $currentScopes = $integration->scopes_json ?? [];

        if (! is_array($currentScopes)) {
            $currentScopes = [];
        }

        if (! in_array($scope, $currentScopes, true)) {
            $currentScopes[] = $scope;
            $integration->forceFill([
                'scopes_json' => array_values(array_unique($currentScopes)),
            ])->save();
        }

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
