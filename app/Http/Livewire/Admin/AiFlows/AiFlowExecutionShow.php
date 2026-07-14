<?php

namespace App\Http\Livewire\Admin\AiFlows;

use App\Models\AiFlowExecution;
use App\Models\AiFlowExecutionStep;
use App\Models\AiFlowExecutionValue;
use App\Models\AiFlowStepResult;
use App\Models\AiFlowStrategicOutput;
use App\Models\AiFlowVariable;
use App\Services\AiFlows\AiFlowAccessService;
use App\Services\AiFlows\AiFlowExecutionService;
use App\Services\AiFlows\AiFlowPromptRenderService;
use App\Services\AiFlows\AiFlowStepCompletionService;
use App\Services\AiFlows\AiFlowStepResultService;
use App\Services\AiFlows\AiFlowStrategicOutputService;
use App\Services\AiFlows\AiFlowVariableParser;
use App\Support\AiFlowLabels;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Livewire\Component;

class AiFlowExecutionShow extends Component
{
    /** @var int */
    public $executionId;

    /** @var array<int, array<int, string|null>> */
    public $variableValues = [];

    /** @var array<int, string> */
    public $stepMessages = [];

    /** @var array<int, string> */
    public $stepErrors = [];

    /** @var array<int, string> */
    public $resultTexts = [];

    /** @var array<int, string> */
    public $strategicOutputTypes = [];

    /** @var array<int, string> */
    public $strategicOutputTitles = [];

    public function mount(int $executionId, AiFlowAccessService $accessService): void
    {
        $this->executionId = $executionId;
        $this->authorizeExecution($accessService, $this->execution());
    }

    public function saveVariables(int $executionStepId, AiFlowAccessService $accessService): void
    {
        $execution = $this->execution();
        $this->authorizeExecution($accessService, $execution);
        $executionStep = $execution->steps->firstWhere('id', $executionStepId);

        abort_unless($executionStep instanceof AiFlowExecutionStep, 404);

        if ($this->isBlocked($executionStep, app(AiFlowExecutionService::class))) {
            $this->stepErrors[$executionStepId] = 'Esta etapa está bloqueada hasta completar las etapas requeridas.';

            return;
        }

        $variables = $this->editableVariablesForExecutionStep($executionStep);
        $values = $this->variableValues[$executionStepId] ?? [];

        DB::transaction(function () use ($execution, $executionStep, $variables, $values): void {
            foreach ($variables as $variable) {
                $value = array_key_exists($variable->id, $values)
                    ? $values[$variable->id]
                    : null;

                $executionStepId = $variable->scope === AiFlowVariable::SCOPE_STEP ? $executionStep->id : null;

                $query = AiFlowExecutionValue::query()
                    ->where('ai_flow_execution_id', $execution->id)
                    ->where('ai_flow_variable_id', $variable->id);

                if ($executionStepId === null) {
                    $query->whereNull('ai_flow_execution_step_id');
                } else {
                    $query->where('ai_flow_execution_step_id', $executionStepId);
                }

                $model = $query->first() ?: new AiFlowExecutionValue([
                    'ai_flow_execution_id' => $execution->id,
                    'ai_flow_variable_id' => $variable->id,
                    'ai_flow_execution_step_id' => $executionStepId,
                ]);

                $model->forceFill([
                    'value' => $value,
                    'filled_by' => auth()->id(),
                    'filled_at' => now(),
                ])->save();
            }
        });

        unset($this->stepErrors[$executionStepId]);
        $this->stepMessages[$executionStepId] = 'Variables guardadas correctamente.';
    }

    public function generatePrompt(
        int $executionStepId,
        AiFlowAccessService $accessService,
        AiFlowPromptRenderService $promptRenderService
    ): void {
        $execution = $this->execution();
        $this->authorizeExecution($accessService, $execution);
        $executionStep = $execution->steps->firstWhere('id', $executionStepId);

        abort_unless($executionStep instanceof AiFlowExecutionStep, 404);

        if ($this->isBlocked($executionStep, app(AiFlowExecutionService::class))) {
            $this->stepErrors[$executionStepId] = 'Esta etapa está bloqueada hasta completar las etapas requeridas.';

            return;
        }

        try {
            $promptRenderService->generate($executionStep, auth()->user());
            unset($this->stepErrors[$executionStepId]);
            $this->stepMessages[$executionStepId] = 'Prompt generado correctamente.';
        } catch (InvalidArgumentException $exception) {
            $this->stepErrors[$executionStepId] = $exception->getMessage();
        }
    }

    public function copyPromptFeedback(int $executionStepId, AiFlowAccessService $accessService): void
    {
        $execution = $this->execution();
        $this->authorizeExecution($accessService, $execution);

        $this->stepMessages[$executionStepId] = 'Prompt copiado. Pégalo en el GPT recomendado.';
    }

