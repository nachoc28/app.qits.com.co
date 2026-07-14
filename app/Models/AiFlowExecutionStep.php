<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiFlowExecutionStep extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_IN_PROGRESS,
        self::STATUS_COMPLETED,
    ];

    protected $table = 'ai_flow_execution_steps';

    protected $fillable = [
        'ai_flow_execution_id',
        'ai_flow_step_id',
        'status',
        'started_at',
        'started_by',
        'completed_at',
        'completed_by',
    ];

    protected $casts = [
        'ai_flow_execution_id' => 'integer',
        'ai_flow_step_id' => 'integer',
        'started_by' => 'integer',
        'completed_by' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function execution()
    {
        return $this->belongsTo(AiFlowExecution::class, 'ai_flow_execution_id');
    }

    public function step()
    {
        return $this->belongsTo(AiFlowStep::class, 'ai_flow_step_id');
    }

    public function startedBy()
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function completedBy()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function values()
    {
        return $this->hasMany(AiFlowExecutionValue::class, 'ai_flow_execution_step_id');
    }

    public function generations()
    {
        return $this->hasMany(AiFlowStepGeneration::class, 'ai_flow_execution_step_id');
    }

    public function results()
    {
        return $this->hasMany(AiFlowStepResult::class, 'ai_flow_execution_step_id');
    }
}
