<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;

class Empresa extends Model
{
    use SoftDeletes;

    protected $table = 'empresas';

    protected $fillable = [
        'nit', 'nombre', 'direccion', 'ciudad_id', 'telefono', 'email', 'logo', 'active', 'is_internal'
    ];

    protected $casts = [
        'ciudad_id' => 'integer',
        'email'     => 'string',
        'active' => 'boolean',
        'is_internal' => 'boolean',
        'deleted_at'  => 'datetime',
    ];

    /** Relaciones */
    public function ciudad()
    {
        return $this->belongsTo(Ciudad::class);
    }

    public function usuarios()
    {
        return $this->hasMany(User::class, 'empresa_id');
    }

    public function proyectos()
    {
        return $this->hasMany(ProyectoEmpresa::class);
    }

    public function servicios()
    {
        return $this->belongsToMany(Servicio::class, 'empresa_servicio')
                    ->withTimestamps();
    }

    // ── Módulo WhatsApp Hub ───────────────────────────────────────────────────

    public function whatsappSetting()
    {
        return $this->hasOne(EmpresaWhatsAppSetting::class);
    }

    public function formForwardingRules()
    {
        return $this->hasMany(FormForwardingRule::class);
    }

    public function leadSources()
    {
        return $this->hasMany(LeadSource::class);
    }

    public function waLeads()
    {
        return $this->hasMany(WaLead::class);
    }

    public function outboundMessages()
    {
        return $this->hasMany(OutboundMessage::class);
    }

    /**
     * Verifica si la empresa tiene activo un servicio del catálogo por su id.
     * Ejemplo: $empresa->hasActiveService(1)  → Módulo 1 (Sitio Web / formularios)
     */
    public function hasActiveService(int $serviceId): bool
    {
        return $this->servicios()
            ->where('servicios.id', $serviceId)
            ->where('servicios.activo', true)
            ->exists();
    }

    /**
     * Verifica si la empresa tiene activo un servicio por su slug.
     * Permite desacoplar la validación del id numérico en fases futuras.
     */
    public function hasActiveServiceBySlug(string $slug): bool
    {
        return $this->servicios()
            ->where('servicios.slug', $slug)
            ->where('servicios.activo', true)
            ->exists();
    }

    // ── Capa de seguridad de integraciones externas ───────────────────────────

    public function integrations()
    {
        return $this->hasMany(EmpresaIntegration::class);
    }

    public function integrationSecurityLogs()
    {
        return $this->hasMany(IntegrationSecurityLog::class);
    }

    // ── Módulo SEO ────────────────────────────────────────────────────────────

    public function seoProperty()
    {
        return $this->hasOne(EmpresaSeoProperty::class);
    }

    public function seoGscDailyMetrics()
    {
        return $this->hasMany(SeoGscDailyMetric::class);
    }

    public function seoGscQueries()
    {
        return $this->hasMany(SeoGscQuery::class);
    }

    public function seoGscPages()
    {
        return $this->hasMany(SeoGscPage::class);
    }

    public function seoGa4DailyMetrics()
    {
        return $this->hasMany(SeoGa4DailyMetric::class);
    }

    public function seoGa4LandingPages()
    {
        return $this->hasMany(SeoGa4LandingPage::class);
    }

    public function seoUtmConversions()
    {
        return $this->hasMany(SeoUtmConversion::class);
    }

    /** Scopes */
    public function scopeNombreLike(Builder $q, string $term): Builder
    {
        return $q->where('nombre', 'like', "%{$term}%");
    }
}
