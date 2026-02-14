<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Pais extends Model
{
    protected $table = 'paises';

    protected $fillable = ['nombre', 'iso2'];

    protected $casts = [
        'nombre' => 'string',
        'iso2'   => 'string',
    ];

    /** Relaciones */
    public function departamentos()
    {
        return $this->hasMany(Departamento::class);
    }

    /** Scopes */
    public function scopeNombreLike(Builder $q, string $term): Builder
    {
        return $q->where('nombre', 'like', "%{$term}%");
    }

    /** Helpers */
    public function departamentoIndefinido(): ?Departamento
    {
        return $this->departamentos()->where('nombre', 'Indefinido')->first();
    }

    public static function indefinido(): ?self
    {
        return static::where('nombre', 'Indefinido')->first();
    }
}
