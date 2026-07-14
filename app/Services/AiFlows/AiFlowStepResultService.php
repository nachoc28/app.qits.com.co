<?php

namespace App\Services\AiFlows;

use App\Models\AiFlowExecutionStep;
use App\Models\AiFlowStepGeneration;
use App\Models\AiFlowStepResult;
use App\Models\User;
use InvalidArgumentException;

class AiFlowStepResultService
{
    public function saveResult(AiFlowExecutionStep $executionStep, string $resultText, User $user): AiFlowStepResult
    {
        $resultText = trim($resultText);

        if ($resultText === '') {
            throw new InvalidArgumentException('El resultado del GPT no puede estar vacío.');
        }

        $latestGeneration = $this->latestGeneration($executionStep);

        if (! $latestGeneration instanceof AiFlowStepGeneration) {
            throw new InvalidArgumentException('Primero debes generar un prompt antes de guardar el resultado.');
        }

        return AiFlowStepResult::query()->create([
            'ai_flow_execution_step_id' => $executionStep->id,
            'ai_flow_step_generation_id' => $latestGeneration->id,
            'result_text' => $resultText,
            'saved_by' => $user->id,
            'saved_at' => now(),
        ]);
    }

    public function latestGeneration(AiFlowExecutionStep $executionStep): ?AiFlowStepGeneration
    {
        return AiFlowStepGeneration::query()
            ->where('ai_flow_execution_step_id', $executionStep->id)
            ->orderByDesc('generated_at')
            ->orderByDesc('id')
            ->first();
    }

    public function latestResult(AiFlowExecutionStep $executionStep): ?AiFlowStepResult
    {
        return AiFlowStepResult::query()
            ->where('ai_flow_execution_step_id', $executionStep->id)
            ->orderByDesc('saved_at')
            ->orderByDesc('id')
            ->first();
    }
}
