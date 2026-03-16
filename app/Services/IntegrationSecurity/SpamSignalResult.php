<?php

namespace App\Services\IntegrationSecurity;

/**
 * Resultado estructurado del análisis de señales de spam o tráfico sospechoso.
 */
class SpamSignalResult
{
    public bool $flagged;
    public int $score;

    /** @var string[] */
    public array $reasons;

    public string $fingerprint;

    /** @var array<string, mixed> */
    public array $meta;

    /**
     * @param string[] $reasons
     * @param array<string, mixed> $meta
     */
    public function __construct(bool $flagged, int $score, array $reasons, string $fingerprint, array $meta = [])
    {
        $this->flagged = $flagged;
        $this->score = $score;
        $this->reasons = $reasons;
        $this->fingerprint = $fingerprint;
        $this->meta = $meta;
    }

    public function hasReason(string $reason): bool
    {
        return in_array($reason, $this->reasons, true);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'flagged' => $this->flagged,
            'score' => $this->score,
            'reasons' => $this->reasons,
            'fingerprint' => $this->fingerprint,
            'meta' => $this->meta,
        ];
    }
}
