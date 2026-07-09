<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentMasterTemplate extends Model
{
    protected $table = 'content_master_templates';

    protected $fillable = [
        'key',
        'name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function versions()
    {
        return $this->hasMany(ContentMasterTemplateVersion::class, 'content_master_template_id');
    }

    public function activeVersion()
    {
        return $this->hasOne(ContentMasterTemplateVersion::class, 'content_master_template_id')
            ->where('is_active', true);
    }
}
