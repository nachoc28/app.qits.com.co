<?php

namespace App\Http\Livewire\Admin\AiFlows;

use App\Models\AiFlow;
use App\Models\AiFlowStep;
use App\Models\AiFlowStepDependency;
use App\Models\AiFlowVariable;
use App\Models\AiFlowVersion;
use App\Services\AiFlows\AiFlowVariableParser;
use App\Services\AiFlows\AiFlowVersionService;
use App\Services\AiFlows\AiFlowVersionValidationService;
use App\Support\AiFlowLabels;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Component;

class AiFlowVersionShow extends Component
{
    /** @var int */
    public $flowId;

    /** @var int */
    public $versionId;

    /** @var array<int, string> */
    public $publicationErrors = [];

    /** @var array<int, string> */
    public $publicationWarnings = [];

    /** @var int|null */
    public $editingStepId;

    /** @var string */
    public $step_key = '';

    /** @var string */
    public $name = '';

    /** @var string|null */
    public $description = '';

    /** @var int|string|null */
    public $position = 1;

    /** @var string|null */
    public $recommended_gpt = '';

    /** @var string|null */
    public $expected_output_name = '';

    /** @var string|null */
    public $base_prompt = '';

    /** @var bool */
    public $is_active = true;

    /** @var int|string|null */
    public $depends_on_step_id = '';

    /** @var string|null */
    public $stepFormError;

    /** @var int|null */
    public $editingVariableId;

    /** @var string */
    public $variable_name = '';

    /** @var string */
    public $variable_label = '';

    /** @var string */
    public $variable_input_type = AiFlowVariable::INPUT_TYPE_INPUT;

    /** @var string */
    public $variable_scope = AiFlowVariable::SCOPE_GLOBAL;

    /** @var bool */
    public $variable_is_required = true;

    /** @var string|null */
    public $variable_help_text = '';

    /** @var string|null */
    public $variable_placeholder = '';

    /** @var string|null */
    public $variable_default_value = '';

    /** @var int|string|null */
    public $variable_position = 1;

    /** @var int|string|null */
    public $variable_ai_flow_step_id = '';

    /** @var int|string|null */
    public $variable_source_step_id = '';

    /** @var string|null */
    public $variableFormError;

    /** @var array<int, string> */
    public $variableSyncInvalidTokens = [];

    public function mount(int $flowId, int $versionId): void
    {
        $this->authorizeAdmin();
        $this->flowId = $flowId;
        $this->versionId = $versionId;
        $this->version();
        $this->resetStepForm();
        $this->resetVariableForm();
    }

    public function publish(
        AiFlowVersionValidationService $validationService,
        AiFlowVersionService $versionService
    ): void {
        $this->authorizeAdmin();
        $this->publicationErrors = [];
        $this->publicationWarnings = [];
        $version = $this->version();
        $validation = $validationService->validateForPublication($version);
        $this->publicationWarnings = $validation['warnings'];

        if (! $validation['can_publish']) {
            $this->publicationErrors = $validation['errors'];

            return;
        }

        try {
            $versionService->publish($version, auth()->user());
            session()->flash('ai_flow_version_show_success', 'Versión publicada correctamente.');
        } catch (InvalidArgumentException $exception) {
            $this->publicationErrors = [$exception->getMessage()];
        }
    }

    public function startCreateStep(): void
    {
        $this->authorizeAdmin();
        $this->resetStepForm();
    }

    public function editStep(int $stepId): void
    {
        $this->authorizeAdmin();
        $version = $this->version();

        if (! $this->isDraftVersion($version)) {
            $this->stepFormError = 'Solo se pueden editar etapas en versiones borrador.';

            return;
        }

        $step = $version->steps()->whereKey($stepId)->firstOrFail();
        $dependency = AiFlowStepDependency::query()
            ->where('ai_flow_step_id', $step->id)
            ->first();

        $this->editingStepId = (int) $step->id;
        $this->step_key = (string) $step->step_key;
        $this->name = (string) $step->name;
        $this->description = (string) $step->description;
        $this->position = (int) $step->position;
        $this->recommended_gpt = (string) $step->recommended_gpt;
        $this->expected_output_name = (string) $step->expected_output_name;
        $this->base_prompt = (string) $step->base_prompt;
        $this->is_active = (bool) $step->is_active;
        $this->depends_on_step_id = $dependency ? (string) $dependency->depends_on_step_id : '';
        $this->stepFormError = null;
        $this->resetErrorBag();
    }

