<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeadEvent extends Model
{
    protected $table = 'lead_events';

    /**
     * Valores de referencia para `event_type`:
     *   received | forwarding_started | text_sent | pdf_generated |
     *   pdf_sent | whatsapp_error | archived
     */
    protected $fillable = [
        'lead_id',
        'event_type',
        'payload_json',
    ];

    protected $casts = [
        'payload_json' => 'array',
    ];

    public function lead()
    {
        return $this->belongsTo(WaLead::class, 'lead_id');
    }
}
