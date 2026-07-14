<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiFlowExecutionValue extends Model
{
    protected $table = 'ai_flow_execution_values';

    protected $fillable = [
        'ai_flow_execution_id',
        'ai_flow_variable_id',
        'ai_flow_execution_step_id',
        'value',
        'filled_by',
        'filled_at',
    ];

    protected $casts = [
        'ai_flow_execution_id' => 'integer',
        'ai_flow_variable_id' => 'integer',
        'ai_flow_execution_step_id' => 'integer',
        'filled_by' => 'integer',
        'filled_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function execution()
    {
        return $this->belongsTo(AiFlowExecution::class, 'ai_flow_execution_id');
    }

    public function variable()
    {
        return $this->belongsTo(AiFlowVariable::class, 'ai_flow_variable_id');
    }

    public function executionStep()
    {
        return $this->belongsTo(AiFlowExecutionStep::class, 'ai_flow_execution_step_id');
    }

    public function filledBy()
    {
        return $this->belongsTo(User::class, 'filled_by');
    }
}
