<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiFlowStep extends Model
{
    protected $table = 'ai_flow_steps';

    protected $fillable = [
        'ai_flow_version_id',
        'step_key',
        'name',
        'description',
        'position',
        'recommended_gpt',
        'expected_output_name',
        'base_prompt',
        'is_active',
    ];

    protected $casts = [
        'ai_flow_version_id' => 'integer',
        'position' => 'integer',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function version()
    {
        return $this->belongsTo(AiFlowVersion::class, 'ai_flow_version_id');
    }

    public function variables()
    {
        return $this->hasMany(AiFlowVariable::class, 'ai_flow_step_id')
            ->orderBy('position');
    }

    public function outputVariables()
    {
        return $this->hasMany(AiFlowVariable::class, 'source_step_id')
            ->where('scope', AiFlowVariable::SCOPE_OUTPUT)
            ->orderBy('position');
    }

    public function dependencies()
    {
        return $this->hasMany(AiFlowStepDependency::class, 'ai_flow_step_id');
    }

    public function dependsOn()
    {
        return $this->belongsToMany(
            AiFlowStep::class,
            'ai_flow_step_dependencies',
            'ai_flow_step_id',
            'depends_on_step_id'
        )->withTimestamps();
    }

    public function dependentSteps()
    {
        return $this->belongsToMany(
            AiFlowStep::class,
            'ai_flow_step_dependencies',
            'depends_on_step_id',
            'ai_flow_step_id'
        )->withTimestamps();
    }

    public function executionSteps()
    {
        return $this->hasMany(AiFlowExecutionStep::class, 'ai_flow_step_id');
    }

    public function generations()
    {
        return $this->hasMany(AiFlowStepGeneration::class, 'ai_flow_step_id');
    }
}
