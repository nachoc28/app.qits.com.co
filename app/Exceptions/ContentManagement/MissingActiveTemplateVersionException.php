<?php

namespace App\Exceptions\ContentManagement;

use RuntimeException;

class MissingActiveTemplateVersionException extends RuntimeException
{
    /**
     * @var array<string, mixed>
     */
    private array $context;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(string $stepType, array $context = [])
    {
        parent::__construct(sprintf(
            'Active master template version for [%s] step is not available.',
            $stepType
        ));

        $this->context = array_merge([
            'step_type' => $stepType,
        ], $context);
    }

    public function userMessage(): string
    {
        return 'La plantilla necesaria para este paso no está configurada. Contacta al administrador.';
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->context;
    }
}
