<?php

namespace App\Services\AiFlows;

use App\Models\AiFlowExecution;
use App\Models\AiFlowExecutionStep;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AiFlowStepCompletionService
{
    /** @var AiFlowStepResultService */
    private $resultService;

    public function __construct(AiFlowStepResultService $resultService)
    {
        $this->resultService = $resultService;
    }

    public function completeStep(AiFlowExecutionStep $executionStep, User $user): void
    {
        if (! $this->resultService->latestResult($executionStep)) {
            throw new InvalidArgumentException('Para completar la etapa debes guardar primero un resultado del GPT.');
        }

        DB::transaction(function () use ($executionStep, $user): void {
            $executionStep->forceFill([
                'status' => AiFlowExecutionStep::STATUS_COMPLETED,
                'completed_by' => $user->id,
                'completed_at' => now(),
            ])->save();

            $execution = $executionStep->execution()->lockForUpdate()->first();

            if (! $execution instanceof AiFlowExecution) {
                return;
            }

            $totalSteps = AiFlowExecutionStep::query()
                ->where('ai_flow_execution_id', $execution->id)
                ->count();

            $completedSteps = AiFlowExecutionStep::query()
                ->where('ai_flow_execution_id', $execution->id)
                ->where('status', AiFlowExecutionStep::STATUS_COMPLETED)
                ->count();

            if ($totalSteps > 0 && $totalSteps === $completedSteps) {
                $execution->forceFill([
                    'status' => AiFlowExecution::STATUS_COMPLETED,
                    'completed_at' => now(),
                ])->save();
            }
        });
    }
}
