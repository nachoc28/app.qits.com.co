<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentArticle extends Model
{
    public const TONE_TUTEO = 'tuteo';
    public const TONE_USTEO = 'usteo';

    public const MAIN_STATUS_PROCESSING = 'processing';
    public const MAIN_STATUS_UNPUBLISHED = 'unpublished';
    public const MAIN_STATUS_PUBLISHED = 'published';

    public const STAGE_PENDING = 'pending';
    public const STAGE_STRATEGIC_REFINEMENT = 'strategic_refinement';
    public const STAGE_DRAFTING = 'drafting';
    public const STAGE_VIDEO_INSTAGRAM = 'video_instagram';
    public const STAGE_FINAL_FILE = 'final_file';
    public const STAGE_COMPLETED = 'completed';

    public const TONES = [
        self::TONE_TUTEO,
        self::TONE_USTEO,
    ];

    public const MAIN_STATUSES = [
        self::MAIN_STATUS_PROCESSING,
        self::MAIN_STATUS_UNPUBLISHED,
        self::MAIN_STATUS_PUBLISHED,
    ];

    public const OPERATIONAL_STAGES = [
        self::STAGE_PENDING,
        self::STAGE_STRATEGIC_REFINEMENT,
        self::STAGE_DRAFTING,
        self::STAGE_VIDEO_INSTAGRAM,
        self::STAGE_FINAL_FILE,
        self::STAGE_COMPLETED,
    ];

    protected $table = 'content_articles';

    protected $fillable = [
        'content_import_id',
        'article_date',
        'topic',
        'strategic_objective_general',
        'target_audience_general',
        'refined_objective',
        'refined_target_audience',
        'tone',
        'main_status',
        'operational_stage',
        'delivered_at',
        'delivered_by',
        'published_at',
        'published_by',
        'published_url',
    ];

    protected $casts = [
        'content_import_id' => 'integer',
        'delivered_by' => 'integer',
        'published_by' => 'integer',
        'article_date' => 'date',
        'delivered_at' => 'datetime',
        'published_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function contentImport()
    {
        return $this->belongsTo(ContentImport::class, 'content_import_id');
    }

    public function deliveredBy()
    {
        return $this->belongsTo(User::class, 'delivered_by');
    }

    public function publishedBy()
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function steps()
    {
        return $this->hasMany(ContentArticleStep::class, 'content_article_id');
    }

    public function generations()
    {
        return $this->hasMany(ContentArticleGeneration::class, 'content_article_id');
    }

    public function files()
    {
        return $this->hasMany(ContentArticleFile::class, 'content_article_id');
    }
}
