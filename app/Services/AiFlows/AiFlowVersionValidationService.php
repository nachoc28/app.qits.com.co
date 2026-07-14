<?php

namespace App\Services\AiFlows;

use App\Models\AiFlowStep;
use App\Models\AiFlowStepDependency;
use App\Models\AiFlowVariable;
use App\Models\AiFlowVersion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AiFlowVersionValidationService
{
    private const VALID_NAME_PATTERN = '/^[a-z][a-z0-9_]*$/';

    /** @var AiFlowVariableParser */
    private $parser;

    public function __construct(AiFlowVariableParser $parser)
    {
        $this->parser = $parser;
    }

    /**
     * @return array{can_publish: bool, errors: array<int, string>, warnings: array<int, string>, detected_variables: array<int, string>}
     */
    public function validateForPublication(AiFlowVersion $version): array
    {
        $version = $version->fresh() ?: $version;
        $errors = [];
        $warnings = [];

        if ($version->status !== AiFlowVersion::STATUS_DRAFT) {
            $errors[] = 'Solo se pueden publicar versiones en estado borrador.';
        }

        $activeSteps = AiFlowStep::query()
            ->where('ai_flow_version_id', $version->id)
            ->where('is_active', true)
            ->orderBy('position')
            ->get();

        if ($activeSteps->isEmpty()) {
            $errors[] = 'La versión debe tener al menos una etapa activa.';
        }

        $detectedVariables = $this->validateStepsAndCollectVariables($activeSteps, $errors);
        $configuredVariables = AiFlowVariable::query()
            ->where('ai_flow_version_id', $version->id)
            ->orderBy('position')
            ->get();

        $this->validateConfiguredVariables($version, $configuredVariables, $detectedVariables, $errors, $warnings);
        $this->validateDependencies($version, $errors);

        return [
            'can_publish' => count($errors) === 0,
            'errors' => $errors,
            'warnings' => $warnings,
            'detected_variables' => $detectedVariables,
        ];
    }

    /**
     * @param  Collection<int, AiFlowStep>  $activeSteps
     * @param  array<int, string>  $errors
     * @return array<int, string>
     */
    private function validateStepsAndCollectVariables(Collection $activeSteps, array &$errors): array
    {
        $detectedVariables = [];

        foreach ($activeSteps as $step) {
            if (trim((string) $step->base_prompt) === '') {
                $errors[] = sprintf('La etapa "%s" debe tener un prompt base.', $step->name);
                continue;
            }

            $parsed = $this->parser->parse((string) $step->base_prompt);

            foreach ($parsed['invalid_tokens'] as $token) {
                $label = $token === '' ? 'vacío' : $token;
                $errors[] = sprintf('La etapa "%s" contiene una variable inválida: {{%s}}.', $step->name, $label);
            }

            foreach ($parsed['variables'] as $variableName) {
                if (! in_array($variableName, $detectedVariables, true)) {
                    $detectedVariables[] = $variableName;
                }
            }
        }

        return $detectedVariables;
    }

    /**
     * @param  Collection<int, AiFlowVariable>  $configuredVariables
     * @param  array<int, string>  $detectedVariables
     * @param  array<int, string>  $errors
     * @param  array<int, string>  $warnings
     */
    private function validateConfiguredVariables(
        AiFlowVersion $version,
        Collection $configuredVariables,
        array $detectedVariables,
        array &$errors,
        array &$warnings
    ): void {
        $configuredNames = $configuredVariables
            ->pluck('name')
            ->map(static function ($name): string {
                return (string) $name;
            })
            ->all();

        foreach ($detectedVariables as $variableName) {
            if (! in_array($variableName, $configuredNames, true)) {
                $errors[] = sprintf('La variable "%s" aparece en prompts pero no está configurada.', $variableName);
            }
        }

        foreach ($configuredVariables as $variable) {
            if (! preg_match(self::VALID_NAME_PATTERN, (string) $variable->name)) {
                $errors[] = sprintf('La variable configurada "%s" tiene un nombre inválido.', $variable->name);
            }

            if (! in_array((string) $variable->name, $detectedVariables, true)) {
                $warnings[] = sprintf('La variable "%s" está configurada pero no aparece en ningún prompt activo.', $variable->name);
            }

            if ($variable->scope === AiFlowVariable::SCOPE_OUTPUT && $variable->source_step_id === null) {
                $errors[] = sprintf('La variable de resultado "%s" debe tener una etapa fuente.', $variable->name);
            }

            if ($variable->source_step_id !== null) {
                $sourceStep = AiFlowStep::query()->find($variable->source_step_id);

                if ($sourceStep instanceof AiFlowStep && (int) $sourceStep->ai_flow_version_id !== (int) $version->id) {
                    $errors[] = sprintf('La variable "%s" referencia una etapa fuente de otra versión.', $variable->name);
                }
            }

            if ($variable->ai_flow_step_id !== null) {
                $step = AiFlowStep::query()->find($variable->ai_flow_step_id);

                if ($step instanceof AiFlowStep && (int) $step->ai_flow_version_id !== (int) $version->id) {
                    $errors[] = sprintf('La variable "%s" está asociada a una etapa de otra versión.', $variable->name);
                }
            }
        }

        $duplicates = DB::table('ai_flow_variables')
            ->select('name', DB::raw('count(*) as aggregate_count'))
            ->where('ai_flow_version_id', $version->id)
            ->groupBy('name')
            ->havingRaw('count(*) > 1')
            ->pluck('aggregate_count', 'name');

        foreach ($duplicates as $name => $count) {
            $errors[] = sprintf('La variable "%s" está duplicada en la versión.', $name);
        }
    }

    /**
     * @param  array<int, string>  $errors
     */
    private function validateDependencies(AiFlowVersion $version, array &$errors): void
    {
        $dependencies = AiFlowStepDependency::query()
            ->with(['step', 'dependsOnStep'])
            ->whereHas('step', function ($query) use ($version): void {
                $query->where('ai_flow_version_id', $version->id);
            })
            ->get();

        foreach ($dependencies as $dependency) {
            if (! $dependency->step instanceof AiFlowStep || ! $dependency->dependsOnStep instanceof AiFlowStep) {
                continue;
            }

            if ((int) $dependency->dependsOnStep->ai_flow_version_id !== (int) $version->id) {
                $errors[] = sprintf(
                    'La dependencia de la etapa "%s" referencia una etapa de otra versión.',
                    $dependency->step->name
                );
            }
        }
    }
}
