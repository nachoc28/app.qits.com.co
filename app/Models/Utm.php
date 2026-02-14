<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Utm extends Model
{
    // La tabla se llama 'utm' (singular)
    protected $table = 'utm';

    protected $fillable = [
        'proyecto_empresa_id',
        'fecha', 'event_type', 'visitor_guid',
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
        'ip_address', 'landing_url', 'extra_data',
    ];

    protected $casts = [
        'proyecto_empresa_id' => 'integer',
        'fecha'               => 'date',
        'extra_data'          => 'array',   // JSON
        'created_at'          => 'datetime',
        'updated_at'          => 'datetime',
    ];

    public function proyectoEmpresa()
    {
        return $this->belongsTo(ProyectoEmpresa::class);
    }
}
