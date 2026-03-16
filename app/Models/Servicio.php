<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Servicio extends Model
{
    protected $table = 'servicios';

    protected $fillable = [
        'nombre',
        'slug',
        'descripcion',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function empresas()
    {
        return $this->belongsToMany(Empresa::class, 'empresa_servicio')
                    ->withTimestamps();
    }

    public function scopeActivos(Builder $q): Builder
    {
        return $q->where('activo', true);
    }
}