    public function saveStep(): void
    {
        $this->authorizeAdmin();
        $version = $this->version();

        if (! $this->isDraftVersion($version)) {
            $this->stepFormError = 'Solo se pueden crear o editar etapas en versiones borrador.';

            return;
        }

        $validated = $this->validate($this->stepRules(), $this->stepMessages());
        $this->validateDependencySelection($version);

        DB::transaction(function () use ($version, $validated): void {
            $attributes = [
                'ai_flow_version_id' => $version->id,
                'step_key' => $validated['step_key'],
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'position' => (int) $validated['position'],
                'recommended_gpt' => $validated['recommended_gpt'] ?? null,
                'expected_output_name' => $validated['expected_output_name'] ?? null,
                'base_prompt' => $validated['base_prompt'] ?? null,
                'is_active' => (bool) $validated['is_active'],
            ];

            if ($this->editingStepId) {
                $step = AiFlowStep::query()
                    ->where('ai_flow_version_id', $version->id)
                    ->whereKey($this->editingStepId)
                    ->firstOrFail();
                $step->forceFill($attributes)->save();
            } else {
                $step = AiFlowStep::query()->create($attributes);
            }

            AiFlowStepDependency::query()
                ->where('ai_flow_step_id', $step->id)
                ->delete();

            if ((int) $this->depends_on_step_id > 0) {
                AiFlowStepDependency::query()->create([
                    'ai_flow_step_id' => $step->id,
                    'depends_on_step_id' => (int) $this->depends_on_step_id,
                ]);
            }
        });

        session()->flash('ai_flow_step_success', $this->editingStepId ? 'Etapa actualizada correctamente.' : 'Etapa creada correctamente.');
        $this->resetStepForm();
    }

    public function toggleStepActive(int $stepId): void
    {
        $this->authorizeAdmin();
        $version = $this->version();

        if (! $this->isDraftVersion($version)) {
            $this->stepFormError = 'Solo se pueden activar o inactivar etapas en versiones borrador.';

            return;
        }

        $step = $version->steps()->whereKey($stepId)->firstOrFail();
        $step->forceFill([
            'is_active' => ! (bool) $step->is_active,
        ])->save();

        session()->flash('ai_flow_step_success', $step->is_active ? 'Etapa activada correctamente.' : 'Etapa inactivada correctamente.');
    }

    public function syncVariables(AiFlowVariableParser $parser): void
    {
        $this->authorizeAdmin();
        $version = $this->version();

        if (! $this->isDraftVersion($version)) {
            $this->variableFormError = 'Solo se pueden sincronizar variables en versiones borrador.';

            return;
        }

        $detected = $this->detectedVariablesForVersion($version, $parser);
        $this->variableSyncInvalidTokens = $detected['invalid_tokens'];
        $created = 0;

        DB::transaction(function () use ($version, $detected, &$created): void {
            $existingNames = AiFlowVariable::query()
                ->where('ai_flow_version_id', $version->id)
                ->pluck('name')
                ->map(static function ($name): string {
                    return (string) $name;
                })
                ->all();

            foreach ($detected['variables'] as $index => $variableName) {
                if (in_array($variableName, $existingNames, true)) {
                    continue;
                }

                AiFlowVariable::query()->create([
                    'ai_flow_version_id' => $version->id,
                    'ai_flow_step_id' => null,
                    'source_step_id' => null,
                    'name' => $variableName,
                    'label' => $this->suggestVariableLabel($variableName),
                    'scope' => AiFlowVariable::SCOPE_GLOBAL,
                    'input_type' => $this->suggestInputType($variableName),
                    'is_required' => true,
                    'help_text' => null,
                    'placeholder' => null,
                    'position' => $index + 1,
                    'default_value' => null,
                ]);

                $existingNames[] = $variableName;
                $created++;
            }
        });

        session()->flash(
            'ai_flow_variable_success',
            $created > 0
                ? sprintf('Sincronización completada. Variables nuevas creadas: %d.', $created)
                : 'Sincronización completada. No había variables nuevas.'
        );
    }

