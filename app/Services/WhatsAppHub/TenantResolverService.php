<?php

namespace App\Services\WhatsAppHub;

use App\Exceptions\WhatsAppHub\InvalidSiteKeyException;
use App\Models\Empresa;
use App\Models\FormForwardingRule;
use App\Services\WhatsAppHub\TenantContext;

/**
 * Resuelve el tenant (Empresa) a partir de un site_key público.
 *
 * Responsabilidades:
 *  - Buscar la regla de reenvío activa para el site_key recibido.
 *  - Cargar la empresa relacionada con sus servicios.
 *  - Lanzar InvalidSiteKeyException en cualquier condición de fallo.
 *
 * Se inyecta en controladores, jobs y comandos Artisan.
 * No valida acceso a módulos — eso lo hace ModuleAccessService.
 */
class TenantResolverService
{
    /**
     * Resolución completa: regla + empresa + servicios.
     *
     * @throws InvalidSiteKeyException
     */
    public function resolveFromSiteKey(string $siteKey): TenantContext
    {
        $rule = FormForwardingRule::where('site_key', $siteKey)
            ->where('is_active', true)
            ->first();

        if (! $rule) {
            throw new InvalidSiteKeyException($siteKey);
        }

        /** @var Empresa $empresa */
        $empresa = Empresa::with([
                'servicios',
                'whatsappSetting',
            ])
            ->where('id', $rule->empresa_id)
            ->where('active', true)
            ->first();

        if (! $empresa) {
            // La empresa existe pero está inactiva o fue eliminada
            throw new InvalidSiteKeyException($siteKey);
        }

        return new TenantContext($empresa, $rule);
    }
}
