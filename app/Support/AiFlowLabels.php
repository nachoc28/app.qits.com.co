<?php

namespace App\Support;

use App\Models\AiFlowExecution;
use App\Models\AiFlowExecutionStep;
use App\Models\AiFlowStrategicOutput;
use App\Models\AiFlowVariable;
use App\Models\AiFlowVersion;

class AiFlowLabels
{
    /**
     * @return array<string, string>
     */
    public static function versionStatusOptions(): array
    {
        return [
            AiFlowVersion::STATUS_DRAFT => 'Borrador',
            AiFlowVersion::STATUS_PUBLISHED => 'Publicada',
            AiFlowVersion::STATUS_ARCHIVED => 'Archivada',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function executionStatusOptions(): array
    {
        return [
            AiFlowExecution::STATUS_PENDING => 'Pendiente',
            AiFlowExecution::STATUS_IN_PROGRESS => 'En proceso',
            AiFlowExecution::STATUS_COMPLETED => 'Completada',
            AiFlowExecution::STATUS_CANCELLED => 'Cancelada',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function executionStepStatusOptions(): array
    {
        return [
            AiFlowExecutionStep::STATUS_PENDING => 'Pendiente',
            AiFlowExecutionStep::STATUS_IN_PROGRESS => 'En proceso',
            AiFlowExecutionStep::STATUS_COMPLETED => 'Completada',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function variableScopeOptions(): array
    {
        return [
            AiFlowVariable::SCOPE_GLOBAL => 'Global',
            AiFlowVariable::SCOPE_STEP => 'Etapa',
            AiFlowVariable::SCOPE_OUTPUT => 'Resultado',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function inputTypeOptions(): array
    {
        return [
            AiFlowVariable::INPUT_TYPE_INPUT => 'Campo corto',
            AiFlowVariable::INPUT_TYPE_TEXTAREA => 'Texto largo',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function strategicOutputTypeOptions(): array
    {
        return [
            AiFlowStrategicOutput::TYPE_STRATEGIC_REPORT => 'Informe estratégico',
            AiFlowStrategicOutput::TYPE_EXECUTIVE_SUMMARY => 'Resumen ejecutivo',
            AiFlowStrategicOutput::TYPE_CURRENT_STRATEGIC_BASE => 'Base estratégica vigente',
        ];
    }

    public static function versionStatus(?string $status): string
    {
        return self::label(self::versionStatusOptions(), $status);
    }

    public static function executionStatus(?string $status): string
    {
        return self::label(self::executionStatusOptions(), $status);
    }

    public static function executionStepStatus(?string $status): string
    {
        return self::label(self::executionStepStatusOptions(), $status);
    }

    public static function variableScope(?string $scope): string
    {
        return self::label(self::variableScopeOptions(), $scope);
    }

    public static function inputType(?string $type): string
    {
        return self::label(self::inputTypeOptions(), $type);
    }

    public static function strategicOutputType(?string $type): string
    {
        return self::label(self::strategicOutputTypeOptions(), $type);
    }

    /**
     * @param  array<string, string>  $options
     */
    private static function label(array $options, ?string $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        return $options[$value] ?? $value;
    }
}
