<?php

namespace App\Services\AiFlows;

use App\Models\AiFlowExecutionStep;
use App\Models\AiFlowStepResult;
use App\Models\AiFlowStrategicOutput;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AiFlowStrategicOutputService
{
    public function markResult(AiFlowStepResult $result, string $type, string $title, User $user): AiFlowStrategicOutput
    {
        $result->loadMissing(['executionStep.execution.empresa']);
        $executionStep = $result->executionStep;

        if (! $executionStep instanceof AiFlowExecutionStep) {
            throw new InvalidArgumentException('El resultado no tiene una etapa válida.');
        }

        if ($executionStep->status !== AiFlowExecutionStep::STATUS_COMPLETED) {
            throw new InvalidArgumentException('Solo puedes marcar resultados de etapas completadas.');
        }

        $content = trim((string) $result->result_text);

        if ($content === '') {
            throw new InvalidArgumentException('El resultado estratégico no puede estar vacío.');
        }

        if (! in_array($type, AiFlowStrategicOutput::TYPES, true)) {
            throw new InvalidArgumentException('El tipo de resultado estratégico no es válido.');
        }

        $title = trim($title);

        if ($title === '') {
            throw new InvalidArgumentException('El título del resultado estratégico es obligatorio.');
        }

        $execution = $executionStep->execution;
        $empresa = $execution ? $execution->empresa : null;

        if (! $execution || ! $empresa) {
            throw new InvalidArgumentException('No se pudo resolver la empresa de la ejecución.');
        }

        return DB::transaction(function () use ($result, $executionStep, $execution, $empresa, $type, $title, $content, $user): AiFlowStrategicOutput {
            AiFlowStrategicOutput::query()
                ->where('empresa_id', $empresa->id)
                ->where('type', $type)
                ->where('is_current', true)
                ->update(['is_current' => false]);

            return AiFlowStrategicOutput::query()->create([
                'empresa_id' => $empresa->id,
                'ai_flow_execution_id' => $execution->id,
                'ai_flow_execution_step_id' => $executionStep->id,
                'ai_flow_step_result_id' => $result->id,
                'type' => $type,
                'title' => $title,
                'content' => $content,
                'is_current' => true,
                'marked_by' => $user->id,
                'marked_at' => now(),
            ]);
        });
    }
}
