<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntegrationSecurityLog extends Model
{
    protected $table = 'integration_security_logs';

    /**
     * Los logs son inmutables por diseño: se crean, no se editan.
     * El array fillable limita la asignación masiva.
     */
    protected $fillable = [
        'integration_id',
        'empresa_id',
        'event_type',
        'ip_address',
        'domain',
        'endpoint',
        'http_method',
        'status',
        'reason_code',
        'payload_fingerprint',
        'meta_json',
    ];

    protected $casts = [
        'meta_json' => 'array',
    ];

    // ── Relaciones ────────────────────────────────────────────────────────────

    /**
     * Puede ser null si la integración fue eliminada después del evento.
     */
    public function integration()
    {
        return $this->belongsTo(EmpresaIntegration::class, 'integration_id');
    }

    /**
     * Puede ser null si la empresa fue eliminada después del evento.
     */
    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeAllowed(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('status', 'allowed');
    }

    public function scopeDenied(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('status', 'denied');
    }

    public function scopeForIntegration(\Illuminate\Database\Eloquent\Builder $query, int $integrationId): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('integration_id', $integrationId);
    }

    public function scopeOfType(\Illuminate\Database\Eloquent\Builder $query, string $eventType): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('event_type', $eventType);
    }
}
