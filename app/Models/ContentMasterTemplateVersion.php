<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class ContentMasterTemplateVersion extends Model
{
    protected $table = 'content_master_template_versions';

    protected $fillable = [
        'content_master_template_id',
        'version_number',
        'template_body',
        'is_active',
    ];

    protected $casts = [
        'content_master_template_id' => 'integer',
        'version_number' => 'integer',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $version) {
            if (! $version->is_active) {
                return;
            }

            $query = static::query()
                ->where('content_master_template_id', $version->content_master_template_id)
                ->where('is_active', true);

            if ($version->exists) {
                $query->where('id', '<>', $version->id);
            }

            if ($query->exists()) {
                throw new InvalidArgumentException(
                    'Only one active template version is allowed per master template.'
                );
            }
        });
    }

    public function masterTemplate()
    {
        return $this->belongsTo(ContentMasterTemplate::class, 'content_master_template_id');
    }

    public function generations()
    {
        return $this->hasMany(ContentArticleGeneration::class, 'content_master_template_version_id');
    }
}
