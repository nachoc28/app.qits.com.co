<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiFlowStrategicOutput extends Model
{
    public const TYPE_STRATEGIC_REPORT = 'strategic_report';
    public const TYPE_EXECUTIVE_SUMMARY = 'executive_summary';
    public const TYPE_CURRENT_STRATEGIC_BASE = 'current_strategic_base';

    public const TYPES = [
        self::TYPE_STRATEGIC_REPORT,
        self::TYPE_EXECUTIVE_SUMMARY,
        self::TYPE_CURRENT_STRATEGIC_BASE,
    ];

    protected $table = 'ai_flow_strategic_outputs';

    protected $fillable = [
        'empresa_id',
        'ai_flow_execution_id',
        'ai_flow_execution_step_id',
        'ai_flow_step_result_id',
        'type',
        'title',
        'content',
        'is_current',
        'marked_by',
        'marked_at',
    ];

    protected $casts = [
        'empresa_id' => 'integer',
        'ai_flow_execution_id' => 'integer',
        'ai_flow_execution_step_id' => 'integer',
        'ai_flow_step_result_id' => 'integer',
        'is_current' => 'boolean',
        'marked_by' => 'integer',
        'marked_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    public function execution()
    {
        return $this->belongsTo(AiFlowExecution::class, 'ai_flow_execution_id');
    }

    public function executionStep()
    {
        return $this->belongsTo(AiFlowExecutionStep::class, 'ai_flow_execution_step_id');
    }

    public function stepResult()
    {
        return $this->belongsTo(AiFlowStepResult::class, 'ai_flow_step_result_id');
    }

    public function markedBy()
    {
        return $this->belongsTo(User::class, 'marked_by');
    }
}
