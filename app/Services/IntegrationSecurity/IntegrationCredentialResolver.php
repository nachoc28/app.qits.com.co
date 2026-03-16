<?php

namespace App\Services\IntegrationSecurity;

use App\Exceptions\IntegrationSecurity\IntegrationInactiveException;
use App\Exceptions\IntegrationSecurity\IntegrationNotFoundException;
use App\Models\EmpresaIntegration;
use Illuminate\Http\Request;

/**
 * Resuelve y valida la integración externa a partir de la public key
 * recibida en el header de autenticación.
 *
 * Responsabilidades (únicas):
 *   1. Leer el header X-QITS-Key (nombre configurable).
 *   2. Buscar la EmpresaIntegration correspondiente.
 *   3. Verificar que la integración existe.
 *   4. Verificar que la integración está activa.
 *
 * Lo que NO hace:
 *   - No valida la firma (responsabilidad de RequestSignatureService).
 *   - No valida el timestamp ni el nonce.
 *   - No verifica permisos de módulo ni scopes.
 *
 * Uso típico:
 *   $integration = app(IntegrationCredentialResolver::class)->resolve($request);
 */
class IntegrationCredentialResolver
{
    /**
     * Resuelve la integración a partir del header public key del request.
     *
     * @throws IntegrationNotFoundException si el header está ausente o la clave no existe.
     * @throws IntegrationInactiveException si la integración existe pero no está activa.
     */
    public function resolve(Request $request): EmpresaIntegration
    {
        $publicKey = $this->extractPublicKey($request);

        $integration = EmpresaIntegration::where('public_key', $publicKey)->first();

        if ($integration === null) {
            throw new IntegrationNotFoundException($publicKey);
        }

        if (! $integration->isActive()) {
            throw new IntegrationInactiveException($integration);
        }

        return $integration;
    }

    /**
     * Lee el header que identifica la integración.
     * Si el header está ausente, trata al llamante como desconocido y lanza
     * IntegrationNotFoundException con clave vacía (no revela información).
     *
     * @throws IntegrationNotFoundException si el header public key no está presente.
     */
    private function extractPublicKey(Request $request): string
    {
        $headerName = config('integration_security.headers.public_key', 'X-QITS-Key');

        $value = $request->header($headerName);

        if (empty($value)) {
            throw new IntegrationNotFoundException('');
        }

        return (string) $value;
    }
}
