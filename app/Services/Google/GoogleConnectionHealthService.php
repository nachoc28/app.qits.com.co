<?php

namespace App\Services\Google;

use App\Services\Google\GoogleConnectionHealthStatus;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Diagnostico global de conexion Google (OAuth central del sistema).
 *
 * No depende de configuraciones por empresa.
 */
class GoogleConnectionHealthService
{
    private const LAST_STATUS_CACHE_KEY = 'google_integration:last_health_status';

    /** @var GoogleOAuthTokenService */
    private $tokenService;

    /** @var GoogleClientFactory */
    private $clientFactory;

    public function __construct(GoogleOAuthTokenService $tokenService, GoogleClientFactory $clientFactory)
    {
        $this->tokenService = $tokenService;
        $this->clientFactory = $clientFactory;
    }

    public function diagnose(): GoogleConnectionHealthStatus
    {
        $checkedAt = now()->toDateTimeString();

        $requiredKeys = [
            'client_id',
            'client_secret',
            'redirect_uri',
            'refresh_token',
        ];

        $oauth = (array) config('google.oauth', []);

        $missing = [];
        foreach ($requiredKeys as $key) {
            $value = isset($oauth[$key]) ? trim((string) $oauth[$key]) : '';
            if ($value === '') {
                $missing[] = 'google.oauth.' . $key;
            }
        }

        $checks = [
            'config_loaded' => true,
            'required_values_present' => $missing === [],
            'client_initialized' => false,
            'refresh_token_flow' => false,
            'access_token_usable' => false,
            'search_console_client_ready' => false,
            'ga4_client_ready' => false,
        ];

        if (count($missing) === count($requiredKeys)) {
            return $this->storeLastKnownStatus(new GoogleConnectionHealthStatus(
                GoogleConnectionHealthStatus::STATE_NOT_CONFIGURED,
                $checks,
                $missing,
                [],
                [
                    'provider' => 'google',
                    'auth_mode' => 'oauth_refresh_token',
                    'checked_at' => $checkedAt,
                ]
            ));
        }

        if ($missing !== []) {
            return $this->storeLastKnownStatus(new GoogleConnectionHealthStatus(
                GoogleConnectionHealthStatus::STATE_PARTIALLY_CONFIGURED,
                $checks,
                $missing,
                [],
                [
                    'provider' => 'google',
                    'auth_mode' => 'oauth_refresh_token',
                    'checked_at' => $checkedAt,
                ]
            ));
        }

        $errors = [];

        try {
            $client = $this->tokenService->createClient([
                'https://www.googleapis.com/auth/webmasters.readonly',
            ]);
            $checks['client_initialized'] = true;

            $this->tokenService->refreshAccessToken($client);
            $checks['refresh_token_flow'] = true;

            $checks['access_token_usable'] = $this->hasUsableAccessToken($client);

            $searchConsoleClient = $this->clientFactory->makeSearchConsoleClient();
            $this->tokenService->refreshAccessToken($searchConsoleClient);
            $checks['search_console_client_ready'] = $this->hasUsableAccessToken($searchConsoleClient);

            $ga4Client = $this->clientFactory->makeGa4Client();
            $this->tokenService->refreshAccessToken($ga4Client);
            $checks['ga4_client_ready'] = $this->hasUsableAccessToken($ga4Client);

            $fullyConnected = $checks['client_initialized']
                && $checks['refresh_token_flow']
                && $checks['access_token_usable']
                && $checks['search_console_client_ready']
                && $checks['ga4_client_ready'];

            return $this->storeLastKnownStatus(new GoogleConnectionHealthStatus(
                $fullyConnected
                    ? GoogleConnectionHealthStatus::STATE_CONNECTED
                    : GoogleConnectionHealthStatus::STATE_FAILED,
                $checks,
                [],
                [],
                [
                    'provider' => 'google',
                    'auth_mode' => 'oauth_refresh_token',
                    'checked_at' => $checkedAt,
                ]
            ));
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();

            return $this->storeLastKnownStatus(new GoogleConnectionHealthStatus(
                GoogleConnectionHealthStatus::STATE_FAILED,
                $checks,
                [],
                $errors,
                [
                    'provider' => 'google',
                    'auth_mode' => 'oauth_refresh_token',
                    'checked_at' => $checkedAt,
                    'exception' => get_class($e),
                ]
            ));
        }
    }

    public function lastKnownStatus(): ?GoogleConnectionHealthStatus
    {
        $payload = Cache::get(self::LAST_STATUS_CACHE_KEY);

        if (! is_array($payload)) {
            return null;
        }

        return GoogleConnectionHealthStatus::fromArray($payload);
    }

    /**
     * @param mixed $client
     */
    private function hasUsableAccessToken($client): bool
    {
        if (! method_exists($client, 'getAccessToken')) {
            return false;
        }

        $token = $client->getAccessToken();

        if (! is_array($token) || empty($token['access_token'])) {
            return false;
        }

        if (method_exists($client, 'isAccessTokenExpired') && $client->isAccessTokenExpired()) {
            return false;
        }

        return true;
    }

    private function storeLastKnownStatus(GoogleConnectionHealthStatus $status): GoogleConnectionHealthStatus
    {
        Cache::put(self::LAST_STATUS_CACHE_KEY, $status->toArray(), now()->addHours(12));

        return $status;
    }
}
