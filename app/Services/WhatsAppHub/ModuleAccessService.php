<?php

namespace App\Services\WhatsAppHub;

use App\Exceptions\WhatsAppHub\ModuleAccessDeniedException;
use App\Models\Empresa;

/**
 * Centraliza las reglas de negocio que determinan si una empresa
 * puede acceder a cada módulo del WhatsApp Automation Hub.
 *
 * Cómo extender:
 *  - Añadir una nueva entrada en config/whatsapp_hub.php bajo 'modules'.
 *  - Crear un método canUseModuleX() que llame a canUseModule('module_x').
 *  - No tocar controladores ni jobs existentes.
 *
 * Uso típico:
 *   app(ModuleAccessService::class)->authorize($empresa, 'module_1');
 */
class ModuleAccessService
{
    /**
     * Verifica acceso Y lanza excepción si está denegado.
     *
     * @throws ModuleAccessDeniedException
     */
    public function authorize(Empresa $empresa, string $moduleKey): void
    {
        if (! $this->canUse($empresa, $moduleKey)) {
            $reason = $this->denyReason($empresa, $moduleKey);
            throw new ModuleAccessDeniedException($empresa, $moduleKey, $reason);
        }
    }

    /**
     * Retorna true/false sin lanzar excepción.
     * Útil para renderizar la UI (mostrar/ocultar botones).
     */
    public function canUse(Empresa $empresa, string $moduleKey): bool
    {
        if (! $empresa->active) {
            return false;
        }

        $config = $this->moduleConfig($moduleKey);
        if ($config === null) {
            return false;
        }

        // Validamos primero por id (más rápido); el slug sirve de fallback
        // cuando el id puede cambiar entre entornos (dev vs prod).
        return $empresa->hasActiveService($config['service_id'])
            || $empresa->hasActiveServiceBySlug($config['service_slug']);
    }

    // ── Atajos para cada módulo ───────────────────────────────────────────────

    /**
     * Módulo 1 — Reenvío de formularios vía WhatsApp API
     * Requiere servicio id=1 / slug='formularios-whatsapp-api'
     *
     * @throws ModuleAccessDeniedException
     */
    public function authorizeModule1(Empresa $empresa): void
    {
        $this->authorize($empresa, 'module_1');
    }

    public function canUseModule1(Empresa $empresa): bool
    {
        return $this->canUse($empresa, 'module_1');
    }

    /**
     * Módulos 2 y 3 — WhatsApp QITS Solution
     * Requiere servicio id=3 / slug='whatsapp-qits-solution'
     *
     * @throws ModuleAccessDeniedException
     */
    public function authorizeModule2And3(Empresa $empresa): void
    {
        $this->authorize($empresa, 'module_2_3');
    }

    public function canUseModule2And3(Empresa $empresa): bool
    {
        return $this->canUse($empresa, 'module_2_3');
    }

    // ── Helpers privados ─────────────────────────────────────────────────────

    private function moduleConfig(string $moduleKey): ?array
    {
        return config("whatsapp_hub.modules.{$moduleKey}");
    }

    private function denyReason(Empresa $empresa, string $moduleKey): string
    {
        if (! $empresa->active) {
            return 'la empresa no está activa';
        }

        $config = $this->moduleConfig($moduleKey);
        if ($config === null) {
            return "la clave de módulo [{$moduleKey}] no existe en la configuración";
        }

        return "el servicio requerido (id={$config['service_id']}, slug={$config['service_slug']}) no está activo para esta empresa";
    }
}
