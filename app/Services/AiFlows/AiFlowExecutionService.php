<?php

namespace App\Services\AiFlows;

use App\Models\AiFlow;
use App\Models\AiFlowExecution;
use App\Models\AiFlowExecutionStep;
use App\Models\AiFlowStep;
use App\Models\AiFlowStepDependency;
use App\Models\AiFlowVersion;
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AiFlowExecutionService
{
    public const VISUAL_STATUS_BLOCKED = 'blocked';

    public function createExecution(Empresa $empresa, AiFlow $flow, string $title, User $user): AiFlowExecution
    {
        $flow = $flow->fresh() ?: $flow;
        $publishedVersion = $this->publishedVersionForFlow($flow);

        if (! $flow->is_active || ! $publishedVersion instanceof AiFlowVersion) {
            throw new InvalidArgumentException('El flujo seleccionado no tiene una versión publicada disponible.');
        }

        $activeSteps = $publishedVersion->steps()
            ->where('is_active', true)
            ->orderBy('position')
            ->get();

        if ($activeSteps->isEmpty()) {
            throw new InvalidArgumentException('La versión publicada no tiene etapas activas.');
        }

        return DB::transaction(function () use ($empresa, $flow, $publishedVersion, $title, $user, $activeSteps): AiFlowExecution {
            $execution = AiFlowExecution::query()->create([
                'empresa_id' => $empresa->id,
                'ai_flow_id' => $flow->id,
                'ai_flow_version_id' => $publishedVersion->id,
                'title' => $title,
                'status' => AiFlowExecution::STATUS_IN_PROGRESS,
                'started_by' => $user->id,
                'started_at' => now(),
                'completed_at' => null,
            ]);

            foreach ($activeSteps as $step) {
                AiFlowExecutionStep::query()->create([
                    'ai_flow_execution_id' => $execution->id,
                    'ai_flow_step_id' => $step->id,
                    'status' => AiFlowExecutionStep::STATUS_PENDING,
                    'started_at' => null,
                    'started_by' => null,
                    'completed_at' => null,
                    'completed_by' => null,
                ]);
            }

            return $execution->fresh(['empresa', 'flow', 'version', 'steps.step']);
        });
    }

    /**
     * @return array<int, array{execution_step: AiFlowExecutionStep, visual_status: string, visual_label: string, is_blocked: bool}>
     */
    public function stepProgressRows(AiFlowExecution $execution): array
    {
        $execution->loadMissing(['steps.step']);

        $executionSteps = $execution->steps
            ->sortBy(static function (AiFlowExecutionStep $executionStep): int {
                return (int) optional($executionStep->step)->position;
            })
            ->values();

        $rows = [];
        $byStepId = $executionSteps->keyBy('ai_flow_step_id');

        foreach ($executionSteps as $index => $executionStep) {
            $isBlocked = $this->isExecutionStepBlocked($executionStep, $executionSteps, $byStepId, $index);
            $visualStatus = $isBlocked ? self::VISUAL_STATUS_BLOCKED : (string) $executionStep->status;

            $rows[] = [
                'execution_step' => $executionStep,
                'visual_status' => $visualStatus,
                'visual_label' => $this->visualStatusLabel($visualStatus),
                'is_blocked' => $isBlocked,
            ];
        }

        return $rows;
    }

    public function publishedVersionForFlow(AiFlow $flow): ?AiFlowVersion
    {
        return AiFlowVersion::query()
            ->where('ai_flow_id', $flow->id)
            ->where('status', AiFlowVersion::STATUS_PUBLISHED)
            ->orderByDesc('version_number')
            ->first();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, AiFlowExecutionStep>  $orderedExecutionSteps
     * @param  \Illuminate\Support\Collection<int, AiFlowExecutionStep>  $byStepId
     */
    private function isExecutionStepBlocked(
        AiFlowExecutionStep $executionStep,
        $orderedExecutionSteps,
        $byStepId,
        int $index
    ): bool {
        if ($executionStep->status !== AiFlowExecutionStep::STATUS_PENDING) {
            return false;
        }

        $step = $executionStep->step;

        if (! $step instanceof AiFlowStep) {
            return true;
        }

        $dependencies = AiFlowStepDependency::query()
            ->where('ai_flow_step_id', $step->id)
            ->pluck('depends_on_step_id')
            ->map(static function ($stepId): int {
                return (int) $stepId;
            })
            ->all();

        if (count($dependencies) === 0) {
            if ($index === 0) {
                return false;
            }

            /** @var AiFlowExecutionStep|null $previousExecutionStep */
            $previousExecutionStep = $orderedExecutionSteps->get($index - 1);

            return ! $previousExecutionStep
                || $previousExecutionStep->status !== AiFlowExecutionStep::STATUS_COMPLETED;
        }

        foreach ($dependencies as $dependencyStepId) {
            /** @var AiFlowExecutionStep|null $dependencyExecutionStep */
            $dependencyExecutionStep = $byStepId->get($dependencyStepId);

            if (! $dependencyExecutionStep || $dependencyExecutionStep->status !== AiFlowExecutionStep::STATUS_COMPLETED) {
                return true;
            }
        }

        return false;
    }

    private function visualStatusLabel(string $status): string
    {
        if ($status === self::VISUAL_STATUS_BLOCKED) {
            return 'Bloqueada';
        }

        if ($status === AiFlowExecutionStep::STATUS_IN_PROGRESS) {
            return 'En proceso';
        }

        if ($status === AiFlowExecutionStep::STATUS_COMPLETED) {
            return 'Completada';
        }

        return 'Pendiente';
    }
}
