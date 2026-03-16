<?php

namespace App\Services\Seo;

use App\Models\Empresa;
use App\Models\EmpresaSeoProperty;

/**
 * Objeto de valor inmutable que encapsula la configuración SEO de una empresa.
 *
 * Evita pasar $empresa y $property por separado entre servicios.
 * Patrón idéntico a TenantContext del Módulo WhatsApp Hub.
 *
 * Construcción recomendada:
 *   $context = app(SeoDashboardService::class)->resolveContext($empresa);
 */
final class SeoPropertyContext
{
    /** @var Empresa */
    private $empresa;

    /** @var EmpresaSeoProperty */
    private $property;

    public function __construct(Empresa $empresa, EmpresaSeoProperty $property)
    {
        $this->empresa  = $empresa;
        $this->property = $property;
    }

    public function getEmpresa(): Empresa
    {
        return $this->empresa;
    }

    public function getProperty(): EmpresaSeoProperty
    {
        return $this->property;
    }

    public function empresaId(): int
    {
        return $this->empresa->id;
    }

    // ── Acceso a propiedades de configuración ─────────────────────────────────

    public function gscProperty(): ?string
    {
        return $this->property->search_console_property;
    }

    public function ga4PropertyId(): ?string
    {
        return $this->property->ga4_property_id;
    }

    public function wordpressSiteUrl(): ?string
    {
        return $this->property->wordpress_site_url;
    }

    // ── Estado de preparación por fuente ──────────────────────────────────────

    /** GSC habilitado y con propiedad configurada. */
    public function isGscReady(): bool
    {
        return (bool) $this->property->gsc_enabled
            && ! empty($this->property->search_console_property);
    }

    /** GA4 habilitado y con property ID configurado. */
    public function isGa4Ready(): bool
    {
        return (bool) $this->property->ga4_enabled
            && ! empty($this->property->ga4_property_id);
    }

    /** UTM tracking habilitado y con URL de WordPress configurada. */
    public function isUtmReady(): bool
    {
        return (bool) $this->property->utm_tracking_enabled
            && ! empty($this->property->wordpress_site_url);
    }
}
