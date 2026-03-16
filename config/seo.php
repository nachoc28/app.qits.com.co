<?php

/*
|--------------------------------------------------------------------------
| SEO Module — SIGC QITS
|--------------------------------------------------------------------------
|
| Configuración centralizada del módulo SEO multi-empresa.
|
| Diseño:
|   - Los servicios (SearchConsoleClientService, Ga4ClientService) leen este
|     archivo para obtener credenciales y parámetros de sincronización.
|   - Cada empresa almacena solo su property identifier en empresa_seo_properties.
|   - Una sola cuenta de servicio de Google centraliza el acceso a todas
|     las propiedades GSC y GA4 registradas en la plataforma.
|
| Variables de entorno (.env):
|   GOOGLE_APPLICATION_CREDENTIALS=storage/app/google/service-account.json
|   SEO_SYNC_LOOKBACK_DAYS=30
|   SEO_GSC_LOOKBACK_DAYS=16
|   SEO_GA4_LOOKBACK_DAYS=3
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Credenciales de Google
    |--------------------------------------------------------------------------
    |
    | credentials_path: ruta absoluta al JSON de la cuenta de servicio.
    |   Se recomienda storage/app/google/service-account.json (NO en public/).
    |   El archivo debe estar excluido de git (.gitignore).
    |
    */

    'google' => [
        'credentials_path' => env('GOOGLE_APPLICATION_CREDENTIALS',
            storage_path('app/google/service-account.json')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Ventanas de sincronización
    |--------------------------------------------------------------------------
    |
    | gsc_lookback_days:
    |   GSC tiene hasta ~2 días de latencia; se re-sincronizan los últimos
    |   16 días para capturar correcciones retroactivas de la API.
    |
    | ga4_lookback_days:
    |   GA4 puede corregir datos hasta 72 horas atrás.
    |
    | default_lookback_days:
    |   Ventana general para re-sincronizaciones manuales o comandos Artisan.
    |
    */

    'sync' => [
        'default_lookback_days' => (int) env('SEO_SYNC_LOOKBACK_DAYS', 30),
        'gsc_lookback_days'     => (int) env('SEO_GSC_LOOKBACK_DAYS',  16),
        'ga4_lookback_days'     => (int) env('SEO_GA4_LOOKBACK_DAYS',   3),
        'batch_size'            => (int) env('SEO_SYNC_BATCH_SIZE',    500),
        'queue_name'            => env('SEO_SYNC_QUEUE_NAME', 'seo-sync'),
        'schedule_cron'         => env('SEO_SYNC_SCHEDULE_CRON', '30 * * * *'),
        'gsc_row_limit'         => (int) env('SEO_GSC_ROW_LIMIT',    25000),
        'gsc_top_queries_limit' => (int) env('SEO_GSC_TOP_QUERIES_LIMIT', 1000),
        'gsc_top_pages_limit'   => (int) env('SEO_GSC_TOP_PAGES_LIMIT',   1000),
        'ga4_landing_pages_limit' => (int) env('SEO_GA4_LANDING_PAGES_LIMIT', 1000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Parámetros específicos de GA4
    |--------------------------------------------------------------------------
    */

    'ga4' => [
        'organic_channel_group' => env('SEO_GA4_ORGANIC_CHANNEL_GROUP', 'Organic Search'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Parámetros del dashboard
    |--------------------------------------------------------------------------
    |
    | default_range_days: ventana por defecto al abrir el dashboard (28 días).
    | top_queries_limit:  máximo de queries a mostrar en la tabla de palabras clave.
    | top_pages_limit:    máximo de páginas a mostrar en la tabla de páginas.
    |
    */

    'dashboard' => [
        'default_range_days'  => (int) env('SEO_DASHBOARD_RANGE_DAYS',   28),
        'top_queries_limit'   => (int) env('SEO_TOP_QUERIES_LIMIT',       50),
        'top_pages_limit'     => (int) env('SEO_TOP_PAGES_LIMIT',         50),
        'top_landings_limit'  => (int) env('SEO_TOP_LANDINGS_LIMIT',      50),
    ],

    /*
    |--------------------------------------------------------------------------
    | Endpoint de ingesta UTM (WordPress → SIGC)
    |--------------------------------------------------------------------------
    |
    | El plugin WordPress de QITS empuja eventos de conversión vía POST a
    | /api/seo/utm-conversions/{empresa_id}. Este bloque define parámetros
    | de validación y seguridad del endpoint.
    |
    | max_batch_size: número máximo de conversiones por request en modo batch.
    |
    */

    'utm_ingestion' => [
        'max_batch_size' => (int) env('SEO_UTM_MAX_BATCH_SIZE', 100),
    ],

];
