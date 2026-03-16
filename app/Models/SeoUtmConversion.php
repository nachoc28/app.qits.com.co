<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeoUtmConversion extends Model
{
    protected $table = 'seo_utm_conversions';

    protected $fillable = [
        'empresa_id',
        'conversion_datetime',
        'page_url',
        'form_name',
        'source',
        'medium',
        'campaign',
        'term',
        'content',
        'event_name',
        'lead_id',
        'raw_payload_json',
    ];

    protected $casts = [
        'empresa_id' => 'integer',
        'conversion_datetime' => 'datetime',
        'lead_id' => 'integer',
        'raw_payload_json' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }
}
