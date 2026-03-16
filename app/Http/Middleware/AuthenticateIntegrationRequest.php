<?php

namespace App\Http\Middleware;

use App\Exceptions\IntegrationSecurity\BusinessServiceDeniedException;
use App\Exceptions\IntegrationSecurity\IntegrationInactiveException;
use App\Exceptions\IntegrationSecurity\IntegrationNotFoundException;
use App\Exceptions\IntegrationSecurity\InvalidSignatureException;
use App\Exceptions\IntegrationSecurity\NonceReplayException;
use App\Exceptions\IntegrationSecurity\RateLimitExceededException;
use App\Exceptions\IntegrationSecurity\RequestExpiredException;
use App\Exceptions\IntegrationSecurity\ScopeDeniedException;
use App\Services\IntegrationSecurity\BusinessModuleAccessService;
use App\Services\IntegrationSecurity\IntegrationAccessService;
use App\Services\IntegrationSecurity\IntegrationCredentialResolver;
use App\Services\IntegrationSecurity\IntegrationRateLimitService;
use App\Services\IntegrationSecurity\NonceGuardService;
use App\Services\IntegrationSecurity\RequestSignatureService;
use App\Services\IntegrationSecurity\SecurityEventLogger;
use App\Services\IntegrationSecurity\TimestampGuardService;
use App\Support\IntegrationSecurity\IntegrationEventType;
use Closure;
use Illuminate\Http\Request;

/**
 * Middleware genérico para endpoints de API protegidos por integración firmada.
 *
 * Pipeline ejecutado en orden:
 *   1. Validar headers requeridos presentes.
 *   2. Validar frescura del timestamp (fail-fast antes de consultar BD).
 *   3. Resolver la EmpresaIntegration desde el public key.
 *   4. Verificar la firma HMAC.
 *   5. Validar y consumir el nonce (replay protection).
 *   6. Validar scope técnico de la integración para el módulo solicitado.
 *   7. Validar que la Empresa tiene activo el servicio de negocio requerido.
 *   8. Adjuntar la integración y empresa al request para uso en controladores.
 *   9. Loguear el evento de éxito.
 *
 * Uso en rutas:
 *   Route::post('/endpoint', [Controller::class, 'method'])
 *       ->middleware('integration.auth:module1.form_ingress');
 *
 * Valores adjuntados al request:
 *   $request->attributes->get('integration')  → EmpresaIntegration
 *   $request->attributes->get('empresa')       → Empresa
 *
 * Todos los fallos devuelven JSON { "message": "..." } con el código HTTP apropiado.
 * Los eventos de seguridad se persisten en integration_security_logs independientemente
 * del resultado (no interrumpen el flujo si el logger falla).
 */
class AuthenticateIntegrationRequest
{
    /** @var IntegrationCredentialResolver */
    private $credentialResolver;

    /** @var TimestampGuardService */
    private $timestampGuard;

    /** @var RequestSignatureService */
    private $signatureService;

    /** @var NonceGuardService */
    private $nonceGuard;

    /** @var IntegrationAccessService */
    private $integrationAccess;

    /** @var BusinessModuleAccessService */
    private $businessAccess;

    /** @var SecurityEventLogger */
    private $logger;

    /** @var IntegrationRateLimitService */
    private $rateLimit;

    public function __construct(
        IntegrationCredentialResolver $credentialResolver,
        TimestampGuardService $timestampGuard,
        RequestSignatureService $signatureService,
        NonceGuardService $nonceGuard,
        IntegrationAccessService $integrationAccess,
        BusinessModuleAccessService $businessAccess,
        SecurityEventLogger $logger,
        IntegrationRateLimitService $rateLimit
    ) {
        $this->credentialResolver = $credentialResolver;
        $this->timestampGuard     = $timestampGuard;
        $this->signatureService   = $signatureService;
        $this->nonceGuard         = $nonceGuard;
        $this->integrationAccess  = $integrationAccess;
        $this->businessAccess     = $businessAccess;
        $this->logger             = $logger;
        $this->rateLimit          = $rateLimit;
    }

