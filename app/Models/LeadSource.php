<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\WaLead;

class LeadSource extends Model
{
    protected $table = 'lead_sources';

    protected $fillable = [
        'empresa_id',
        'type',
        'domain',
        'page_url',
        'campaign',
        'medium',
        'utm_source',
        'utm_campaign',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    public function leads()
    {
        return $this->hasMany(WaLead::class, 'source_id');
    }
}
