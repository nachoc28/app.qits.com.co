<?php

namespace App\Services\AiFlows;

use App\Models\AiFlowExecutionStep;
use App\Models\AiFlowExecutionValue;
use App\Models\AiFlowStep;
use App\Models\AiFlowStepGeneration;
use App\Models\AiFlowStepResult;
use App\Models\AiFlowVariable;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AiFlowPromptRenderService
{
    /** @var AiFlowVariableParser */
    private $parser;

    public function __construct(AiFlowVariableParser $parser)
    {
        $this->parser = $parser;
    }

    public function generate(AiFlowExecutionStep $executionStep, User $user): AiFlowStepGeneration
    {
        $executionStep->loadMissing(['execution', 'step.version']);
        $step = $executionStep->step;

        if (! $step instanceof AiFlowStep) {
            throw new InvalidArgumentException('La etapa de flujo no está disponible.');
        }

        $basePrompt = (string) $step->base_prompt;
        $parsed = $this->parser->parse($basePrompt);

        if (count($parsed['invalid_tokens']) > 0) {
            throw new InvalidArgumentException('El prompt contiene variables inválidas.');
        }

        $variablesByName = AiFlowVariable::query()
            ->where('ai_flow_version_id', $step->ai_flow_version_id)
            ->whereIn('name', $parsed['variables'])
            ->get()
            ->keyBy('name');

        $finalPrompt = $basePrompt;
        $snapshot = [];

        foreach ($parsed['variables'] as $variableName) {
            /** @var AiFlowVariable|null $variable */
            $variable = $variablesByName->get($variableName);

            if (! $variable instanceof AiFlowVariable) {
                throw new InvalidArgumentException(sprintf('La variable "%s" no está configurada.', $variableName));
            }

            $resolved = $this->resolveVariableValue($executionStep, $variable);
            $value = $resolved['value'];

            if ($variable->scope === AiFlowVariable::SCOPE_OUTPUT && $value === null) {
                throw new InvalidArgumentException(sprintf('La variable de resultado "%s" aún no tiene resultado disponible.', $variable->name));
            }

            if ($variable->is_required && trim((string) $value) === '') {
                throw new InvalidArgumentException(sprintf('La variable requerida "%s" no tiene valor.', $variable->label));
            }

            $value = (string) ($value ?? '');
            $finalPrompt = str_replace('{{' . $variableName . '}}', $value, $finalPrompt);

            $snapshot[] = [
                'variable' => $variable->name,
                'label' => $variable->label,
                'scope' => $variable->scope,
                'source' => $resolved['source'],
                'value' => $value,
            ];
        }

        return DB::transaction(function () use ($executionStep, $step, $user, $finalPrompt, $snapshot): AiFlowStepGeneration {
            if ($executionStep->status === AiFlowExecutionStep::STATUS_PENDING) {
                $executionStep->forceFill([
                    'status' => AiFlowExecutionStep::STATUS_IN_PROGRESS,
                    'started_by' => $user->id,
                    'started_at' => now(),
                ])->save();
            }

            return AiFlowStepGeneration::query()->create([
                'ai_flow_execution_step_id' => $executionStep->id,
                'ai_flow_step_id' => $step->id,
                'final_prompt_text' => $finalPrompt,
                'variables_snapshot_json' => $snapshot,
                'generated_by' => $user->id,
                'generated_at' => now(),
            ]);
        });
    }

    /**
     * @return array{value: string|null, source: string}
     */
    private function resolveVariableValue(AiFlowExecutionStep $executionStep, AiFlowVariable $variable): array
    {
        if ($variable->scope === AiFlowVariable::SCOPE_OUTPUT) {
            $sourceExecutionStep = AiFlowExecutionStep::query()
                ->where('ai_flow_execution_id', $executionStep->ai_flow_execution_id)
                ->where('ai_flow_step_id', $variable->source_step_id)
                ->first();

            if (! $sourceExecutionStep instanceof AiFlowExecutionStep) {
                return ['value' => null, 'source' => 'output'];
            }

            $result = AiFlowStepResult::query()
                ->where('ai_flow_execution_step_id', $sourceExecutionStep->id)
                ->orderByDesc('saved_at')
                ->orderByDesc('id')
                ->first();

            return [
                'value' => $result ? (string) $result->result_text : null,
                'source' => 'output:' . $sourceExecutionStep->id,
            ];
        }

        $query = AiFlowExecutionValue::query()
            ->where('ai_flow_execution_id', $executionStep->ai_flow_execution_id)
            ->where('ai_flow_variable_id', $variable->id);

        if ($variable->scope === AiFlowVariable::SCOPE_STEP) {
            $query->where('ai_flow_execution_step_id', $executionStep->id);
            $source = 'step:' . $executionStep->id;
        } else {
            $query->whereNull('ai_flow_execution_step_id');
            $source = 'global';
        }

        $value = $query->first();

        return [
            'value' => $value ? (string) $value->value : null,
            'source' => $source,
        ];
    }
}
