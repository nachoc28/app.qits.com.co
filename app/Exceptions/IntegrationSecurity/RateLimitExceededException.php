<?php

namespace App\Exceptions\IntegrationSecurity;

use RuntimeException;

/**
 * Se lanza cuando una solicitud excede los límites de tráfico
 * definidos para la integración, empresa, IP o endpoint.
 */
class RateLimitExceededException extends RuntimeException
{
    private string $profile;
    private string $bucket;
    private int $retryAfterSeconds;
    private int $limit;
    private int $windowSeconds;

    public function __construct(
        string $profile,
        string $bucket,
        int $retryAfterSeconds,
        int $limit,
        int $windowSeconds
    ) {
        $this->profile = $profile;
        $this->bucket = $bucket;
        $this->retryAfterSeconds = $retryAfterSeconds;
        $this->limit = $limit;
        $this->windowSeconds = $windowSeconds;

        parent::__construct(
            "Rate limit exceeded for bucket [{$bucket}] under profile [{$profile}]."
        );
    }

    public function getProfile(): string
    {
        return $this->profile;
    }

    public function getBucket(): string
    {
        return $this->bucket;
    }

    public function getRetryAfterSeconds(): int
    {
        return $this->retryAfterSeconds;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function getWindowSeconds(): int
    {
        return $this->windowSeconds;
    }
}
