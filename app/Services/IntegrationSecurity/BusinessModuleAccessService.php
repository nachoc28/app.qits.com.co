<?php

namespace App\Services\IntegrationSecurity;

use App\Exceptions\IntegrationSecurity\BusinessServiceDeniedException;
use App\Models\Empresa;
use App\Models\EmpresaIntegration;
use App\Support\IntegrationSecurity\ModuleRegistry;

/**
 * Valida que la Empresa propietaria de una integración tiene activo el
 * servicio de catálogo requerido por el módulo solicitado.
 *
 * Responsabilidades:
 *   1. Resolver el servicio requerido (id + slug) a partir de la clave de módulo
 *      usando ModuleRegistry (fuente única: config/integration_security.php).
 *   2. Comprobar que la Empresa tiene ese servicio activo:
 *      - Primero por id (consulta más rápida con índice entero).
 *      - Luego por slug como fallback (útil si los ids difieren entre entornos).
 *   3. Verificar que la Empresa misma está activa.
 *   4. Lanzar BusinessServiceDeniedException cuando no está autorizada.
 *
 * Lo que NO hace:
 *   - No valida el scope técnico de la integración (IntegrationAccessService).
 *   - No resuelve ni valida credenciales (IntegrationCredentialResolver).
 *
 * Uso típico — pasando la integración (eager-loads empresa si es necesario):
 *   app(BusinessModuleAccessService::class)->authorize($integration, IntegrationModule::MODULE1_FORM_INGRESS);
 *
 * Uso directo con modelo Empresa:
 *   app(BusinessModuleAccessService::class)->authorizeEmpresa($empresa, IntegrationModule::MODULE1_FORM_INGRESS);
 */
class BusinessModuleAccessService
{
    /** @var ModuleRegistry */
    private $registry;

    public function __construct(ModuleRegistry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * Autoriza usando la EmpresaIntegration: carga la empresa relacionada y delega.
     *
     * @throws BusinessServiceDeniedException
     * @throws \InvalidArgumentException si el moduleKey no está registrado en config.
     */
    public function authorize(EmpresaIntegration $integration, string $moduleKey): void
    {
        // Carga la empresa evitando N+1: si ya está cargada en el objeto use la caché.
        $empresa = $integration->relationLoaded('empresa')
            ? $integration->empresa
            : $integration->load('empresa')->empresa;

        $this->authorizeEmpresa($empresa, $moduleKey);
    }

    /**
     * Autoriza directamente a partir de un modelo Empresa.
     *
     * @throws BusinessServiceDeniedException
     * @throws \InvalidArgumentException si el moduleKey no está registrado en config.
     */
    public function authorizeEmpresa(Empresa $empresa, string $moduleKey): void
    {
        if (! $this->canEmpresa($empresa, $moduleKey)) {
            throw new BusinessServiceDeniedException(
                $empresa,
                $moduleKey,
                $this->registry->requiredServiceIdFor($moduleKey),
                $this->registry->requiredServiceSlugFor($moduleKey)
            );
        }
    }

    /**
     * Verifica sin lanzar excepción.
     * Carga la empresa si no está en el estado de la relación.
     *
     * @throws \InvalidArgumentException si el moduleKey no está registrado en config.
     */
    public function can(EmpresaIntegration $integration, string $moduleKey): bool
    {
        $empresa = $integration->relationLoaded('empresa')
            ? $integration->empresa
            : $integration->load('empresa')->empresa;

        return $this->canEmpresa($empresa, $moduleKey);
    }

    /**
     * Verifica directamente a partir de un modelo Empresa, sin lanzar excepción.
     *
     * @throws \InvalidArgumentException si el moduleKey no está registrado en config.
     */
    public function canEmpresa(Empresa $empresa, string $moduleKey): bool
    {
        // Una empresa inactiva no puede usar ningún módulo.
        if (! $empresa->active) {
            return false;
        }

        $serviceId   = $this->registry->requiredServiceIdFor($moduleKey);
        $serviceSlug = $this->registry->requiredServiceSlugFor($moduleKey);

        // Validar primero por id (índice entero, rápido).
        // El slug sirve de fallback por si los ids difieren entre entornos (dev vs prod).
        return $empresa->hasActiveService($serviceId)
            || $empresa->hasActiveServiceBySlug($serviceSlug);
    }
}