    public function saveResult(
        int $executionStepId,
        AiFlowAccessService $accessService,
        AiFlowStepResultService $resultService
    ): void {
        $execution = $this->execution();
        $this->authorizeExecution($accessService, $execution);
        $executionStep = $execution->steps->firstWhere('id', $executionStepId);

        abort_unless($executionStep instanceof AiFlowExecutionStep, 404);

        if ($this->isBlocked($executionStep, app(AiFlowExecutionService::class))) {
            $this->stepErrors[$executionStepId] = 'Esta etapa está bloqueada hasta completar las etapas requeridas.';

            return;
        }

        try {
            $resultService->saveResult($executionStep, (string) ($this->resultTexts[$executionStepId] ?? ''), auth()->user());
            $this->resultTexts[$executionStepId] = '';
            unset($this->stepErrors[$executionStepId]);
            $this->stepMessages[$executionStepId] = 'Resultado guardado correctamente.';
        } catch (InvalidArgumentException $exception) {
            $this->stepErrors[$executionStepId] = $exception->getMessage();
        }
    }

    public function completeStep(
        int $executionStepId,
        AiFlowAccessService $accessService,
        AiFlowStepCompletionService $completionService
    ): void {
        $execution = $this->execution();
        $this->authorizeExecution($accessService, $execution);
        $executionStep = $execution->steps->firstWhere('id', $executionStepId);

        abort_unless($executionStep instanceof AiFlowExecutionStep, 404);

        if ($this->isBlocked($executionStep, app(AiFlowExecutionService::class))) {
            $this->stepErrors[$executionStepId] = 'Esta etapa está bloqueada hasta completar las etapas requeridas.';

            return;
        }

        try {
            $completionService->completeStep($executionStep, auth()->user());
            unset($this->stepErrors[$executionStepId]);
            $this->stepMessages[$executionStepId] = 'Etapa completada correctamente.';
        } catch (InvalidArgumentException $exception) {
            $this->stepErrors[$executionStepId] = $exception->getMessage();
        }
    }

    public function markStrategicOutput(
        int $resultId,
        AiFlowAccessService $accessService,
        AiFlowStrategicOutputService $strategicOutputService
    ): void {
        $execution = $this->execution();
        $this->authorizeExecution($accessService, $execution);

        $result = AiFlowStepResult::query()
            ->whereHas('executionStep', function ($query) use ($execution): void {
                $query->where('ai_flow_execution_id', $execution->id);
            })
            ->findOrFail($resultId);

        $executionStepId = (int) $result->ai_flow_execution_step_id;

        try {
            $strategicOutputService->markResult(
                $result,
                (string) ($this->strategicOutputTypes[$resultId] ?? ''),
                (string) ($this->strategicOutputTitles[$resultId] ?? ''),
                auth()->user()
            );

            unset($this->stepErrors[$executionStepId]);
            $this->stepMessages[$executionStepId] = 'Resultado estratégico marcado correctamente.';
        } catch (InvalidArgumentException $exception) {
            $this->stepErrors[$executionStepId] = $exception->getMessage();
        }
    }

    public function render(
        AiFlowAccessService $accessService,
        AiFlowExecutionService $executionService,
        AiFlowVariableParser $parser
    )
    {
        $execution = $this->execution();
        $this->authorizeExecution($accessService, $execution);

        $stepRows = $executionService->stepProgressRows($execution);
        $stepRows = $this->appendPromptDataToRows($stepRows, $parser);
        $totalSteps = count($stepRows);
        $completedSteps = collect($stepRows)->filter(static function (array $row): bool {
            return $row['execution_step']->status === AiFlowExecutionStep::STATUS_COMPLETED;
        })->count();

        return view('livewire.admin.ai-flows.ai-flow-execution-show', [
            'execution' => $execution,
            'stepRows' => $stepRows,
            'totalSteps' => $totalSteps,
            'completedSteps' => $completedSteps,
            'progressPercent' => $totalSteps > 0 ? (int) floor(($completedSteps / $totalSteps) * 100) : 0,
            'executionStatusLabel' => AiFlowLabels::executionStatus($execution->status),
        ]);
    }

    private function execution(): AiFlowExecution
    {
        return AiFlowExecution::query()
            ->with([
                'empresa',
                'flow',
                'version.variables',
                'startedBy',
                'steps.step',
                'steps.generations.generatedBy',
                'steps.results.savedBy',
            ])
            ->findOrFail($this->executionId);
    }

    private function authorizeExecution(AiFlowAccessService $accessService, AiFlowExecution $execution): void
    {
        abort_unless($accessService->canAccessExecution(auth()->user(), $execution), 403);
    }

