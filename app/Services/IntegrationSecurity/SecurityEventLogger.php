<?php

namespace App\Services\IntegrationSecurity;

use App\Models\EmpresaIntegration;
use App\Models\IntegrationSecurityLog;
use App\Support\IntegrationSecurity\IntegrationEventType;
use Illuminate\Http\Request;

/**
 * Persiste eventos de seguridad en integration_security_logs.
 *
 * Responsabilidades:
 *   - Escribir un registro inmutable por cada evento de autenticación
 *     o autorización relevante.
 *   - Extraer del request los campos contextuales (IP, dominio, path, método).
 *   - Calcular el fingerprint del cuerpo si se solicita.
 *
 * Diseño:
 *   - El logger NUNCA lanza excepciones hacia el llamante: un fallo al loguear
 *     no debe interrumpir el flujo del request. Los errores se envían al log
 *     de aplicación de Laravel.
 *   - No filtra ni valida datos de negocio; solo registra lo que recibe.
 *
 * Uso típico — evento de fallo (sin integración resuelta):
 *   $logger->failure(
 *       $request,
 *       IntegrationEventType::AUTH_FAILED_INVALID_KEY,
 *       'AUTH_FAILED_INVALID_KEY'
 *   );
 *
 * Uso típico — evento de éxito (con integración resuelta):
 *   $logger->success($request, $integration);
 *
 * Uso avanzado — control total:
 *   $logger->log($request, IntegrationEventType::RATE_LIMIT_EXCEEDED, 'denied', null, 'RATE_LIMIT', ['rpm' => 60]);
 */
class SecurityEventLogger
{
    /**
     * Registra un evento de éxito de autenticación.
     */
    public function success(Request $request, EmpresaIntegration $integration, array $meta = []): void
    {
        $this->log(
            $request,
            IntegrationEventType::AUTH_SUCCESS,
            'allowed',
            $integration,
            null,
            $meta
        );
    }

    /**
     * Registra un evento de fallo de autenticación o autorización.
     *
     * @param string                   $eventType  Constante de IntegrationEventType.
     * @param string                   $reasonCode Código legible por máquina (ej. 'NONCE_REPLAYED').
     * @param EmpresaIntegration|null  $integration Integración resuelta, si se llegó a resolver.
     */
    public function failure(
        Request $request,
        string $eventType,
        string $reasonCode,
        EmpresaIntegration $integration = null,
        array $meta = []
    ): void {
        $this->log($request, $eventType, 'denied', $integration, $reasonCode, $meta);
    }

    /**
     * Método de logging de bajo nivel con control total sobre todos los campos.
     *
     * @param string                  $eventType  Constante de IntegrationEventType.
     * @param string                  $status     'allowed' | 'denied' | 'error'
     * @param EmpresaIntegration|null $integration
     * @param string|null             $reasonCode
     * @param array                   $meta       Datos extra a almacenar en meta_json.
     * @param bool                    $withPayloadFingerprint Si true, calcula SHA-256 del body.
     */
    public function log(
        Request $request,
        string $eventType,
        string $status,
        EmpresaIntegration $integration = null,
        string $reasonCode = null,
        array $meta = [],
        bool $withPayloadFingerprint = false
    ): void {
        try {
            IntegrationSecurityLog::create([
                'integration_id'      => $integration ? $integration->id : null,
                'empresa_id'          => $integration ? $integration->empresa_id : null,
                'event_type'          => $eventType,
                'ip_address'          => $this->resolveIp($request),
                'domain'              => $this->resolveDomain($request),
                'endpoint'            => '/' . ltrim($request->path(), '/'),
                'http_method'         => strtoupper($request->method()),
                'status'              => $status,
                'reason_code'         => $reasonCode,
                'payload_fingerprint' => $withPayloadFingerprint
                                            ? hash('sha256', (string) $request->getContent())
                                            : null,
                'meta_json'           => empty($meta) ? null : $meta,
            ]);
        } catch (\Throwable $e) {
            // El fallo al loguear no debe romper el flujo del request.
            \Illuminate\Support\Facades\Log::error(
                '[SecurityEventLogger] Failed to write security log entry.',
                ['exception' => $e->getMessage(), 'event_type' => $eventType]
            );
        }
    }

    // ── Helpers privados ──────────────────────────────────────────────────────

    /**
     * Resuelve la IP real del cliente teniendo en cuenta proxies confiables.
     * Usa el helper de Laravel que respeta REMOTE_ADDR y X-Forwarded-For
     * según la configuración de trusted proxies.
     */
    private function resolveIp(Request $request): ?string
    {
        return $request->ip();
    }

    /**
     * Extrae el dominio/origen del request.
     * Prefiere el header Origin (estándar CORS); recurre a Referer si está ausente.
     * Devuelve null si ninguno está disponible.
     */
    private function resolveDomain(Request $request): ?string
    {
        $origin = $request->header('Origin');
        if (! empty($origin)) {
            $host = parse_url($origin, PHP_URL_HOST);

            return $host !== false ? $host : $origin;
        }

        $referer = $request->header('Referer');
        if (! empty($referer)) {
            $host = parse_url($referer, PHP_URL_HOST);

            return $host !== false ? $host : null;
        }

        return null;
    }
}
