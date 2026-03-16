<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class OutboundMessage extends Model
{
    protected $table = 'outbound_messages';

    /**
     * Valores de referencia para `status`:
     *   queued | sent | delivered | failed
     *
     * Valores de referencia para `message_type`:
     *   text | template | document | image
     */
    protected $fillable = [
        'empresa_id',
        'lead_id',
        'channel',
        'destination_phone',
        'message_type',
        'message_body',
        'attachment_path',
        'provider_message_id',
        'provider_response',
        'status',
        'error_message',
    ];

    protected $casts = [
        'provider_response' => 'array',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    public function lead()
    {
        return $this->belongsTo(WaLead::class, 'lead_id');
    }

    public function scopeByStatus(Builder $q, string $status): Builder
    {
        return $q->where('status', $status);
    }

    public function scopeFailed(Builder $q): Builder
    {
        return $q->where('status', 'failed');
    }
}