    /**
     * @param  array<int, array<string, mixed>>  $stepRows
     * @return array<int, array<string, mixed>>
     */
    private function appendPromptDataToRows(array $stepRows, AiFlowVariableParser $parser): array
    {
        foreach ($stepRows as $index => $row) {
            /** @var AiFlowExecutionStep $executionStep */
            $executionStep = $row['execution_step'];
            $step = $executionStep->step;
            $variables = $this->variablesForExecutionStepPrompt($executionStep, $parser);
            $this->hydrateVariableValues($executionStep, $variables);
            $generations = $executionStep->generations
                ->sortByDesc('generated_at')
                ->values();
            $results = $executionStep->results
                ->sortByDesc('saved_at')
                ->values();

            $stepRows[$index]['prompt_variables'] = $variables;
            $stepRows[$index]['latest_generation'] = $generations->first();
            $stepRows[$index]['previous_generations'] = $generations->slice(1)->values();
            $stepRows[$index]['latest_result'] = $results->first();
            $stepRows[$index]['previous_results'] = $results->slice(1)->values();
            $this->hydrateStrategicOutputFormDefaults($results);
            $stepRows[$index]['prompt_has_unconfigured_variables'] = $step
                ? $this->promptHasUnconfiguredVariables($executionStep, $parser)
                : false;
        }

        return $stepRows;
    }

    /**
     * @return \Illuminate\Support\Collection<int, AiFlowVariable>
     */
    private function variablesForExecutionStepPrompt(AiFlowExecutionStep $executionStep, AiFlowVariableParser $parser)
    {
        $step = $executionStep->step;

        if (! $step) {
            return collect();
        }

        $parsed = $parser->parse((string) $step->base_prompt);

        return AiFlowVariable::query()
            ->where('ai_flow_version_id', $step->ai_flow_version_id)
            ->whereIn('name', $parsed['variables'])
            ->orderBy('position')
            ->get()
            ->filter(function (AiFlowVariable $variable) use ($executionStep): bool {
                if ($variable->scope === AiFlowVariable::SCOPE_STEP) {
                    return (int) $variable->ai_flow_step_id === (int) $executionStep->ai_flow_step_id;
                }

                return true;
            })
            ->values();
    }

    private function promptHasUnconfiguredVariables(AiFlowExecutionStep $executionStep, AiFlowVariableParser $parser): bool
    {
        $step = $executionStep->step;

        if (! $step) {
            return false;
        }

        $parsed = $parser->parse((string) $step->base_prompt);
        $configuredNames = AiFlowVariable::query()
            ->where('ai_flow_version_id', $step->ai_flow_version_id)
            ->whereIn('name', $parsed['variables'])
            ->pluck('name')
            ->all();

        foreach ($parsed['variables'] as $variableName) {
            if (! in_array($variableName, $configuredNames, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, AiFlowVariable>  $variables
     */
    private function hydrateVariableValues(AiFlowExecutionStep $executionStep, $variables): void
    {
        foreach ($variables as $variable) {
            if ($variable->scope === AiFlowVariable::SCOPE_OUTPUT) {
                continue;
            }

            if (isset($this->variableValues[$executionStep->id]) && array_key_exists($variable->id, $this->variableValues[$executionStep->id])) {
                continue;
            }

            $query = AiFlowExecutionValue::query()
                ->where('ai_flow_execution_id', $executionStep->ai_flow_execution_id)
                ->where('ai_flow_variable_id', $variable->id);

            if ($variable->scope === AiFlowVariable::SCOPE_STEP) {
                $query->where('ai_flow_execution_step_id', $executionStep->id);
            } else {
                $query->whereNull('ai_flow_execution_step_id');
            }

            $stored = $query->first();
            $this->variableValues[$executionStep->id][$variable->id] = $stored
                ? (string) $stored->value
                : (string) $variable->default_value;
        }
    }

    /**
     * @return \Illuminate\Support\Collection<int, AiFlowVariable>
     */
    private function editableVariablesForExecutionStep(AiFlowExecutionStep $executionStep)
    {
        return $this->variablesForExecutionStepPrompt($executionStep, app(AiFlowVariableParser::class))
            ->reject(static function (AiFlowVariable $variable): bool {
                return $variable->scope === AiFlowVariable::SCOPE_OUTPUT;
            })
            ->values();
    }

    private function isBlocked(AiFlowExecutionStep $executionStep, AiFlowExecutionService $executionService): bool
    {
        $rows = $executionService->stepProgressRows($executionStep->execution);

        foreach ($rows as $row) {
            if ((int) $row['execution_step']->id === (int) $executionStep->id) {
                return (bool) $row['is_blocked'];
            }
        }

        return true;
    }

    private function hydrateStrategicOutputFormDefaults($results): void
    {
        foreach ($results as $result) {
            if (! isset($this->strategicOutputTypes[$result->id])) {
                $this->strategicOutputTypes[$result->id] = AiFlowStrategicOutput::TYPE_STRATEGIC_REPORT;
            }

            if (! isset($this->strategicOutputTitles[$result->id])) {
                $this->strategicOutputTitles[$result->id] = 'Resultado estratégico';
            }
        }
    }
}
