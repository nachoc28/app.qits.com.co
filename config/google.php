<?php

/*
|--------------------------------------------------------------------------
| Google Integrations (Global)
|--------------------------------------------------------------------------
|
| Configuracion global de integraciones Google para SIGC QITS.
| Esta capa NO depende de empresa y se reutiliza para modulos actuales
| y futuros (SEO, reporting, etc.).
|
| Regla arquitectonica:
| - Las credenciales OAuth viven a nivel sistema.
| - Las empresas solo almacenan identificadores funcionales (p. ej.
|   Search Console property y GA4 property id).
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | OAuth2 (Cuenta central del sistema)
    |--------------------------------------------------------------------------
    */
    'oauth' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect_uri' => env('GOOGLE_REDIRECT_URI'),
        'refresh_token' => env('GOOGLE_REFRESH_TOKEN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Service Account (opcional, para flujos server-to-server)
    |--------------------------------------------------------------------------
    */
    'credentials' => [
        'service_account_path' => env(
            'GOOGLE_APPLICATION_CREDENTIALS',
            storage_path('app/google/service-account.json')
        ),
    ],

];
