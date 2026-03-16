<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\IntegrationRequestNonce;
use App\Models\IntegrationSecurityLog;

class EmpresaIntegration extends Model
{
    protected $table = 'empresa_integrations';

    protected $fillable = [
        'empresa_id',
        'name',
        'provider_type',
        'public_key',
        'secret_hash',
        'status',
        'allowed_domains_json',
        'allowed_ips_json',
        'scopes_json',
        'rate_limit_profile',
        'last_used_at',
        'last_used_ip',
        'meta_json',
    ];

    /**
     * secret_hash nunca debe exponerse en respuestas JSON ni en logs.
     * Se almacena como hash unidireccional (no reversible).
     */
    protected $hidden = [
        'secret_hash',
    ];

    protected $casts = [
        'allowed_domains_json' => 'array',
        'allowed_ips_json'     => 'array',
        'scopes_json'          => 'array',
        'meta_json'            => 'array',
        'last_used_at'         => 'datetime',
    ];

    // ── Relaciones ────────────────────────────────────────────────────────────

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    public function nonces()
    {
        return $this->hasMany(IntegrationRequestNonce::class, 'integration_id');
    }

    public function securityLogs()
    {
        return $this->hasMany(IntegrationSecurityLog::class, 'integration_id');
    }

    // ── Helpers de dominio ────────────────────────────────────────────────────

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function hasScope(string $scope): bool
    {
        $scopes = $this->scopes_json ?? [];

        return in_array($scope, $scopes, true) || in_array('*', $scopes, true);
    }
}
