<?php

namespace App\Services\IntegrationSecurity;

use App\Models\EmpresaIntegration;
use App\Services\IntegrationSecurity\PayloadFingerprintService;
use App\Services\IntegrationSecurity\SecurityEventLogger;
use App\Services\IntegrationSecurity\SpamSignalResult;
use App\Support\IntegrationSecurity\IntegrationEventType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Capa básica y reutilizable de señales anti-spam / hardening.
 *
 * No bloquea por sí sola: entrega un resultado estructurado para que cada
 * endpoint o middleware decida si permite, rechaza o deriva a revisión.
 */
class SpamSignalService
{
    /** @var PayloadFingerprintService */
    private $fingerprintService;

    /** @var SecurityEventLogger */
    private $logger;

    public function __construct(PayloadFingerprintService $fingerprintService, SecurityEventLogger $logger)
    {
        $this->fingerprintService = $fingerprintService;
        $this->logger = $logger;
    }

    /**
     * Analiza request y devuelve señales de hardening.
     *
     * @param array<string, mixed> $overrides
     */
    public function analyze(Request $request, array $overrides = []): SpamSignalResult
    {
        $cfg = $this->config($overrides);

        $fingerprint = $this->fingerprintService->fromRequest($request, [
            'ip' => (string) $request->ip(),
            'ua' => (string) $request->userAgent(),
        ]);

        $reasons = [];
        $score = 0;
        $meta = [];

        $content = (string) $request->getContent();

        $maxBytes = (int) ($cfg['max_payload_bytes'] ?? 0);
        if ($maxBytes > 0 && strlen($content) > $maxBytes) {
            $reasons[] = 'PAYLOAD_TOO_LARGE';
            $score += 40;
            $meta['payload_bytes'] = strlen($content);
            $meta['max_payload_bytes'] = $maxBytes;
        }

        $matchedPatterns = $this->matchSuspiciousPatterns($content, (array) ($cfg['suspicious_patterns'] ?? []));
        if ($matchedPatterns !== []) {
            $reasons[] = 'SUSPICIOUS_PAYLOAD_PATTERN';
            $score += 30;
            $meta['matched_patterns'] = $matchedPatterns;
        }

        $missingFields = $this->missingRequiredFields($request, (array) ($cfg['required_fields'] ?? []));
        if ($missingFields !== []) {
            $reasons[] = 'MALFORMED_REQUIRED_FIELDS';
            $score += 20;
            $meta['missing_required_fields'] = $missingFields;
        }

        $repeatConfig = (array) ($cfg['repeat_submission'] ?? []);
        if ($this->isRepeatedSubmission($request, $fingerprint, $repeatConfig)) {
            $reasons[] = 'TOO_MANY_REPEATED_SUBMISSIONS';
            $score += 50;
            $meta['repeat_submission'] = [
                'window_seconds' => (int) ($repeatConfig['window_seconds'] ?? 30),
                'max_attempts' => (int) ($repeatConfig['max_attempts'] ?? 3),
            ];
        }

        $threshold = (int) ($cfg['score_threshold'] ?? 60);
        $flagged = $score >= $threshold;

        return new SpamSignalResult($flagged, $score, array_values(array_unique($reasons)), $fingerprint, $meta);
    }

    /**
     * Loguea señal de hardening si el resultado está marcado como sospechoso.
     */
    public function logIfFlagged(
        Request $request,
        SpamSignalResult $result,
        ?EmpresaIntegration $integration = null
    ): void {
        if (! $result->flagged) {
            return;
        }

        $this->logger->failure(
            $request,
            IntegrationEventType::HARDENING_SIGNAL_DETECTED,
            'HARDENING_SIGNAL_DETECTED',
            $integration,
            [
                'score' => $result->score,
                'reasons' => $result->reasons,
                'fingerprint' => $result->fingerprint,
                'meta' => $result->meta,
            ]
        );
    }

    // ── Helpers privados ──────────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function config(array $overrides): array
    {
        $base = (array) config('integration_security.hardening', []);

        return array_replace_recursive($base, $overrides);
    }

    /**
     * @param string[] $patterns
     * @return string[]
     */
    private function matchSuspiciousPatterns(string $content, array $patterns): array
    {
        $matches = [];
        if ($content === '' || $patterns === []) {
            return $matches;
        }

        foreach ($patterns as $pattern) {
            if ($pattern === '') {
                continue;
            }

            if (@preg_match($pattern, '') !== false) {
                if (preg_match($pattern, $content) === 1) {
                    $matches[] = $pattern;
                }

                continue;
            }

            if (stripos($content, $pattern) !== false) {
                $matches[] = $pattern;
            }
        }

        return $matches;
    }

    /**
     * @param string[] $requiredFields
     * @return string[]
     */
    private function missingRequiredFields(Request $request, array $requiredFields): array
    {
        if ($requiredFields === []) {
            return [];
        }

        $data = $request->all();
        $missing = [];

        foreach ($requiredFields as $field) {
            if (! array_key_exists($field, $data)) {
                $missing[] = $field;
                continue;
            }

            $value = $data[$field];
            if ($value === null || (is_string($value) && trim($value) === '')) {
                $missing[] = $field;
            }
        }

        return $missing;
    }

    /**
     * @param array<string, mixed> $repeatConfig
     */
    private function isRepeatedSubmission(Request $request, string $fingerprint, array $repeatConfig): bool
    {
        $windowSeconds = (int) ($repeatConfig['window_seconds'] ?? 30);
        $maxAttempts = (int) ($repeatConfig['max_attempts'] ?? 3);

        if ($maxAttempts <= 0) {
            return false;
        }

        $ip = (string) $request->ip();
        $key = 'intsec:hardening:repeat:' . sha1($fingerprint . '|' . $ip);

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            return true;
        }

        RateLimiter::hit($key, $windowSeconds);

        return false;
    }
}
