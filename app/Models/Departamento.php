<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Departamento extends Model
{
    protected $table = 'departamentos';

    protected $fillable = ['nombre', 'pais_id', 'codigo_divipola'];

    protected $casts = [
        'pais_id'          => 'integer',
        'codigo_divipola'  => 'string',
    ];

    /** Relaciones */
    public function pais()
    {
        return $this->belongsTo(Pais::class);
    }

    public function ciudades()
    {
        return $this->hasMany(Ciudad::class);
    }

    /** Scopes */
    public function scopeNombreLike(Builder $q, string $term): Builder
    {
        return $q->where('nombre', 'like', "%{$term}%");
    }

    /** Helpers */
    public function ciudadIndefinida(): ?Ciudad
    {
        return $this->ciudades()->where('nombre', 'Indefinido')->first();
    }
}
