<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiFlowVersion extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_ARCHIVED = 'archived';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_PUBLISHED,
        self::STATUS_ARCHIVED,
    ];

    protected $table = 'ai_flow_versions';

    protected $fillable = [
        'ai_flow_id',
        'version_number',
        'status',
        'published_at',
        'published_by',
    ];

    protected $casts = [
        'ai_flow_id' => 'integer',
        'version_number' => 'integer',
        'published_by' => 'integer',
        'published_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function flow()
    {
        return $this->belongsTo(AiFlow::class, 'ai_flow_id');
    }

    public function steps()
    {
        return $this->hasMany(AiFlowStep::class, 'ai_flow_version_id')
            ->orderBy('position');
    }

    public function variables()
    {
        return $this->hasMany(AiFlowVariable::class, 'ai_flow_version_id')
            ->orderBy('position');
    }

    public function executions()
    {
        return $this->hasMany(AiFlowExecution::class, 'ai_flow_version_id');
    }

    public function publishedBy()
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }
}
