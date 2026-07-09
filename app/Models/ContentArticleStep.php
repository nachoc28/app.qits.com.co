<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class ContentArticleStep extends Model
{
    public const TYPE_OBJECTIVE = 'objective';
    public const TYPE_DRAFTING = 'drafting';
    public const TYPE_VIDEO_INSTAGRAM = 'video_instagram';

    public const STATUS_PENDING = 'pending';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_READY = 'ready';

    public const STEP_TYPES = [
        self::TYPE_OBJECTIVE,
        self::TYPE_DRAFTING,
        self::TYPE_VIDEO_INSTAGRAM,
    ];

    public const STEP_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_IN_PROGRESS,
        self::STATUS_READY,
    ];

    protected $table = 'content_article_steps';

    protected $fillable = [
        'content_article_id',
        'step_type',
        'step_status',
        'ready_at',
        'ready_by',
    ];

    protected $casts = [
        'content_article_id' => 'integer',
        'ready_by' => 'integer',
        'ready_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $step) {
            if (
                $step->step_type === self::TYPE_OBJECTIVE
                && $step->step_status === self::STATUS_READY
            ) {
                $article = $step->relationLoaded('article')
                    ? $step->article
                    : $step->article()->first();

                if (! $article instanceof ContentArticle) {
                    throw new InvalidArgumentException('Objective step requires a valid content article.');
                }

                if (
                    trim((string) $article->refined_objective) === ''
                    || trim((string) $article->refined_target_audience) === ''
                ) {
                    throw new InvalidArgumentException(
                        'Objective step cannot be marked ready without refined objective and refined target audience.'
                    );
                }
            }
        });
    }

    public function article()
    {
        return $this->belongsTo(ContentArticle::class, 'content_article_id');
    }

    public function readyBy()
    {
        return $this->belongsTo(User::class, 'ready_by');
    }
}
