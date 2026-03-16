<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmpresaSeoProperty extends Model
{
    protected $table = 'empresa_seo_properties';

    protected $fillable = [
        'empresa_id',
        'site_url',
        'search_console_property',
        'ga4_property_id',
        'wordpress_site_url',
        'utm_tracking_enabled',
        'gsc_enabled',
        'ga4_enabled',
        'last_gsc_sync_at',
        'last_ga4_sync_at',
        'last_utm_sync_at',
    ];

    protected $casts = [
        'empresa_id' => 'integer',
        'utm_tracking_enabled' => 'boolean',
        'gsc_enabled' => 'boolean',
        'ga4_enabled' => 'boolean',
        'last_gsc_sync_at' => 'datetime',
        'last_ga4_sync_at' => 'datetime',
        'last_utm_sync_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    // ── Estado de preparación por fuente ──────────────────────────────────────

    /** GSC habilitado y con search_console_property configurado. */
    public function isGscReady(): bool
    {
        return (bool) $this->gsc_enabled
            && ! empty($this->search_console_property);
    }

    /** GA4 habilitado y con ga4_property_id configurado. */
    public function isGa4Ready(): bool
    {
        return (bool) $this->ga4_enabled
            && ! empty($this->ga4_property_id);
    }

    /** UTM tracking habilitado y con wordpress_site_url configurado. */
    public function isUtmReady(): bool
    {
        return (bool) $this->utm_tracking_enabled
            && ! empty($this->wordpress_site_url);
    }

    // ── Actualización de timestamps de sincronización ─────────────────────────

    /** Registra la fecha+hora de la última sincronización exitosa de GSC. */
    public function markGscSynced(): void
    {
        $this->last_gsc_sync_at = now();
        $this->save();
    }

    /** Registra la fecha+hora de la última sincronización exitosa de GA4. */
    public function markGa4Synced(): void
    {
        $this->last_ga4_sync_at = now();
        $this->save();
    }

    /** Registra la fecha+hora del último ingreso exitoso de UTM conversions. */
    public function markUtmSynced(): void
    {
        $this->last_utm_sync_at = now();
        $this->save();
    }
}
