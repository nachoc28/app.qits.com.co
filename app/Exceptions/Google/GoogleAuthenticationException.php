<?php

namespace App\Exceptions\Google;

use RuntimeException;

/**
 * Excepcion de autenticacion para integraciones Google a nivel global.
 */
class GoogleAuthenticationException extends RuntimeException
{
    public static function missingConfiguration(string $key): self
    {
        return new self('Google OAuth configuration missing: ' . $key);
    }

    public static function clientLibraryUnavailable(): self
    {
        return new self('google/apiclient is not installed or not available in runtime.');
    }

    public static function refreshFailed(string $detail = ''): self
    {
        $message = 'Unable to refresh Google access token using the configured refresh token.';

        if ($detail !== '') {
            $message .= ' ' . $detail;
        }

        return new self($message);
    }

    public static function invalidCredentials(string $detail = ''): self
    {
        $message = 'Google OAuth credentials appear invalid (client_id/client_secret/refresh_token).';

        if ($detail !== '') {
            $message .= ' ' . $detail;
        }

        return new self($message);
    }
}
