<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class AiFlowStepDependency extends Model
{
    protected $table = 'ai_flow_step_dependencies';

    protected $fillable = [
        'ai_flow_step_id',
        'depends_on_step_id',
    ];

    protected $casts = [
        'ai_flow_step_id' => 'integer',
        'depends_on_step_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $dependency): void {
            if ((int) $dependency->ai_flow_step_id === (int) $dependency->depends_on_step_id) {
                throw new InvalidArgumentException('An AI flow step cannot depend on itself.');
            }

            $step = AiFlowStep::query()->find($dependency->ai_flow_step_id);
            $dependsOn = AiFlowStep::query()->find($dependency->depends_on_step_id);

            if (! $step instanceof AiFlowStep || ! $dependsOn instanceof AiFlowStep) {
                return;
            }

            if ((int) $step->ai_flow_version_id !== (int) $dependsOn->ai_flow_version_id) {
                throw new InvalidArgumentException('AI flow step dependencies must belong to the same flow version.');
            }
        });
    }

    public function step()
    {
        return $this->belongsTo(AiFlowStep::class, 'ai_flow_step_id');
    }

    public function dependsOnStep()
    {
        return $this->belongsTo(AiFlowStep::class, 'depends_on_step_id');
    }
}
