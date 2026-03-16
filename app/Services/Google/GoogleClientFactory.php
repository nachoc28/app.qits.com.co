<?php

namespace App\Services\Google;

use App\Exceptions\Google\GoogleAuthenticationException;
use Google\Client as GoogleClient;

/**
 * Fabrica centralizada para configurar instancias de Google Client.
 *
 * Responsabilidad unica: construir clientes con la configuracion OAuth global,
 * scopes dinamicos y token de acceso opcional.
 */
class GoogleClientFactory
{
    /**
     * Crea un Google Client base con configuracion OAuth global.
     *
     * @param string[] $scopes
     * @return mixed
     */
    public function makeClient(array $scopes = [], ?array $accessToken = null)
    {
        $oauth = $this->oauthConfig();

        $this->ensureRequired($oauth, 'client_id');
        $this->ensureRequired($oauth, 'client_secret');
        $this->ensureRequired($oauth, 'redirect_uri');

        $client = $this->newGoogleClient();
        $this->setIfMethodExists($client, 'setClientId', (string) $oauth['client_id']);
        $this->setIfMethodExists($client, 'setClientSecret', (string) $oauth['client_secret']);
        $this->setIfMethodExists($client, 'setRedirectUri', (string) $oauth['redirect_uri']);

        if ($scopes !== []) {
            $this->setIfMethodExists($client, 'setScopes', $scopes);
        }

        $this->setIfMethodExists($client, 'setAccessType', 'offline');
        $this->setIfMethodExists($client, 'setIncludeGrantedScopes', true);

        if (is_array($accessToken) && $accessToken !== []) {
            $this->setIfMethodExists($client, 'setAccessToken', $accessToken);
        }

        return $client;
    }

    /**
     * @param array<string, mixed>|null $accessToken
     * @return mixed Google Client configurado para Search Console
     */
    public function makeSearchConsoleClient(?array $accessToken = null)
    {
        return $this->makeClient([
            'https://www.googleapis.com/auth/webmasters.readonly',
        ], $accessToken);
    }

    /**
     * @param array<string, mixed>|null $accessToken
     * @return mixed Google Client configurado para GA4 Data API
     */
    public function makeGa4Client(?array $accessToken = null)
    {
        return $this->makeClient([
            'https://www.googleapis.com/auth/analytics.readonly',
        ], $accessToken);
    }

    /**
     * @return array<string, mixed>
     */
    private function oauthConfig(): array
    {
        $google = (array) config('google.oauth', []);

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
     * @return mixed
     */
    private function newGoogleClient()
    {
        if (class_exists(GoogleClient::class)) {
            return new GoogleClient();
        }

        if (class_exists('Google_Client')) {
            $legacyClass = 'Google_Client';
            return new $legacyClass();
        }

        throw GoogleAuthenticationException::clientLibraryUnavailable();
    }

    /**
     * @param mixed $object
     * @param mixed $value
     */
    private function setIfMethodExists($object, string $method, $value): void
    {
        if (method_exists($object, $method)) {
            $object->{$method}($value);
        }
    }
}
