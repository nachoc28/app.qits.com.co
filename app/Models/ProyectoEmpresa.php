<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProyectoEmpresa extends Model
{
    protected $table = 'proyectos_empresas';

    protected $fillable = ['empresa_id', 'url', 'tipo_proyecto_id'];

    protected $casts = [
        'empresa_id'       => 'integer',
        'tipo_proyecto_id' => 'integer',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    public function tipoProyecto()
    {
        return $this->belongsTo(TipoProyecto::class, 'tipo_proyecto_id');
    }

    public function utms()
    {
        return $this->hasMany(Utm::class, 'proyecto_empresa_id');
    }
}
