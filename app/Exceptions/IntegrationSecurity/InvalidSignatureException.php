<?php

namespace App\Exceptions\IntegrationSecurity;

use RuntimeException;

/**
 * Se lanza cuando la firma HMAC del request no coincide con la esperada.
 *
 * No incluye detalles técnicos en el mensaje público para evitar
 * filtrar información sobre el algoritmo o la construcción del canonical.
 * El Handler la mapea a HTTP 401.
 */
class InvalidSignatureException extends RuntimeException
{
    public function __construct(string $internalDetail = '')
    {
        // Mensaje público genérico; el detalle interno queda solo en logs.
        parent::__construct('Request signature is invalid.');

        if ($internalDetail !== '') {
            // Reemplaza el mensaje solo en contextos de logging,
            // usando el getter de la jerarquía padre si se necesita.
            $this->message = 'Request signature is invalid. ' . $internalDetail;
        }
    }
}
