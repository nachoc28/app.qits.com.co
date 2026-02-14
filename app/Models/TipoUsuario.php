<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoUsuario extends Model
{
    // OJO: nombre de tabla no convencional
    protected $table = 'tipos_usuarios';

    protected $fillable = ['nombre'];

    public function usuarios()
    {
        return $this->hasMany(User::class, 'tipo_usuario_id');
    }
}
