<?php

namespace App\Services\Google;

use App\Exceptions\Google\GoogleAuthenticationException;

/**
 * Servicio global de autenticacion OAuth2 para Google.
 *
 * Responsabilidades:
 *  - cargar configuracion OAuth desde config
 *  - construir cliente Google autenticable
 *  - aplicar refresh token del sistema
 *  - refrescar access token cuando sea necesario
 */
class GoogleOAuthTokenService
{
    /** @var GoogleClientFactory */
    private $clientFactory;

    public function __construct(GoogleClientFactory $clientFactory)
    {
        $this->clientFactory = $clientFactory;
    }

    /**
     * Crea un cliente Google configurado para OAuth2.
     *
     * @param string[] $scopes
     * @return mixed
     */
    public function createClient(array $scopes = [])
    {
        return $this->clientFactory->makeClient($scopes);
    }

    /**
     * Devuelve un cliente listo para usar con access token vigente.
     *
     * @param string[] $scopes
     * @return mixed
     */
    public function ensureAuthenticatedClient(array $scopes = [])
    {
        $client = $this->createClient($scopes);

        $this->refreshAccessToken($client);

        return $client;
    }

    /**
     * Refresca el access token con el refresh token del sistema.
     *
     * @param mixed $client
     */
    public function refreshAccessToken($client): void
    {
        $normalized = $this->requestAccessTokenDataFromRefreshToken($client);

        if (method_exists($client, 'setAccessToken')) {
            $client->setAccessToken([
                'access_token' => $normalized['access_token'],
                'expires_in' => $normalized['expires_in'],
                'created' => $normalized['created'],
                'token_type' => $normalized['token_type'],
                'scope' => $normalized['scope'],
            ]);
        }

        if (method_exists($client, 'isAccessTokenExpired') && $client->isAccessTokenExpired()) {
            throw GoogleAuthenticationException::refreshFailed('Token still appears expired after refresh attempt.');
        }
    }

    /**
     * Solicita un access token nuevo usando el refresh token configurado.
     *
     * @param string[] $scopes
     * @return array<string, mixed>
     */
    public function refreshAccessTokenData(array $scopes = []): array
    {
        $client = $this->createClient($scopes);

        return $this->requestAccessTokenDataFromRefreshToken($client);
    }

    /**
     * @return array<string, mixed>
     */
    private function oauthConfig(): array
    {
        $google = (array) config('google.oauth', []);

        // Fallback para convencion Laravel en config/services.php.
        if ($google === []) {
            $google = (array) config('services.google', []);
        }

        return $google;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function ensureRequired(array $config, string $key): void
    {
        $value = isset($config[$key]) ? (string) $config[$key] : '';

        if (trim($value) === '') {
            throw GoogleAuthenticationException::missingConfiguration('google.oauth.' . $key);
        }
    }

    /**
     * @param mixed $client
     * @return array<string, mixed>
     */
    private function requestAccessTokenDataFromRefreshToken($client): array
    {
        $oauth = $this->oauthConfig();
        $this->ensureRequired($oauth, 'refresh_token');

        $refreshToken = trim((string) $oauth['refresh_token']);

        if (! method_exists($client, 'fetchAccessTokenWithRefreshToken')) {
            throw GoogleAuthenticationException::refreshFailed('Google client does not support refresh token flow.');
        }

        $result = $client->fetchAccessTokenWithRefreshToken($refreshToken);

        if (! is_array($result)) {
            throw GoogleAuthenticationException::refreshFailed('Unexpected response from token endpoint.');
        }

        if (isset($result['error'])) {
            $error = (string) $result['error'];
            $detail = isset($result['error_description'])
                ? (string) $result['error_description']
                : $error;

            if (in_array($error, ['invalid_client', 'invalid_grant', 'unauthorized_client'], true)) {
                throw GoogleAuthenticationException::invalidCredentials($detail);
            }

            throw GoogleAuthenticationException::refreshFailed($detail);
        }

        $accessToken = isset($result['access_token']) ? trim((string) $result['access_token']) : '';

        if ($accessToken === '') {
            throw GoogleAuthenticationException::refreshFailed('Token endpoint did not return access_token.');
        }

        return [
            'access_token' => $accessToken,
            'token_type' => isset($result['token_type']) ? (string) $result['token_type'] : 'Bearer',
            'expires_in' => isset($result['expires_in']) ? (int) $result['expires_in'] : 0,
            'created' => time(),
            'scope' => isset($result['scope']) ? (string) $result['scope'] : '',
            'refresh_token' => $refreshToken,
        ];
    }
}
