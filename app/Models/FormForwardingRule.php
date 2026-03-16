<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class FormForwardingRule extends Model
{
    protected $table = 'form_forwarding_rules';

    protected $fillable = [
        'empresa_id',
        'site_key',
        'form_name',
        'allowed_domain',
        'allowed_origin_url',
        'message_template',
        'generate_pdf_always',
        'only_for_long_forms',
        'is_active',
    ];

    protected $casts = [
        'generate_pdf_always' => 'boolean',
        'only_for_long_forms' => 'boolean',
        'is_active'           => 'boolean',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    public function scopeActivas(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    /** Busca por site_key (clave pública usada en el webhook). */
    public static function findBySiteKey(string $key): ?self
    {
        return static::where('site_key', $key)->where('is_active', true)->first();
    }
}
