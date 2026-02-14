<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoProyecto extends Model
{
    protected $table = 'tipos_proyectos';

    protected $fillable = ['nombre'];

    public function proyectos()
    {
        return $this->hasMany(ProyectoEmpresa::class, 'tipo_proyecto_id');
    }

    public function leads()
    {
        return $this->hasMany(Lead::class, 'tipo_proyecto_id');
    }
}
