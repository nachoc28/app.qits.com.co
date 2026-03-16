<?php

namespace App\Services\IntegrationSecurity;

/**
 * Resultado estructurado de la evaluación de rate limiting.
 */
class IntegrationRateLimitResult
{
    public bool $allowed;
    public string $profile;
    public ?string $violatedBucket;
    public ?int $retryAfterSeconds;
    public ?int $limit;
    public ?int $windowSeconds;

    /** @var array<string, int> */
    public array $attemptsByBucket;

    /** @var array<string, mixed> */
    public array $meta;

    /**
     * @param array<string, int> $attemptsByBucket
     * @param array<string, mixed> $meta
     */
    public function __construct(
        bool $allowed,
        string $profile,
        ?string $violatedBucket = null,
        ?int $retryAfterSeconds = null,
        ?int $limit = null,
        ?int $windowSeconds = null,
        array $attemptsByBucket = [],
        array $meta = []
    ) {
        $this->allowed = $allowed;
        $this->profile = $profile;
        $this->violatedBucket = $violatedBucket;
        $this->retryAfterSeconds = $retryAfterSeconds;
        $this->limit = $limit;
        $this->windowSeconds = $windowSeconds;
        $this->attemptsByBucket = $attemptsByBucket;
        $this->meta = $meta;
    }

    public function isExceeded(): bool
    {
        return ! $this->allowed;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'allowed' => $this->allowed,
            'profile' => $this->profile,
            'violated_bucket' => $this->violatedBucket,
            'retry_after_seconds' => $this->retryAfterSeconds,
            'limit' => $this->limit,
            'window_seconds' => $this->windowSeconds,
            'attempts_by_bucket' => $this->attemptsByBucket,
            'meta' => $this->meta,
        ];
    }
}
