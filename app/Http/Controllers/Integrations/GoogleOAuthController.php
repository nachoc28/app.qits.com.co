<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use Google\Client as GoogleClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Controlador temporal para bootstrap OAuth de Google.
 *
 * Uso: obtener manualmente el refresh token inicial del sistema.
 * No usar como integracion permanente.
 */
class GoogleOAuthController extends Controller
{
    public function connect(): RedirectResponse
    {
        $oauth = (array) config('google.oauth', []);

        $clientId = isset($oauth['client_id']) ? trim((string) $oauth['client_id']) : '';
        $clientSecret = isset($oauth['client_secret']) ? trim((string) $oauth['client_secret']) : '';
        $redirectUri = isset($oauth['redirect_uri']) ? trim((string) $oauth['redirect_uri']) : '';

        if ($clientId === '' || $clientSecret === '' || $redirectUri === '') {
            return redirect()->back()->with('error', 'Faltan GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET o GOOGLE_REDIRECT_URI.');
        }

        $client = new GoogleClient();
        $client->setClientId($clientId);
        $client->setClientSecret($clientSecret);
        $client->setRedirectUri($redirectUri);
        $client->setScopes([
            'https://www.googleapis.com/auth/webmasters.readonly',
            'https://www.googleapis.com/auth/analytics.readonly',
        ]);
        $client->setAccessType('offline');
        $client->setPrompt('consent');

        $authUrl = $client->createAuthUrl();

        return redirect()->away($authUrl);
    }

    public function callback(Request $request): Response
    {
        $code = trim((string) $request->query('code', ''));

        if ($code === '') {
            return response('No se recibio authorization code.', 422, ['Content-Type' => 'text/plain']);
        }

        $oauth = (array) config('google.oauth', []);

        $client = new GoogleClient();
        $client->setClientId(isset($oauth['client_id']) ? trim((string) $oauth['client_id']) : '');
        $client->setClientSecret(isset($oauth['client_secret']) ? trim((string) $oauth['client_secret']) : '');
        $client->setRedirectUri(isset($oauth['redirect_uri']) ? trim((string) $oauth['redirect_uri']) : '');

        $tokenPayload = $client->fetchAccessTokenWithAuthCode($code);

        if (! is_array($tokenPayload)) {
            return response('No fue posible intercambiar el authorization code.', 500, ['Content-Type' => 'text/plain']);
        }

        if (isset($tokenPayload['error'])) {
            $error = (string) $tokenPayload['error'];
            $description = isset($tokenPayload['error_description'])
                ? (string) $tokenPayload['error_description']
                : '';

            return response('Error OAuth: ' . $error . ($description !== '' ? ' - ' . $description : ''), 500, ['Content-Type' => 'text/plain']);
        }

        $refreshToken = isset($tokenPayload['refresh_token']) ? trim((string) $tokenPayload['refresh_token']) : '';

        if ($refreshToken === '') {
            return response(
                "No se recibio refresh_token. Reintente con prompt=consent y acceso offline, usando una cuenta no autorizada previamente o revocando permisos.",
                422,
                ['Content-Type' => 'text/plain']
            );
        }

        return response("GOOGLE_REFRESH_TOKEN=" . $refreshToken, 200, ['Content-Type' => 'text/plain']);
    }
}
