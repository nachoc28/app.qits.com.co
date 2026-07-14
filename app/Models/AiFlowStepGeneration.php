<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiFlowStepGeneration extends Model
{
    protected $table = 'ai_flow_step_generations';

    protected $fillable = [
        'ai_flow_execution_step_id',
        'ai_flow_step_id',
        'final_prompt_text',
        'variables_snapshot_json',
        'generated_by',
        'generated_at',
    ];

    protected $casts = [
        'ai_flow_execution_step_id' => 'integer',
        'ai_flow_step_id' => 'integer',
        'variables_snapshot_json' => 'array',
        'generated_by' => 'integer',
        'generated_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function executionStep()
    {
        return $this->belongsTo(AiFlowExecutionStep::class, 'ai_flow_execution_step_id');
    }

    public function step()
    {
        return $this->belongsTo(AiFlowStep::class, 'ai_flow_step_id');
    }

    public function generatedBy()
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function results()
    {
        return $this->hasMany(AiFlowStepResult::class, 'ai_flow_step_generation_id');
    }
}
