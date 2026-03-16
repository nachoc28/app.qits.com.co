<?php

namespace App\Exceptions\IntegrationSecurity;

use RuntimeException;

/**
 * Se lanza cuando el nonce del request ya fue procesado anteriormente,
 * indicando un posible ataque de replay.
 *
 * El Handler la mapea a HTTP 401.
 * El mensaje público es intencionalmente genérico para no orientar al atacante.
 */
class NonceReplayException extends RuntimeException
{
    private string $nonce;

    public function __construct(string $nonce)
    {
        $this->nonce = $nonce;

        parent::__construct('Request nonce has already been used.');
    }

    public function getNonce(): string
    {
        return $this->nonce;
    }
}
