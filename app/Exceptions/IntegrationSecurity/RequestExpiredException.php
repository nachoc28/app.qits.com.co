<?php

namespace App\Exceptions\IntegrationSecurity;

use RuntimeException;

/**
 * Se lanza cuando el timestamp del request está fuera de la ventana de tolerancia
 * definida en config('integration_security.timestamp_tolerance_seconds').
 *
 * Protege contra ataques de replay tardíos incluso si el nonce no se almacenó.
 * El Handler la mapea a HTTP 401.
 */
class RequestExpiredException extends RuntimeException
{
    private int $requestTimestamp;

    public function __construct(int $requestTimestamp)
    {
        $this->requestTimestamp = $requestTimestamp;

        $tolerance = (int) config('integration_security.timestamp_tolerance_seconds', 300);

        parent::__construct(
            "Request timestamp [{$requestTimestamp}] is outside the allowed window of ±{$tolerance}s."
        );
    }

    public function getRequestTimestamp(): int
    {
        return $this->requestTimestamp;
    }
}
