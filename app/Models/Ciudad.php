<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Ciudad extends Model
{
    protected $table = 'ciudades';

    protected $fillable = ['nombre', 'departamento_id', 'codigo_divipola'];

    protected $casts = [
        'departamento_id'  => 'integer',
        'codigo_divipola'  => 'string',
    ];

    /** Relaciones */
    public function departamento()
    {
        return $this->belongsTo(Departamento::class);
    }

    public function empresas()
    {
        return $this->hasMany(Empresa::class);
    }

    /** Scopes */
    public function scopeNombreLike(Builder $q, string $term): Builder
    {
        return $q->where('nombre', 'like', "%{$term}%");
    }
}
