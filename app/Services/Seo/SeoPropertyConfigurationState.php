<?php

namespace App\Services\Seo;

use App\Models\EmpresaSeoProperty;

/**
 * Resultado de evaluación de configuración SEO por empresa.
 */
final class SeoPropertyConfigurationState
{
    public const STATUS_NOT_CONFIGURED = 'not_configured';
    public const STATUS_PARTIALLY_CONFIGURED = 'partially_configured';
    public const STATUS_CONFIGURED = 'configured';

    /** @var string */
    public $status;

    /** @var EmpresaSeoProperty|null */
    public $property;

    /** @var array<int, string> */
    public $errors = [];

    /** @var array<int, string> */
    public $warnings = [];

    /**
     * @param array<int, string> $errors
     * @param array<int, string> $warnings
     */
    public function __construct(string $status, ?EmpresaSeoProperty $property = null, array $errors = [], array $warnings = [])
    {
        $this->status = $status;
        $this->property = $property;
        $this->errors = $errors;
        $this->warnings = $warnings;
    }

    public function isNotConfigured(): bool
    {
        return $this->status === self::STATUS_NOT_CONFIGURED;
    }

    public function isPartiallyConfigured(): bool
    {
        return $this->status === self::STATUS_PARTIALLY_CONFIGURED;
    }

    public function isConfigured(): bool
    {
        return $this->status === self::STATUS_CONFIGURED;
    }

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    public function hasWarnings(): bool
    {
        return $this->warnings !== [];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'empresa_id' => $this->property ? (int) $this->property->empresa_id : null,
            'property_id' => $this->property ? (int) $this->property->id : null,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
        ];
    }
}
