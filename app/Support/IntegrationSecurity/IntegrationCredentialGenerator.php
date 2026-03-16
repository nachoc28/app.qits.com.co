<?php

namespace App\Support\IntegrationSecurity;

/**
 * Generador criptográficamente seguro de credenciales de integración.
 */
class IntegrationCredentialGenerator
{
    /**
     * Public key legible para uso en headers y administración.
     */
    public function generatePublicKey(): string
    {
        return 'qits_pk_' . bin2hex(random_bytes(12));
    }

    /**
     * Secreto plano mostrado una sola vez al cliente.
     */
    public function generateSecret(): string
    {
        return 'qits_sk_' . bin2hex(random_bytes(24));
    }

    /**
     * Hash unidireccional del secreto para almacenamiento en BD.
     *
     * Nota: este hash también actúa como signing key interna del servidor.
     */
    public function hashSecret(string $plainSecret): string
    {
        return hash('sha256', $plainSecret);
    }
}