    public function editVariable(int $variableId): void
    {
        $this->authorizeAdmin();
        $version = $this->version();

        if (! $this->isDraftVersion($version)) {
            $this->variableFormError = 'Solo se pueden editar variables en versiones borrador.';

            return;
        }

        $variable = $version->variables()->whereKey($variableId)->firstOrFail();

        $this->editingVariableId = (int) $variable->id;
        $this->variable_name = (string) $variable->name;
        $this->variable_label = (string) $variable->label;
        $this->variable_input_type = (string) $variable->input_type;
        $this->variable_scope = (string) $variable->scope;
        $this->variable_is_required = (bool) $variable->is_required;
        $this->variable_help_text = (string) $variable->help_text;
        $this->variable_placeholder = (string) $variable->placeholder;
        $this->variable_default_value = (string) $variable->default_value;
        $this->variable_position = (int) $variable->position;
        $this->variable_ai_flow_step_id = $variable->ai_flow_step_id ? (string) $variable->ai_flow_step_id : '';
        $this->variable_source_step_id = $variable->source_step_id ? (string) $variable->source_step_id : '';
        $this->variableFormError = null;
        $this->resetErrorBag();
    }

    public function saveVariable(): void
    {
        $this->authorizeAdmin();
        $version = $this->version();

        if (! $this->isDraftVersion($version)) {
            $this->variableFormError = 'Solo se pueden editar variables en versiones borrador.';

            return;
        }

        if (! $this->editingVariableId) {
            $this->variableFormError = 'Selecciona una variable detectada para configurarla.';

            return;
        }

        $variable = $version->variables()->whereKey($this->editingVariableId)->firstOrFail();
        $validated = $this->validate($this->variableRules(), $this->variableMessages());
        $this->validateVariableStepSelection($version);

        $scope = $validated['variable_scope'];
        $stepId = $scope === AiFlowVariable::SCOPE_STEP ? (int) $validated['variable_ai_flow_step_id'] : null;
        $sourceStepId = $scope === AiFlowVariable::SCOPE_OUTPUT ? (int) $validated['variable_source_step_id'] : null;

        $variable->forceFill([
            'label' => $validated['variable_label'],
            'input_type' => $validated['variable_input_type'],
            'scope' => $scope,
            'is_required' => (bool) $validated['variable_is_required'],
            'help_text' => $validated['variable_help_text'] ?? null,
            'placeholder' => $validated['variable_placeholder'] ?? null,
            'default_value' => $validated['variable_default_value'] ?? null,
            'position' => (int) $validated['variable_position'],
            'ai_flow_step_id' => $stepId,
            'source_step_id' => $sourceStepId,
        ])->save();

        session()->flash('ai_flow_variable_success', 'Variable actualizada correctamente.');
        $this->resetVariableForm();
    }

    public function resetVariableForm(): void
    {
        $this->editingVariableId = null;
        $this->variable_name = '';
        $this->variable_label = '';
        $this->variable_input_type = AiFlowVariable::INPUT_TYPE_INPUT;
        $this->variable_scope = AiFlowVariable::SCOPE_GLOBAL;
        $this->variable_is_required = true;
        $this->variable_help_text = '';
        $this->variable_placeholder = '';
        $this->variable_default_value = '';
        $this->variable_position = 1;
        $this->variable_ai_flow_step_id = '';
        $this->variable_source_step_id = '';
        $this->variableFormError = null;
        $this->resetErrorBag();
    }

