<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Empresa extends Model
{
    protected $table = 'empresas';

    protected $fillable = [
        'nit', 'nombre', 'direccion', 'ciudad_id', 'telefono', 'email', 'logo','active'
    ];

    protected $casts = [
        'ciudad_id' => 'integer',
        'email'     => 'string',
        'active' => 'boolean',
        'deleted_at'  => 'datetime',
    ];

    /** Relaciones */
    public function ciudad()
    {
        return $this->belongsTo(Ciudad::class);
    }

    public function usuarios()
    {
        return $this->hasMany(User::class, 'empresa_id');
    }

    public function proyectos()
    {
        return $this->hasMany(ProyectoEmpresa::class);
    }

    /** Scopes */
    public function scopeNombreLike(Builder $q, string $term): Builder
    {
        return $q->where('nombre', 'like', "%{$term}%");
    }
}
