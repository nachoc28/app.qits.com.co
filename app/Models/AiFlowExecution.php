<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiFlowExecution extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_IN_PROGRESS,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
    ];

    protected $table = 'ai_flow_executions';

    protected $fillable = [
        'empresa_id',
        'ai_flow_id',
        'ai_flow_version_id',
        'title',
        'status',
        'started_by',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'empresa_id' => 'integer',
        'ai_flow_id' => 'integer',
        'ai_flow_version_id' => 'integer',
        'started_by' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    public function flow()
    {
        return $this->belongsTo(AiFlow::class, 'ai_flow_id');
    }

    public function version()
    {
        return $this->belongsTo(AiFlowVersion::class, 'ai_flow_version_id');
    }

    public function startedBy()
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function steps()
    {
        return $this->hasMany(AiFlowExecutionStep::class, 'ai_flow_execution_id');
    }

    public function values()
    {
        return $this->hasMany(AiFlowExecutionValue::class, 'ai_flow_execution_id');
    }

    public function strategicOutputs()
    {
        return $this->hasMany(AiFlowStrategicOutput::class, 'ai_flow_execution_id');
    }
}
