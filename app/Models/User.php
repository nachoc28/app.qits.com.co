<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;


class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use HasProfilePhoto;
    use Notifiable;
    use TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected $fillable = [
        'name','email','password','telefono','empresa_id','tipo_usuario_id','active'
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'empresa_id'        => 'integer',
        'tipo_usuario_id'   => 'integer',
        'active'            => 'boolean',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'profile_photo_url',
    ];

    public function isAdmin(): bool
    {
        return optional($this->tipoUsuario)->nombre === 'Administrador';
    }

    /** Relaciones propias */
    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    public function tipoUsuario()
    {
        return $this->belongsTo(TipoUsuario::class, 'tipo_usuario_id');
    }

    public function ticketsAsignados()
    {
        return $this->hasMany(Ticket::class, 'responsable_id');
    }

    public function leadsConvertidos()
    {
        return $this->hasMany(Lead::class, 'converted_user_id');
    }

}
