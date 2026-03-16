<?php

namespace App\Services\Google;

/**
 * DTO de estado de salud para la conexion global de Google.
 */
class GoogleConnectionHealthStatus
{
    public const STATE_NOT_CONFIGURED = 'not_configured';
    public const STATE_PARTIALLY_CONFIGURED = 'partially_configured';
    public const STATE_CONNECTED = 'connected';
    public const STATE_FAILED = 'failed';

    /** @var string */
    public $state;

    /** @var array<string, bool> */
    public $checks = [];

    /** @var array<int, string> */
    public $missing = [];

    /** @var array<int, string> */
    public $errors = [];

    /** @var array<string, mixed> */
    public $meta = [];

    /**
     * @param array<string, bool> $checks
     * @param array<int, string> $missing
     * @param array<int, string> $errors
     * @param array<string, mixed> $meta
     */
    public function __construct(
        string $state,
        array $checks = [],
        array $missing = [],
        array $errors = [],
        array $meta = []
    ) {
        $this->state = $state;
        $this->checks = $checks;
        $this->missing = $missing;
        $this->errors = $errors;
        $this->meta = $meta;
    }

    public function isConnected(): bool
    {
        return $this->state === self::STATE_CONNECTED;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            isset($data['state']) ? (string) $data['state'] : self::STATE_NOT_CONFIGURED,
            isset($data['checks']) && is_array($data['checks']) ? $data['checks'] : [],
            isset($data['missing']) && is_array($data['missing']) ? $data['missing'] : [],
            isset($data['errors']) && is_array($data['errors']) ? $data['errors'] : [],
            isset($data['meta']) && is_array($data['meta']) ? $data['meta'] : []
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'state' => $this->state,
            'checks' => $this->checks,
            'missing' => $this->missing,
            'errors' => $this->errors,
            'meta' => $this->meta,
        ];
    }
}
