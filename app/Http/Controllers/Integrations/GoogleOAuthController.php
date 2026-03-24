<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\EmpresaIntegration;
use Google\Client as GoogleClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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

        $empresaId = Empresa::query()
            ->where('is_internal', true)
            ->value('id');

        if (! $empresaId) {
            $empresaId = Empresa::query()->orderBy('id')->value('id');
        }

        if (! $empresaId) {
            return response('No fue posible persistir GOOGLE_REFRESH_TOKEN en base de datos.', 500, ['Content-Type' => 'text/plain']);
        }

        $integration = EmpresaIntegration::query()
            ->where('empresa_id', (int) $empresaId)
            ->where('provider_type', 'google_oauth')
            ->orderByDesc('id')
            ->first();

        if (! $integration) {
            do {
                $publicKey = 'gogl_' . Str::lower(Str::random(32));
            } while (EmpresaIntegration::query()->where('public_key', $publicKey)->exists());

            $integration = EmpresaIntegration::create([
                'empresa_id' => (int) $empresaId,
                'name' => 'Google OAuth Global',
                'provider_type' => 'google_oauth',
                'public_key' => $publicKey,
                'secret_hash' => Hash::make(Str::random(64)),
                'status' => 'active',
                'scopes_json' => [],
                'meta_json' => [],
            ]);
        }

        $meta = is_array($integration->meta_json) ? $integration->meta_json : [];
        $meta['google_refresh_token_encrypted'] = Crypt::encryptString($refreshToken);

        $integration->forceFill([
            'meta_json' => $meta,
        ])->save();

        config(['google.oauth.refresh_token' => $refreshToken]);

        return response('Google connected successfully', 200, ['Content-Type' => 'text/plain']);
    }
}
