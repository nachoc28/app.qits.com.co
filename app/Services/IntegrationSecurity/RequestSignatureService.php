<?php

namespace App\Services\IntegrationSecurity;

use App\Exceptions\IntegrationSecurity\InvalidSignatureException;
use App\Models\EmpresaIntegration;
use Illuminate\Http\Request;

/**
 * Valida la firma HMAC de un request externo.
 *
 * Responsabilidades:
 *   1. Construir el canonical string de forma determinista.
 *   2. Calcular la firma HMAC esperada usando la signing key derivada.
 *   3. Comparar en tiempo constante para prevenir timing attacks.
 *
 * Lo que NO hace:
 *   - No resuelve la integración (responsabilidad de IntegrationCredentialResolver).
 *   - No valida ni persiste el nonce (responsabilidad de un futuro NonceValidatorService).
 *   - No verifica scopes o permisos de módulo.
 *
 * ── Canonical String Format ────────────────────────────────────────────────
 *
 *   METHOD\n
 *   /path/to/endpoint\n
 *   UNIX_TIMESTAMP\n
 *   NONCE\n
 *   HEX_SHA256(raw_request_body)
 *
 * Reglas del formato:
 *   - METHOD: en mayúsculas (GET, POST, ...).
 *   - Path: siempre empieza con '/', sin query string.
 *   - Timestamp: entero Unix como string, igual al header X-QITS-Timestamp.
 *   - Nonce: string opaco del header X-QITS-Nonce.
 *   - Body hash: sha256 hex del cuerpo crudo; string vacío si no hay cuerpo → sha256('').
 *
 * ── Verificación del cliente externo ──────────────────────────────────────
 *
 *   $canonical = strtoupper($method) . "\n"
 *              . '/' . ltrim($path, '/') . "\n"
 *              . $timestamp . "\n"
 *              . $nonce . "\n"
 *              . hash('sha256', $rawBody);
 *
 *   // La signing key es hash('sha256', $clientSecretPlain)
 *   $signingKey = hash('sha256', $clientSecretPlain);
 *   $signature = hash_hmac('sha256', $canonical, $signingKey);
 *   // Enviar en header: X-QITS-Signature: $signature
 *
 */
class RequestSignatureService
{
    /**
     * Verifica la firma HMAC del request.
     *
     * La validación de timestamp debe realizarse previamente mediante TimestampGuardService.
     * La validación de nonce debe realizarse posteriormente mediante NonceGuardService.
     *
     * @throws InvalidSignatureException si la firma no coincide.
     */
    public function verify(Request $request, EmpresaIntegration $integration): void
    {
        $this->checkSignature($request, $integration);
    }

    /**
     * Construye el canonical string del request.
     *
     * Es público para que las pruebas unitarias puedan verificar
     * el contenido del string sin necesidad de firmar.
     */
    public function buildCanonicalString(Request $request): string
    {
        $tsHeader    = config('integration_security.headers.timestamp', 'X-QITS-Timestamp');
        $nonceHeader = config('integration_security.headers.nonce',     'X-QITS-Nonce');

        $method    = strtoupper($request->method());
        $path      = '/' . ltrim($request->path(), '/');
        $timestamp = (string) $request->header($tsHeader, '');
        $nonce     = (string) $request->header($nonceHeader, '');
        $bodyHash  = hash('sha256', (string) $request->getContent());

        return implode("\n", [$method, $path, $timestamp, $nonce, $bodyHash]);
    }

    // ── Validaciones internas ─────────────────────────────────────────────────

    /**
     * Construye la firma esperada y la compara en tiempo constante
     * con la firma recibida en el header X-QITS-Signature.
     *
     * Usa hash_equals() para prevenir timing attacks (Oracle de comparación).
     *
     * @throws InvalidSignatureException
     */
    private function checkSignature(Request $request, EmpresaIntegration $integration): void
    {
        $sigHeader = config('integration_security.headers.signature', 'X-QITS-Signature');
        $algorithm = config('integration_security.signature_algorithm', 'sha256');

        $received = (string) $request->header($sigHeader, '');

        // secret_hash es hash unidireccional y funciona como signing key derivada.
        $secret   = (string) $integration->secret_hash;
        $canonical = $this->buildCanonicalString($request);
        $expected  = hash_hmac($algorithm, $canonical, $secret);

        // hash_equals requiere que ambas cadenas tengan la misma longitud
        // para no ser vulnerable a comparación corta-circuitada.
        if (! hash_equals($expected, $received)) {
            throw new InvalidSignatureException();
        }
    }
}
