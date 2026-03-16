<?php

namespace App\Exceptions\IntegrationSecurity;

use RuntimeException;
use App\Models\Empresa;

/**
 * Se lanza cuando la Empresa propietaria de la integración no tiene
 * activo el servicio de catálogo requerido por el módulo solicitado.
 *
 * Diferencia con ScopeDeniedException:
 *   - ScopeDeniedException → la integración no tiene el permiso técnico.
 *   - BusinessServiceDeniedException → la empresa no tiene contratado el servicio.
 *
 * El Handler la mapea a HTTP 403.
 */
class BusinessServiceDeniedException extends RuntimeException
{
    private Empresa $empresa;
    private string $moduleKey;
    private int $requiredServiceId;
    private string $requiredServiceSlug;

    public function __construct(
        Empresa $empresa,
        string $moduleKey,
        int $requiredServiceId,
        string $requiredServiceSlug
    ) {
        $this->empresa             = $empresa;
        $this->moduleKey           = $moduleKey;
        $this->requiredServiceId   = $requiredServiceId;
        $this->requiredServiceSlug = $requiredServiceSlug;

        parent::__construct(
            "Empresa [{$empresa->nombre}] does not have the required service"
            . " [id={$requiredServiceId}, slug={$requiredServiceSlug}]"
            . " for module [{$moduleKey}]."
        );
    }

    public function getEmpresa(): Empresa
    {
        return $this->empresa;
    }

    public function getModuleKey(): string
    {
        return $this->moduleKey;
    }

    public function getRequiredServiceId(): int
    {
        return $this->requiredServiceId;
    }

    public function getRequiredServiceSlug(): string
    {
        return $this->requiredServiceSlug;
    }
}
