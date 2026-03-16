<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntegrationRequestNonce extends Model
{
    protected $table = 'integration_request_nonces';

    protected $fillable = [
        'integration_id',
        'nonce',
        'request_signature_hash',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    // ── Relaciones ────────────────────────────────────────────────────────────

    public function integration()
    {
        return $this->belongsTo(EmpresaIntegration::class, 'integration_id');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    /**
     * Solo nonces que aún no han expirado.
     */
    public function scopeValid(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('expires_at', '>', now());
    }

    /**
     * Solo nonces ya expirados (útil para la tarea de purga programada).
     */
    public function scopeExpired(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('expires_at', '<=', now());
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
