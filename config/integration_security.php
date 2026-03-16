<?php

/*
|--------------------------------------------------------------------------
| Integration Security Layer — SIGC QITS
|--------------------------------------------------------------------------
|
| Configuración centralizada para la capa de seguridad de integraciones
| externas (WordPress, sitios de terceros, futuras integraciones).
|
| Diseño:
|   - Los controladores NO consultan esta estructura directamente.
|   - Todo el acceso pasa por App\Support\IntegrationSecurity\ModuleRegistry
|     y/o los servicios de autenticación correspondientes.
|   - Para añadir un módulo nuevo: agregar una entrada en 'modules' y referirse
|     a su clave mediante IntegrationModule::NUEVA_CLAVE.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Headers de autenticación requeridos
    |--------------------------------------------------------------------------
    |
    | Nombres canónicos de los headers HTTP que toda request externa firmada
    | debe incluir. Centralizar aquí evita strings dispersos en middlewares.
    |
    */

    'headers' => [
        'public_key' => env('INTEGRATION_HEADER_KEY',       'X-QITS-Key'),
        'timestamp'  => env('INTEGRATION_HEADER_TIMESTAMP', 'X-QITS-Timestamp'),
        'nonce'      => env('INTEGRATION_HEADER_NONCE',     'X-QITS-Nonce'),
        'signature'  => env('INTEGRATION_HEADER_SIGNATURE', 'X-QITS-Signature'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Ventana de tolerancia de timestamp (segundos)
    |--------------------------------------------------------------------------
    |
    | Diferencia máxima permitida entre el timestamp de la request y now().
    | Protege contra ataques de replay tardíos aun sin nonce almacenado.
    | Valor recomendado: 300 (±5 minutos).
    |
    */

    'timestamp_tolerance_seconds' => (int) env('INTEGRATION_TIMESTAMP_TOLERANCE', 300),

    /*
    |--------------------------------------------------------------------------
    | TTL de nonces (segundos)
    |--------------------------------------------------------------------------
    |
    | Ventana de vida de cada nonce almacenado en integration_request_nonces.
    | Debe ser >= timestamp_tolerance_seconds para que la protección sea efectiva.
    | Un job programado puede purgar nonces expirados con IntegrationRequestNonce::expired().
    |
    */

    'nonce_ttl_seconds' => (int) env('INTEGRATION_NONCE_TTL', 300),

    /*
    |--------------------------------------------------------------------------
    | Algoritmo de firma
    |--------------------------------------------------------------------------
    |
    | Algoritmo HMAC usado para verificar la firma del request.
    | Debe coincidir con el algoritmo que usa el cliente externo.
    |
    */

    'signature_algorithm' => env('INTEGRATION_SIGNATURE_ALGO', 'sha256'),

    /*
    |--------------------------------------------------------------------------
    | Perfiles de rate limiting
    |--------------------------------------------------------------------------
    |
    | Cada integración (empresa_integrations.rate_limit_profile) apunta a
    | uno de estos perfiles. Si el campo es null se usa el perfil por defecto
    | definido en 'rate_limit.default_profile'.
    |
    | rpm   → requests por minuto (ventana deslizante)
    | burst → rafaga máxima instantánea
    |
    */

    'rate_limit_profiles' => [
        'normal' => [
            'rpm'   => (int) env('INTEGRATION_RATE_NORMAL_RPM',   60),
            'burst' => (int) env('INTEGRATION_RATE_NORMAL_BURST', 10),
            'burst_window_seconds' => (int) env('INTEGRATION_RATE_NORMAL_BURST_WINDOW', 10),
        ],
        'strict' => [
            'rpm'   => (int) env('INTEGRATION_RATE_STRICT_RPM',   20),
            'burst' => (int) env('INTEGRATION_RATE_STRICT_BURST',  5),
            'burst_window_seconds' => (int) env('INTEGRATION_RATE_STRICT_BURST_WINDOW', 10),
        ],
        'high_volume' => [
            'rpm'   => (int) env('INTEGRATION_RATE_HIGH_VOLUME_RPM',   300),
            'burst' => (int) env('INTEGRATION_RATE_HIGH_VOLUME_BURST',  50),
            'burst_window_seconds' => (int) env('INTEGRATION_RATE_HIGH_VOLUME_BURST_WINDOW', 10),
        ],

        // Alias de compatibilidad con versiones previas.
        'default' => [
            'rpm'   => (int) env('INTEGRATION_RATE_DEFAULT_RPM',   60),
            'burst' => (int) env('INTEGRATION_RATE_DEFAULT_BURST', 10),
            'burst_window_seconds' => (int) env('INTEGRATION_RATE_DEFAULT_BURST_WINDOW', 10),
        ],
        'high' => [
            'rpm'   => (int) env('INTEGRATION_RATE_HIGH_RPM',   300),
            'burst' => (int) env('INTEGRATION_RATE_HIGH_BURST',  50),
            'burst_window_seconds' => (int) env('INTEGRATION_RATE_HIGH_BURST_WINDOW', 10),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Controles operativos de rate limiting
    |--------------------------------------------------------------------------
    |
    | Configuración de dimensiones y valores globales usados por
    | IntegrationRateLimitService.
    |
    */

    'rate_limit' => [
        // Perfil a usar cuando la integración no define uno propio.
        'default_profile' => env('INTEGRATION_RATE_DEFAULT_PROFILE', 'normal'),

        // Ventana por defecto para la ráfaga (si el perfil no la define).
        'burst_window_seconds' => (int) env('INTEGRATION_RATE_BURST_WINDOW', 10),

        // Dimensiones habilitadas por defecto.
        'dimensions' => [
            'integration' => env('INTEGRATION_RATE_DIM_INTEGRATION', true),
            'empresa'     => env('INTEGRATION_RATE_DIM_EMPRESA', true),
            'ip'          => env('INTEGRATION_RATE_DIM_IP', true),
            'endpoint'    => env('INTEGRATION_RATE_DIM_ENDPOINT', false),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Hardening / spam signals
    |--------------------------------------------------------------------------
    |
    | Heurísticas genéricas para detección temprana de tráfico sospechoso.
    | SpamSignalService consume este bloque y devuelve señales estructuradas.
    |
    */

    'hardening' => [
        // Tamaño máximo de payload en bytes (0 deshabilita).
        'max_payload_bytes' => (int) env('INTEGRATION_HARDENING_MAX_PAYLOAD_BYTES', 200000),

        // Score mínimo para marcar la solicitud como sospechosa.
        'score_threshold' => (int) env('INTEGRATION_HARDENING_SCORE_THRESHOLD', 60),

        // Patrones sospechosos básicos (regex o texto simple).
        'suspicious_patterns' => [
            '/<script\\b/i',
            '/select\\s+.*from/i',
            '/union\\s+select/i',
            '/drop\\s+table/i',
            '/\\.\\.\\//',
        ],

        // Campos que un endpoint puede requerir por defecto.
        // Cada endpoint puede sobreescribirlo en tiempo de ejecución.
        'required_fields' => [],

        // Detección de repeticiones en ventana corta.
        'repeat_submission' => [
            'window_seconds' => (int) env('INTEGRATION_HARDENING_REPEAT_WINDOW', 30),
            'max_attempts'   => (int) env('INTEGRATION_HARDENING_REPEAT_MAX_ATTEMPTS', 3),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Módulos: mapeo scope ↔ servicio de negocio requerido
    |--------------------------------------------------------------------------
    |
    | Cada entrada define:
    |   scope                 — string que debe estar en scopes_json de la integración
    |   required_service_id   — id en tabla servicios que la empresa debe tener activo
    |   required_service_slug — slug alternativo (útil si los ids difieren entre entornos)
    |   description           — texto legible para logs y UI de administración
    |
    | Las claves de módulo están disponibles como constantes en
    | App\Support\IntegrationSecurity\IntegrationModule.
    |
    */

    'modules' => [

        // ── Módulo 1: reenvío de formularios ─────────────────────────────────
        'module1.form_ingress' => [
            'scope'                 => 'module1.form_ingress',
            'required_service_id'   => 1,
            'required_service_slug' => 'formularios-whatsapp-api',
            'description'           => 'Reenvío de formularios vía WhatsApp API',
        ],

        // ── Módulo 2: WhatsApp QITS Solution (etapa 2) ───────────────────────
        'module2.whatsapp_solution' => [
            'scope'                 => 'module2.whatsapp_solution',
            'required_service_id'   => 3,
            'required_service_slug' => 'whatsapp-qits-solution',
            'description'           => 'WhatsApp QITS Solution — conversación inbound (etapa 2)',
        ],

        // ── Módulo 3: automatización full (etapa 3) ───────────────────────────
        'module3.automation' => [
            'scope'                 => 'module3.automation',
            'required_service_id'   => 3,
            'required_service_slug' => 'whatsapp-qits-solution',
            'description'           => 'Automatización y flujos avanzados (etapa 3)',
        ],

        // ── SEO: ingesta de conversiones UTM desde WordPress ───────────────
        'seo.utm_conversions_ingest' => [
            'scope'                 => 'seo.utm_conversions_ingest',
            'required_service_id'   => 1,
            'required_service_slug' => 'sitio-web',
            'description'           => 'Ingesta de conversiones UTM para dashboard SEO',
        ],

    ],

];