    public function render()
    {
        $this->authorizeAdmin();
        $flow = AiFlow::query()->findOrFail($this->flowId);
        $version = $this->version();
        $parser = app(AiFlowVariableParser::class);
        $steps = $version->steps()->get();
        $detectedVariables = $this->detectedVariablesForVersion($version, $parser);
        $variables = $version->variables()
            ->orderBy('position')
            ->orderBy('name')
            ->get();

        return view('livewire.admin.ai-flows.ai-flow-version-show', [
            'flow' => $flow,
            'version' => $version,
            'steps' => $steps,
            'stepRows' => $this->buildStepRows($steps, $parser),
            'dependencyOptions' => $this->dependencyOptions($steps),
            'promptPreview' => $parser->parse((string) $this->base_prompt),
            'variables' => $variables,
            'variableRows' => $this->buildVariableRows($variables, $detectedVariables['variables']),
            'detectedVersionVariables' => $detectedVariables['variables'],
            'versionInvalidTokens' => $detectedVariables['invalid_tokens'],
            'variableStepOptions' => $this->variableStepOptions($steps),
            'statusLabel' => AiFlowLabels::versionStatus($version->status),
            'isDraft' => $this->isDraftVersion($version),
        ]);
    }

    private function resetStepForm(): void
    {
        $this->editingStepId = null;
        $this->step_key = '';
        $this->name = '';
        $this->description = '';
        $this->position = $this->nextStepPosition();
        $this->recommended_gpt = '';
        $this->expected_output_name = '';
        $this->base_prompt = '';
        $this->is_active = true;
        $this->depends_on_step_id = '';
        $this->stepFormError = null;
        $this->resetErrorBag();
    }

    private function nextStepPosition(): int
    {
        if (! $this->versionId) {
            return 1;
        }

        return ((int) AiFlowStep::query()
            ->where('ai_flow_version_id', $this->versionId)
            ->max('position')) + 1;
    }

