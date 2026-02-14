<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    protected $table = 'tickets';

    protected $fillable = [
        'lead_id','resumen','responsable_id','tiempo_respuesta','status',
    ];

    protected $casts = [
        'lead_id'          => 'integer',
        'responsable_id'   => 'integer',
        'tiempo_respuesta' => 'integer',
    ];

    /** Relaciones */
    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function responsable()
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }
}
