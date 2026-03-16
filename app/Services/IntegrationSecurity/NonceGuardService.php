<?php

namespace App\Services\IntegrationSecurity;

use App\Exceptions\IntegrationSecurity\NonceReplayException;
use App\Models\EmpresaIntegration;
use App\Models\IntegrationRequestNonce;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Valida y almacena nonces para proteger contra ataques de replay.
 *
 * Responsabilidades:
 *   1. Leer el nonce del header X-QITS-Nonce.
 *   2. Verificar que el nonce no existe en la BD para la integración dada
 *      y dentro del TTL configurado.
 *   3. Almacenar el nonce si es nuevo (consume el nonce).
 *   4. Lanzar NonceReplayException si el nonce ya fue visto.
 *
 * Protección ante race conditions:
 *   La tabla integration_request_nonces tiene índice UNIQUE en 'nonce'.
 *   Si dos requests idénticos llegan simultáneamente, el segundo recibirá
 *   una QueryException por violación de unicidad, que se captura y traduce
 *   a NonceReplayException.
 *
 * Purga:
 *   Los nonces expirados no se purgan aquí para no bloquear el request.
 *   Usar un Artisan command o job programado:
 *     IntegrationRequestNonce::expired()->delete();
 *
 * Uso típico:
 *   app(NonceGuardService::class)->checkAndStore($integration, $request, $signatureHash);
 */
class NonceGuardService
{
    /**
     * Valida que el nonce es único para esta integración y lo persiste.
     *
     * @param string $signatureHash Hash de la firma del request (opcional, para auditoría).
     *
     * @throws NonceReplayException si el nonce ya fue usado.
     */
    public function checkAndStore(
        EmpresaIntegration $integration,
        Request $request,
        string $signatureHash = ''
    ): void {
        $nonce = $this->extractNonce($request);

        // Verificación explícita antes del INSERT para dar un mensaje más claro
        // en el caso no-concurrente (el 99% de los casos).
        if ($this->alreadyUsed($integration->id, $nonce)) {
            throw new NonceReplayException($nonce);
        }

        $ttl     = (int) config('integration_security.nonce_ttl_seconds', 300);
        $expires = Carbon::now()->addSeconds($ttl);

        try {
            IntegrationRequestNonce::create([
                'integration_id'          => $integration->id,
                'nonce'                   => $nonce,
                'request_signature_hash'  => $signatureHash !== '' ? $signatureHash : null,
                'expires_at'              => $expires,
            ]);
        } catch (QueryException $e) {
            // Violación de la constraint UNIQUE en nonce → replay concurrente.
            if ($this->isDuplicateKeyError($e)) {
                throw new NonceReplayException($nonce);
            }

            throw $e;
        }
    }

    /**
     * Extrae el nonce del header de la request.
     * Si el header está ausente, devuelve string vacío;
     * un nonce vacío siempre fallará la comprobación de unicidad.
     */
    public function extractNonce(Request $request): string
    {
        $headerName = config('integration_security.headers.nonce', 'X-QITS-Nonce');

        return (string) $request->header($headerName, '');
    }

    // ── Helpers privados ──────────────────────────────────────────────────────

    /**
     * Comprueba si el nonce ya existe en BD (dentro del TTL vigente).
     * Se busca solo entre nonces no expirados para permitir re-uso de nonces
     * una vez transcurrida la ventana de protección.
     */
    private function alreadyUsed(int $integrationId, string $nonce): bool
    {
        return IntegrationRequestNonce::where('integration_id', $integrationId)
            ->where('nonce', $nonce)
            ->valid()  // scope: expires_at > now()
            ->exists();
    }

    /**
     * Detecta errores de llave duplicada en MySQL / MariaDB.
     * Código de error MySQL 1062 = Duplicate entry.
     */
    private function isDuplicateKeyError(QueryException $e): bool
    {
        return $e->errorInfo[1] === 1062;
    }
}