    private function stepRules(): array
    {
        return [
            'step_key' => [
                'required',
                'string',
                'max:120',
                'regex:/^[a-z][a-z0-9_-]*$/',
                Rule::unique('ai_flow_steps', 'step_key')
                    ->where('ai_flow_version_id', $this->versionId)
                    ->ignore($this->editingStepId),
            ],
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string'],
            'position' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('ai_flow_steps', 'position')
                    ->where('ai_flow_version_id', $this->versionId)
                    ->ignore($this->editingStepId),
            ],
            'recommended_gpt' => ['nullable', 'string', 'max:180'],
            'expected_output_name' => ['nullable', 'string', 'max:180'],
            'base_prompt' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'depends_on_step_id' => ['nullable'],
        ];
    }

    private function stepMessages(): array
    {
        return [
            'step_key.required' => 'La clave de la etapa es obligatoria.',
            'step_key.unique' => 'Ya existe una etapa con esta clave en la versión.',
            'step_key.regex' => 'La clave de la etapa debe estar en minúsculas, sin espacios ni tildes. Puede usar guion medio o guion bajo.',
            'step_key.max' => 'La clave de la etapa no debe superar 120 caracteres.',
            'name.required' => 'El nombre de la etapa es obligatorio.',
            'name.max' => 'El nombre de la etapa no debe superar 180 caracteres.',
            'position.required' => 'La posición es obligatoria.',
            'position.integer' => 'La posición debe ser numérica.',
            'position.min' => 'La posición debe ser mayor o igual a 1.',
            'position.unique' => 'Ya existe una etapa con esta posición en la versión.',
            'recommended_gpt.max' => 'El GPT recomendado no debe superar 180 caracteres.',
            'expected_output_name.max' => 'La salida esperada no debe superar 180 caracteres.',
        ];
    }

    private function variableRules(): array
    {
        return [
            'variable_label' => ['required', 'string', 'max:180'],
            'variable_input_type' => ['required', Rule::in(AiFlowVariable::INPUT_TYPES)],
            'variable_scope' => ['required', Rule::in(AiFlowVariable::SCOPES)],
            'variable_is_required' => ['boolean'],
            'variable_help_text' => ['nullable', 'string'],
            'variable_placeholder' => ['nullable', 'string', 'max:255'],
            'variable_default_value' => ['nullable', 'string'],
            'variable_position' => ['required', 'integer', 'min:1'],
            'variable_ai_flow_step_id' => ['nullable'],
            'variable_source_step_id' => ['nullable'],
        ];
    }

    private function variableMessages(): array
    {
        return [
            'variable_label.required' => 'La etiqueta de la variable es obligatoria.',
            'variable_label.max' => 'La etiqueta no debe superar 180 caracteres.',
            'variable_input_type.required' => 'El tipo de campo es obligatorio.',
            'variable_input_type.in' => 'El tipo de campo no es válido.',
            'variable_scope.required' => 'El alcance de la variable es obligatorio.',
            'variable_scope.in' => 'El alcance de la variable no es válido.',
            'variable_position.required' => 'La posición de la variable es obligatoria.',
            'variable_position.integer' => 'La posición de la variable debe ser numérica.',
            'variable_position.min' => 'La posición de la variable debe ser mayor o igual a 1.',
        ];
    }

    private function validateDependencySelection(AiFlowVersion $version): void
    {
        $dependencyId = (int) $this->depends_on_step_id;

        if ($dependencyId <= 0) {
            return;
        }

        $dependency = AiFlowStep::query()->find($dependencyId);

        if (! $dependency instanceof AiFlowStep || (int) $dependency->ai_flow_version_id !== (int) $version->id) {
            throw ValidationException::withMessages([
                'depends_on_step_id' => 'La dependencia debe pertenecer a la misma versión.',
            ]);
        }

        if ($this->editingStepId && (int) $dependency->id === (int) $this->editingStepId) {
            throw ValidationException::withMessages([
                'depends_on_step_id' => 'Una etapa no puede depender de sí misma.',
            ]);
        }

        if ((int) $dependency->position >= (int) $this->position) {
            throw ValidationException::withMessages([
                'depends_on_step_id' => 'La dependencia debe ser una etapa anterior.',
            ]);
        }
    }

    private function validateVariableStepSelection(AiFlowVersion $version): void
    {
        if ($this->variable_scope === AiFlowVariable::SCOPE_STEP) {
            $stepId = (int) $this->variable_ai_flow_step_id;

            if ($stepId <= 0) {
                throw ValidationException::withMessages([
                    'variable_ai_flow_step_id' => 'Las variables de etapa requieren una etapa válida.',
                ]);
            }

            $step = AiFlowStep::query()->find($stepId);

            if (! $step instanceof AiFlowStep || (int) $step->ai_flow_version_id !== (int) $version->id) {
                throw ValidationException::withMessages([
                    'variable_ai_flow_step_id' => 'La etapa debe pertenecer a la misma versión.',
                ]);
            }
        }

        if ($this->variable_scope === AiFlowVariable::SCOPE_OUTPUT) {
            $sourceStepId = (int) $this->variable_source_step_id;

            if ($sourceStepId <= 0) {
                throw ValidationException::withMessages([
                    'variable_source_step_id' => 'Las variables de resultado requieren una etapa fuente válida.',
                ]);
            }

            $sourceStep = AiFlowStep::query()->find($sourceStepId);

            if (! $sourceStep instanceof AiFlowStep || (int) $sourceStep->ai_flow_version_id !== (int) $version->id) {
                throw ValidationException::withMessages([
                    'variable_source_step_id' => 'La etapa fuente debe pertenecer a la misma versión.',
                ]);
            }
        }
    }

    /**
     * @return array{variables: array<int, string>, invalid_tokens: array<int, string>}
     */
    private function detectedVariablesForVersion(AiFlowVersion $version, AiFlowVariableParser $parser): array
    {
        $steps = $version->steps()
            ->where('is_active', true)
            ->orderBy('position')
            ->get();

        $variables = [];
        $invalidTokens = [];

        foreach ($steps as $step) {
            $parsed = $parser->parse((string) $step->base_prompt);

            foreach ($parsed['variables'] as $variableName) {
                if (! in_array($variableName, $variables, true)) {
                    $variables[] = $variableName;
                }
            }

            foreach ($parsed['invalid_tokens'] as $invalidToken) {
                if (! in_array($invalidToken, $invalidTokens, true)) {
                    $invalidTokens[] = $invalidToken;
                }
            }
        }

        return [
            'variables' => $variables,
            'invalid_tokens' => $invalidTokens,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, AiFlowVariable>  $variables
     * @param  array<int, string>  $detectedNames
     * @return array<int, array{variable: AiFlowVariable, is_used: bool}>
     */
    private function buildVariableRows($variables, array $detectedNames): array
    {
        return $variables->map(static function (AiFlowVariable $variable) use ($detectedNames): array {
            return [
                'variable' => $variable,
                'is_used' => in_array((string) $variable->name, $detectedNames, true),
            ];
        })->all();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, AiFlowStep>  $steps
     * @return array<int, array{id:int,name:string,position:int}>
     */
    private function variableStepOptions($steps): array
    {
        return $steps
            ->sortBy('position')
            ->map(static function (AiFlowStep $step): array {
                return [
                    'id' => (int) $step->id,
                    'name' => (string) $step->name,
                    'position' => (int) $step->position,
                ];
            })
            ->values()
            ->all();
    }

    private function suggestVariableLabel(string $name): string
    {
        $label = str_replace('_', ' ', $name);
        $label = ucfirst($label);

        return $label;
    }

    private function suggestInputType(string $name): string
    {
        $textareaHints = [
            'objetivo',
            'observaciones',
            'descripcion',
            'publico',
            'servicios',
            'competidores',
            'canales',
            'restricciones',
            'temporadas',
            'brief',
            'informacion',
            'sitemap',
            'contexto',
        ];

        foreach ($textareaHints as $hint) {
            if (strpos($name, $hint) !== false) {
                return AiFlowVariable::INPUT_TYPE_TEXTAREA;
            }
        }

        return AiFlowVariable::INPUT_TYPE_INPUT;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, AiFlowStep>  $steps
     * @return array<int, array{step: AiFlowStep, variables_count: int, invalid_tokens_count: int}>
     */
    private function buildStepRows($steps, AiFlowVariableParser $parser): array
    {
        return $steps->map(static function (AiFlowStep $step) use ($parser): array {
            $parsed = $parser->parse((string) $step->base_prompt);

            return [
                'step' => $step,
                'variables_count' => count($parsed['variables']),
                'invalid_tokens_count' => count($parsed['invalid_tokens']),
            ];
        })->all();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, AiFlowStep>  $steps
     * @return array<int, array{id:int,name:string,position:int}>
     */
    private function dependencyOptions($steps): array
    {
        $currentPosition = (int) $this->position;
        $editingStepId = (int) $this->editingStepId;

        return $steps
            ->filter(static function (AiFlowStep $step) use ($currentPosition, $editingStepId): bool {
                return (int) $step->position < $currentPosition
                    && (int) $step->id !== $editingStepId;
            })
            ->map(static function (AiFlowStep $step): array {
                return [
                    'id' => (int) $step->id,
                    'name' => (string) $step->name,
                    'position' => (int) $step->position,
                ];
            })
            ->values()
            ->all();
    }

    private function isDraftVersion(AiFlowVersion $version): bool
    {
        return $version->status === AiFlowVersion::STATUS_DRAFT;
    }

    private function version(): AiFlowVersion
    {
        return AiFlowVersion::query()
            ->where('ai_flow_id', $this->flowId)
            ->whereKey($this->versionId)
            ->firstOrFail();
    }

    private function authorizeAdmin(): void
    {
        abort_unless(auth()->check() && auth()->user()->isAdmin(), 403);
    }
}
