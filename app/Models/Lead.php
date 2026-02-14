<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    protected $table = 'leads';

    protected $fillable = [
        'nombre','email','telefono','tipo_proyecto_id',
        'mensaje','origen','status','converted_user_id',
    ];

    protected $casts = [
        'tipo_proyecto_id'  => 'integer',
        'converted_user_id' => 'integer',
        'created_at'        => 'datetime',
        'updated_at'        => 'datetime',
    ];

    /** Relaciones */
    public function tipoProyecto()
    {
        return $this->belongsTo(TipoProyecto::class, 'tipo_proyecto_id');
    }

    public function convertedUser()
    {
        return $this->belongsTo(User::class, 'converted_user_id');
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class, 'lead_id');
    }
}
