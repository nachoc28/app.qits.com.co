<?php

namespace App\Services\IntegrationSecurity;

use App\Exceptions\IntegrationSecurity\RateLimitExceededException;
use App\Models\EmpresaIntegration;
use App\Services\IntegrationSecurity\IntegrationRateLimitResult;
use App\Services\IntegrationSecurity\SecurityEventLogger;
use App\Support\IntegrationSecurity\IntegrationEventType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Aplica rate limiting reutilizable para endpoints autenticados por integración.
 *
 * Soporta límites por:
 *  - integración
 *  - empresa
 *  - IP
 *  - endpoint
 *
 * y perfiles configurables (normal, strict, high_volume).
 */
class IntegrationRateLimitService
{
    /** @var SecurityEventLogger */
    private $logger;

    public function __construct(SecurityEventLogger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Evalúa límites y devuelve resultado estructurado.
     *
     * @param array<string, mixed> $options
     */
    public function check(
        Request $request,
        ?EmpresaIntegration $integration,
        array $options = []
    ): IntegrationRateLimitResult {
        $profileName = $this->resolveProfile($integration, $options);
        $profile = $this->resolveProfileConfig($profileName);

        $rpm = (int) ($profile['rpm'] ?? 60);
        $burst = (int) ($profile['burst'] ?? 10);
        $burstWindow = (int) ($profile['burst_window_seconds']
            ?? config('integration_security.rate_limit.burst_window_seconds', 10));

        $dimensions = $this->resolveDimensions($options);
        $buckets = $this->buildBuckets($request, $integration, $dimensions, $options);

        foreach ($buckets as $bucketName => $bucketValue) {
            $minuteKey = $this->rateKey($profileName, $bucketName . ':minute', $bucketValue);
            $burstKey = $this->rateKey($profileName, $bucketName . ':burst', $bucketValue);

            if ($rpm > 0 && RateLimiter::tooManyAttempts($minuteKey, $rpm)) {
                return $this->deny(
                    $request,
                    $integration,
                    $profileName,
                    $bucketName . ':minute',
                    RateLimiter::availableIn($minuteKey),
                    $rpm,
                    60,
                    ['bucket_value' => $bucketValue]
                );
            }

            if ($burst > 0 && RateLimiter::tooManyAttempts($burstKey, $burst)) {
                return $this->deny(
                    $request,
                    $integration,
                    $profileName,
                    $bucketName . ':burst',
                    RateLimiter::availableIn($burstKey),
                    $burst,
                    $burstWindow,
                    ['bucket_value' => $bucketValue]
                );
            }
        }

        $attempts = [];
        foreach ($buckets as $bucketName => $bucketValue) {
            $minuteKey = $this->rateKey($profileName, $bucketName . ':minute', $bucketValue);
            $burstKey = $this->rateKey($profileName, $bucketName . ':burst', $bucketValue);

            if ($rpm > 0) {
                RateLimiter::hit($minuteKey, 60);
                $attempts[$bucketName . ':minute'] = RateLimiter::attempts($minuteKey);
            }

            if ($burst > 0) {
                RateLimiter::hit($burstKey, $burstWindow);
                $attempts[$bucketName . ':burst'] = RateLimiter::attempts($burstKey);
            }
        }

        return new IntegrationRateLimitResult(
            true,
            $profileName,
            null,
            null,
            null,
            null,
            $attempts,
            ['dimensions' => $dimensions]
        );
    }

    /**
     * Valida límites y lanza excepción tipada si están excedidos.
     *
     * @param array<string, mixed> $options
     * @throws RateLimitExceededException
     */
    public function enforce(Request $request, ?EmpresaIntegration $integration, array $options = []): void
    {
        $result = $this->check($request, $integration, $options);

        if ($result->isExceeded()) {
            throw new RateLimitExceededException(
                $result->profile,
                (string) $result->violatedBucket,
                (int) $result->retryAfterSeconds,
                (int) $result->limit,
                (int) $result->windowSeconds
            );
        }
    }

    // ── Helpers privados ──────────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $options
     */
    private function resolveProfile(?EmpresaIntegration $integration, array $options): string
    {
        if (! empty($options['profile'])) {
            return (string) $options['profile'];
        }

        if ($integration && ! empty($integration->rate_limit_profile)) {
            return (string) $integration->rate_limit_profile;
        }

        return (string) config('integration_security.rate_limit.default_profile', 'normal');
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveProfileConfig(string $profile): array
    {
        $profiles = (array) config('integration_security.rate_limit_profiles', []);

        if (isset($profiles[$profile])) {
            return (array) $profiles[$profile];
        }

        if ($profile === 'default' && isset($profiles['normal'])) {
            return (array) $profiles['normal'];
        }

        if ($profile === 'high' && isset($profiles['high_volume'])) {
            return (array) $profiles['high_volume'];
        }

        return (array) ($profiles['normal'] ?? ['rpm' => 60, 'burst' => 10]);
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, bool>
     */
    private function resolveDimensions(array $options): array
    {
        $defaults = (array) config('integration_security.rate_limit.dimensions', [
            'integration' => true,
            'empresa' => true,
            'ip' => true,
            'endpoint' => false,
        ]);

        $overrides = (array) ($options['dimensions'] ?? []);

        return [
            'integration' => (bool) ($overrides['integration'] ?? $defaults['integration'] ?? true),
            'empresa' => (bool) ($overrides['empresa'] ?? $defaults['empresa'] ?? true),
            'ip' => (bool) ($overrides['ip'] ?? $defaults['ip'] ?? true),
            'endpoint' => (bool) ($overrides['endpoint'] ?? $defaults['endpoint'] ?? false),
        ];
    }

    /**
     * @param array<string, bool> $dimensions
     * @param array<string, mixed> $options
     * @return array<string, string>
     */
    private function buildBuckets(
        Request $request,
        ?EmpresaIntegration $integration,
        array $dimensions,
        array $options
    ): array {
        $buckets = [];

        if ($dimensions['integration']) {
            $buckets['integration'] = $integration
                ? 'integration:' . $integration->id
                : 'integration:unknown';
        }

        if ($dimensions['empresa']) {
            $empresaId = $integration ? (string) $integration->empresa_id : 'unknown';
            $buckets['empresa'] = 'empresa:' . $empresaId;
        }

        if ($dimensions['ip']) {
            $buckets['ip'] = 'ip:' . ((string) $request->ip() ?: 'unknown');
        }

        if ($dimensions['endpoint']) {
            $endpointKey = (string) ($options['endpoint_key'] ?? (strtoupper($request->method()) . ':' . '/' . ltrim($request->path(), '/')));
            $buckets['endpoint'] = 'endpoint:' . $endpointKey;
        }

        if ($buckets === []) {
            $buckets['global'] = 'global';
        }

        return $buckets;
    }

    private function rateKey(string $profile, string $bucketName, string $bucketValue): string
    {
        return 'intsec:rl:' . $profile . ':' . $bucketName . ':' . sha1($bucketValue);
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function deny(
        Request $request,
        ?EmpresaIntegration $integration,
        string $profile,
        string $violatedBucket,
        int $retryAfterSeconds,
        int $limit,
        int $windowSeconds,
        array $meta
    ): IntegrationRateLimitResult {
        $this->logger->failure(
            $request,
            IntegrationEventType::RATE_LIMIT_EXCEEDED,
            'RATE_LIMIT_EXCEEDED',
            $integration,
            array_merge($meta, [
                'profile' => $profile,
                'violated_bucket' => $violatedBucket,
                'retry_after_seconds' => $retryAfterSeconds,
                'limit' => $limit,
                'window_seconds' => $windowSeconds,
            ])
        );

        return new IntegrationRateLimitResult(
            false,
            $profile,
            $violatedBucket,
            $retryAfterSeconds,
            $limit,
            $windowSeconds,
            [],
            $meta
        );
    }
}
