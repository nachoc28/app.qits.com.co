<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiFlowStepResult extends Model
{
    protected $table = 'ai_flow_step_results';

    protected $fillable = [
        'ai_flow_execution_step_id',
        'ai_flow_step_generation_id',
        'result_text',
        'saved_by',
        'saved_at',
    ];

    protected $casts = [
        'ai_flow_execution_step_id' => 'integer',
        'ai_flow_step_generation_id' => 'integer',
        'saved_by' => 'integer',
        'saved_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function executionStep()
    {
        return $this->belongsTo(AiFlowExecutionStep::class, 'ai_flow_execution_step_id');
    }

    public function generation()
    {
        return $this->belongsTo(AiFlowStepGeneration::class, 'ai_flow_step_generation_id');
    }

    public function savedBy()
    {
        return $this->belongsTo(User::class, 'saved_by');
    }

    public function strategicOutputs()
    {
        return $this->hasMany(AiFlowStrategicOutput::class, 'ai_flow_step_result_id');
    }
}
