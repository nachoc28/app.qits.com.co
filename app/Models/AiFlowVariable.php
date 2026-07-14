<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class AiFlowVariable extends Model
{
    public const SCOPE_GLOBAL = 'global';
    public const SCOPE_STEP = 'step';
    public const SCOPE_OUTPUT = 'output';

    public const SCOPES = [
        self::SCOPE_GLOBAL,
        self::SCOPE_STEP,
        self::SCOPE_OUTPUT,
    ];

    public const INPUT_TYPE_INPUT = 'input';
    public const INPUT_TYPE_TEXTAREA = 'textarea';

    public const INPUT_TYPES = [
        self::INPUT_TYPE_INPUT,
        self::INPUT_TYPE_TEXTAREA,
    ];

    protected $table = 'ai_flow_variables';

    protected $fillable = [
        'ai_flow_version_id',
        'ai_flow_step_id',
        'source_step_id',
        'name',
        'label',
        'scope',
        'input_type',
        'is_required',
        'help_text',
        'placeholder',
        'position',
        'default_value',
    ];

    protected $casts = [
        'ai_flow_version_id' => 'integer',
        'ai_flow_step_id' => 'integer',
        'source_step_id' => 'integer',
        'is_required' => 'boolean',
        'position' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $variable): void {
            if (! preg_match('/^[a-z][a-z0-9_]*$/', (string) $variable->name)) {
                throw new InvalidArgumentException('AI flow variable names must be snake_case without accents or spaces.');
            }

            if ($variable->scope === self::SCOPE_GLOBAL && $variable->ai_flow_step_id !== null) {
                throw new InvalidArgumentException('Global AI flow variables cannot be attached to a step.');
            }

            if ($variable->scope !== self::SCOPE_OUTPUT && $variable->source_step_id !== null) {
                throw new InvalidArgumentException('source_step_id only applies to output variables.');
            }

            if ($variable->scope === self::SCOPE_STEP && $variable->ai_flow_step_id === null) {
                throw new InvalidArgumentException('Step AI flow variables must be attached to a step.');
            }

            $thisVersionId = (int) $variable->ai_flow_version_id;

            foreach (['ai_flow_step_id', 'source_step_id'] as $field) {
                $stepId = $variable->{$field};

                if ($stepId === null) {
                    continue;
                }

                $step = AiFlowStep::query()->find($stepId);

                if ($step instanceof AiFlowStep && (int) $step->ai_flow_version_id !== $thisVersionId) {
                    throw new InvalidArgumentException('AI flow variable steps must belong to the same flow version.');
                }
            }
        });
    }

    public function version()
    {
        return $this->belongsTo(AiFlowVersion::class, 'ai_flow_version_id');
    }

    public function step()
    {
        return $this->belongsTo(AiFlowStep::class, 'ai_flow_step_id');
    }

    public function sourceStep()
    {
        return $this->belongsTo(AiFlowStep::class, 'source_step_id');
    }

    public function values()
    {
        return $this->hasMany(AiFlowExecutionValue::class, 'ai_flow_variable_id');
    }
}
