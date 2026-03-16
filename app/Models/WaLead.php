<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\LeadEvent;
use App\Models\LeadDocument;
use App\Models\OutboundMessage;

class WaLead extends Model
{
    use SoftDeletes;

    protected $table = 'wa_leads';

    /**
     * Valores posibles para `status`:
     *   received | processing | forwarded | failed | archived
     */
    protected $fillable = [
        'empresa_id',
        'source_id',
        'full_name',
        'phone',
        'email',
        'company',
        'city',
        'notes',
        'origin_url',
        'origin_form_name',
        'message',
        'payload_json',
        'status',
    ];

    protected $casts = [
        'payload_json' => 'array',
        'deleted_at'   => 'datetime',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    public function source()
    {
        return $this->belongsTo(LeadSource::class, 'source_id');
    }

    public function events()
    {
        return $this->hasMany(LeadEvent::class, 'lead_id');
    }

    public function documents()
    {
        return $this->hasMany(LeadDocument::class, 'lead_id');
    }

    public function outboundMessages()
    {
        return $this->hasMany(OutboundMessage::class, 'lead_id');
    }

    public function scopeByStatus(Builder $q, string $status): Builder
    {
        return $q->where('status', $status);
    }

    public function scopeForEmpresa(Builder $q, int $empresaId): Builder
    {
        return $q->where('empresa_id', $empresaId);
    }

    // ── Scopes para dashboard ────────────────────────────────────────────────

    public function scopeFromDate(Builder $q, string $date): Builder
    {
        return $q->whereDate('created_at', '>=', $date);
    }

    public function scopeToDate(Builder $q, string $date): Builder
    {
        return $q->whereDate('created_at', '<=', $date);
    }

    public function scopeByFormName(Builder $q, string $name): Builder
    {
        return $q->where('origin_form_name', $name);
    }

    public function scopeByOriginDomain(Builder $q, string $domain): Builder
    {
        return $q->whereHas('source', function (Builder $sq) use ($domain) {
            $sq->where('domain', $domain);
        });
    }

    public function scopeBySource(Builder $q, int $sourceId): Builder
    {
        return $q->where('source_id', $sourceId);
    }
}
