<?php

namespace App\Services\IntegrationSecurity;

use App\Exceptions\IntegrationSecurity\ScopeDeniedException;
use App\Models\EmpresaIntegration;
use App\Support\IntegrationSecurity\ModuleRegistry;

/**
 * Valida que una integración posee el scope técnico requerido
 * para acceder a un módulo concreto.
 *
 * Responsabilidades:
 *   1. Resolver el scope requerido a partir de la clave de módulo
 *      usando ModuleRegistry (fuente única: config/integration_security.php).
 *   2. Verificar que EmpresaIntegration::$scopes_json contiene ese scope
 *      o el wildcard '*'.
 *   3. Lanzar ScopeDeniedException si no está autorizado.
 *
 * Lo que NO hace:
 *   - No valida si la empresa tiene el servicio de negocio contratado
 *     (responsabilidad de BusinessModuleAccessService).
 *   - No resuelve ni valida la integración en sí (IntegrationCredentialResolver).
 *
 * Uso típico:
 *   app(IntegrationAccessService::class)->authorize($integration, IntegrationModule::MODULE1_FORM_INGRESS);
 */
class IntegrationAccessService
{
    /** @var ModuleRegistry */
    private $registry;

    public function __construct(ModuleRegistry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * Verifica el scope y lanza ScopeDeniedException si está denegado.
     *
     * @throws ScopeDeniedException
     * @throws \InvalidArgumentException si el moduleKey no está registrado en config.
     */
    public function authorize(EmpresaIntegration $integration, string $moduleKey): void
    {
        if (! $this->can($integration, $moduleKey)) {
            $requiredScope = $this->registry->scopeFor($moduleKey);
            throw new ScopeDeniedException($integration, $requiredScope, $moduleKey);
        }
    }

    /**
     * Verifica el scope sin lanzar excepción.
     * Útil para comprobaciones condicionales o UI.
     *
     * @throws \InvalidArgumentException si el moduleKey no está registrado en config.
     */
    public function can(EmpresaIntegration $integration, string $moduleKey): bool
    {
        $requiredScope = $this->registry->scopeFor($moduleKey);

        // hasScope() en EmpresaIntegration ya maneja el wildcard '*'.
        return $integration->hasScope($requiredScope);
    }

    /**
     * Devuelve los scopes que la integración posee de entre los módulos registrados.
     * Útil para logs de diagnóstico.
     *
     * @return string[]  lista de moduleKeys accesibles
     */
    public function grantedModules(EmpresaIntegration $integration): array
    {
        $granted = [];

        foreach ($this->registry->all() as $moduleKey => $definition) {
            $scope = $definition['scope'] ?? '';
            if ($scope !== '' && $integration->hasScope($scope)) {
                $granted[] = $moduleKey;
            }
        }

        return $granted;
    }
}