    /**
     * @param string $moduleKey  Clave de módulo requerida (ej. 'module1.form_ingress').
     *                           Si no se pasa, solo se autentica sin verificar scope.
     */
    public function handle(Request $request, Closure $next, string $moduleKey = ''): mixed
    {
        // ── 1. Headers requeridos ─────────────────────────────────────────────
        $missing = $this->missingHeaders($request);
        if ($missing !== []) {
            $this->logger->failure(
                $request,
                IntegrationEventType::AUTH_FAILED_MISSING_HEADERS,
                'MISSING_HEADERS',
                null,
                ['missing' => $missing]
            );

            return response()->json([
                'message' => 'Missing required authentication headers.',
                'missing' => $missing,
            ], 401);
        }

        // ── 2. Timestamp (fail-fast: no consulta BD si es inválido) ───────────
        try {
            $this->timestampGuard->check($request);
        } catch (RequestExpiredException $e) {
            $this->logger->failure(
                $request,
                IntegrationEventType::AUTH_FAILED_EXPIRED_TIMESTAMP,
                'EXPIRED_TIMESTAMP'
            );

            return response()->json(['message' => 'Request timestamp expired.'], 401);
        }

        // ── 3. Resolver integración ───────────────────────────────────────────
        try {
            $integration = $this->credentialResolver->resolve($request);
        } catch (IntegrationNotFoundException $e) {
            $this->logger->failure(
                $request,
                IntegrationEventType::AUTH_FAILED_INVALID_KEY,
                'INVALID_KEY'
            );

            return response()->json(['message' => 'Unauthorized.'], 401);
        } catch (IntegrationInactiveException $e) {
            $this->logger->failure(
                $request,
                IntegrationEventType::AUTH_FAILED_INACTIVE_INTEGRATION,
                'INTEGRATION_INACTIVE',
                $e->getIntegration()
            );

            return response()->json(['message' => 'Integration is not active.'], 403);
        }

        // ── 4. Verificar firma HMAC ───────────────────────────────────────────
        try {
            $this->signatureService->verify($request, $integration);
        } catch (InvalidSignatureException $e) {
            $this->logger->failure(
                $request,
                IntegrationEventType::AUTH_FAILED_INVALID_SIGNATURE,
                'INVALID_SIGNATURE',
                $integration
            );

            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        // ── 5. Nonce / replay protection ──────────────────────────────────────
        $signatureHash = (string) $request->header(
            config('integration_security.headers.signature', 'X-QITS-Signature'),
            ''
        );

        try {
            $this->nonceGuard->checkAndStore($integration, $request, $signatureHash);
        } catch (NonceReplayException $e) {
            $this->logger->failure(
                $request,
                IntegrationEventType::AUTH_FAILED_REPLAY,
                'NONCE_REPLAYED',
                $integration,
                ['nonce' => $e->getNonce()]
            );

            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        // ── 6 & 7. Autorización de módulo (solo si se especificó moduleKey) ───
        if ($moduleKey !== '') {
            // 6. Scope técnico de la integración
            try {
                $this->integrationAccess->authorize($integration, $moduleKey);
            } catch (ScopeDeniedException $e) {
                $this->logger->failure(
                    $request,
                    IntegrationEventType::AUTH_FAILED_SCOPE_DENIED,
                    'SCOPE_DENIED',
                    $integration,
                    ['module' => $moduleKey, 'required_scope' => $e->getRequiredScope()]
                );

                return response()->json(['message' => 'Access denied: insufficient scope.'], 403);
            }

            // 7. Servicio de negocio activo en la Empresa
            try {
                $this->businessAccess->authorize($integration, $moduleKey);
            } catch (BusinessServiceDeniedException $e) {
                $this->logger->failure(
                    $request,
                    IntegrationEventType::BUSINESS_ACCESS_DENIED_SERVICE_MISSING,
                    'BUSINESS_SERVICE_MISSING',
                    $integration,
                    ['module' => $moduleKey, 'service_id' => $e->getRequiredServiceId()]
                );

                return response()->json(['message' => 'Access denied: required service not active.'], 403);
            }
        }

        // ── 8. Protección operativa: rate limiting ──────────────────────────
        try {
            $this->rateLimit->enforce($request, $integration, [
                'endpoint_key' => $moduleKey !== ''
                    ? $moduleKey
                    : (strtoupper($request->method()) . ':' . '/' . ltrim($request->path(), '/')),
                'dimensions' => [
                    'endpoint' => true,
                ],
            ]);
        } catch (RateLimitExceededException $e) {
            return response()->json([
                'message' => 'Too Many Requests.',
                'error' => [
                    'code' => 'RATE_LIMIT_EXCEEDED',
                    'profile' => $e->getProfile(),
                    'bucket' => $e->getBucket(),
                    'retry_after_seconds' => $e->getRetryAfterSeconds(),
                    'limit' => $e->getLimit(),
                    'window_seconds' => $e->getWindowSeconds(),
                ],
            ], 429, [
                'Retry-After' => (string) $e->getRetryAfterSeconds(),
            ]);
        }

        // ── 9. Adjuntar contexto al request ───────────────────────────────────
        $empresa = $integration->relationLoaded('empresa')
            ? $integration->empresa
            : $integration->load('empresa')->empresa;

        $request->attributes->set('integration', $integration);
        $request->attributes->set('empresa', $empresa);

        // ── 10. Loguear éxito y actualizar last_used ──────────────────────────
        $this->logger->success($request, $integration, ['module' => $moduleKey ?: null]);
        $this->touchLastUsed($integration, $request);

        return $next($request);
    }

    // ── Helpers privados ──────────────────────────────────────────────────────

    /**
     * Devuelve los nombres de los headers requeridos que faltan en el request.
     *
     * @return string[]
     */
    private function missingHeaders(Request $request): array
    {
        $required = [
            config('integration_security.headers.public_key', 'X-QITS-Key'),
            config('integration_security.headers.timestamp',  'X-QITS-Timestamp'),
            config('integration_security.headers.nonce',      'X-QITS-Nonce'),
            config('integration_security.headers.signature',  'X-QITS-Signature'),
        ];

        $missing = [];
        foreach ($required as $header) {
            if (empty($request->header($header))) {
                $missing[] = $header;
            }
        }

        return $missing;
    }

    /**
     * Actualiza last_used_at y last_used_ip de forma silenciosa.
     * Un fallo aquí no debe interrumpir el flujo del request.
     */
    private function touchLastUsed($integration, Request $request): void
    {
        try {
            $integration->update([
                'last_used_at' => now(),
                'last_used_ip' => $request->ip(),
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning(
                '[AuthenticateIntegrationRequest] Failed to update last_used fields.',
                ['integration_id' => $integration->id, 'error' => $e->getMessage()]
            );
        }
    }
}
