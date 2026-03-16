<?php

namespace App\Services\IntegrationSecurity;

use App\Exceptions\IntegrationSecurity\RequestExpiredException;
use Illuminate\Http\Request;

/**
 * Valida que el timestamp del request se encuentra dentro de la ventana
 * de tolerancia configurada en integration_security.timestamp_tolerance_seconds.
 *
 * Responsabilidad única:
 *   Rechazar requests cuyo timestamp sea demasiado antiguo o demasiado futuro.
 *
 * Por qué separarlo de RequestSignatureService:
 *   - Permite validar el timestamp ANTES de resolver la integración (fail-fast).
 *   - Facilita el uso independiente en pipelines de middleware.
 *   - Reduce la carga de BD: si el timestamp es inválido, no se busca la integración.
 *
 * Uso típico:
 *   app(TimestampGuardService::class)->check($request);
 */
class TimestampGuardService
{
    /**
     * Verifica que el timestamp del header esté dentro de la ventana permitida.
     *
     * @throws RequestExpiredException si el timestamp es inválido o está fuera del rango.
     */
    public function check(Request $request): void
    {
        $headerName = config('integration_security.headers.timestamp', 'X-QITS-Timestamp');
        $tolerance  = (int) config('integration_security.timestamp_tolerance_seconds', 300);

        $raw = $request->header($headerName, '');

        // Un timestamp ausente, no numérico o igual a 0 se trata como expirado.
        if ($raw === '' || ! ctype_digit($raw)) {
            throw new RequestExpiredException(0);
        }

        $requestTs = (int) $raw;

        if (abs(time() - $requestTs) > $tolerance) {
            throw new RequestExpiredException($requestTs);
        }
    }

    /**
     * Lee el timestamp del header como entero.
     * Devuelve 0 si el header está ausente o no es numérico.
     */
    public function extractTimestamp(Request $request): int
    {
        $headerName = config('integration_security.headers.timestamp', 'X-QITS-Timestamp');
        $raw = (string) $request->header($headerName, '');

        return ctype_digit($raw) ? (int) $raw : 0;
    }
}
