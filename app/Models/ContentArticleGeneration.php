<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentArticleGeneration extends Model
{
    public const STEP_TYPES = ContentArticleStep::STEP_TYPES;

    protected $table = 'content_article_generations';

    protected $fillable = [
        'content_article_id',
        'content_master_template_version_id',
        'step_type',
        'final_prompt_text',
        'generated_by',
        'generated_at',
    ];

    protected $casts = [
        'content_article_id' => 'integer',
        'content_master_template_version_id' => 'integer',
        'generated_by' => 'integer',
        'generated_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function article()
    {
        return $this->belongsTo(ContentArticle::class, 'content_article_id');
    }

    public function templateVersion()
    {
        return $this->belongsTo(ContentMasterTemplateVersion::class, 'content_master_template_version_id');
    }

    public function generatedBy()
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
