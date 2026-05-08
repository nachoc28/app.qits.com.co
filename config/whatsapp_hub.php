<?php

return [

    /*
    |--------------------------------------------------------------------------
    | WhatsApp Automation Hub — requisitos de módulos
    |--------------------------------------------------------------------------
    |
    | Define qué servicio del catálogo (tabla servicios) habilita cada módulo.
    | Se puede validar por id (rápido) o por slug (desacoplado de la BBDD).
    |
    | Para ampliar a un módulo nuevo, basta añadir una nueva entrada aquí y
    | referenciarla en ModuleAccessService sin tocar ningún controlador.
    */

    'modules' => [
        'module_1' => [
            'service_id'   => 1,
            'service_slug' => 'formularios-whatsapp-api',
            'description'  => 'Reenvío de formularios vía WhatsApp API',
        ],
        'module_2_3' => [
            'service_id'   => 3,
            'service_slug' => 'whatsapp-qits-solution',
            'description'  => 'WhatsApp QITS Solution (etapas 2 y 3)',
        ],
    ],

    'pdf_storage' => [
        // Disco de almacenamiento para PDFs de leads.
        // Para hosting compartido puede mantenerse en local.
        'disk' => env('WHATSAPP_HUB_PDF_DISK', 'local'),

        // Directorio base deterministico por empresa/lead.
        'base_dir' => env('WHATSAPP_HUB_PDF_BASE_DIR', 'whatsapp_hub/leads'),
    ],

    'cloud_api' => [
        // Endpoint base de WhatsApp Cloud API.
        'base_url' => env('WHATSAPP_CLOUD_API_BASE_URL', 'https://graph.facebook.com'),

        // Version de Graph API usada para envio.
        'version' => env('WHATSAPP_CLOUD_API_VERSION', 'v20.0'),

        // Timeout HTTP para requests salientes.
        'timeout_seconds' => (int) env('WHATSAPP_CLOUD_API_TIMEOUT', 20),
    ],

    // Credenciales globales del numero emisor aprobado de QITS para templates.
    'template_sender' => [
        'access_token' => env('WHATSAPP_TOKEN', ''),
        'phone_number_id' => env('WHATSAPP_PHONE_ID', ''),
        // Reservado para administracion futura (no requerido por sendTemplate actual).
        'waba_id' => env('WHATSAPP_WABA_ID', ''),
    ],

    'dispatch' => [
        // true: encola el envio; false: envio sincrono.
        'async' => env('WHATSAPP_HUB_DISPATCH_ASYNC', true),

        // Compatible con database queue en hosting compartido.
        'queue_connection' => env('WHATSAPP_HUB_QUEUE_CONNECTION'),
        'queue_name' => env('WHATSAPP_HUB_QUEUE_NAME', 'whatsapp-hub'),
    ],

    // URL base para links publicos (fallback a APP_URL si no se define).
    'public_link_base_url' => env('WHATSAPP_HUB_PUBLIC_LINK_BASE_URL', env('APP_URL', '')),

    // Plantilla usada para notificaciones de formularios WordPress.
    'form_notifications_template' => [
        'name' => env('WHATSAPP_HUB_FORM_TEMPLATE_NAME', ''),
        'language' => env('WHATSAPP_HUB_FORM_TEMPLATE_LANGUAGE', 'es_CO'),
        // Valores esperados: approved | pending | disabled
        'status' => env('WHATSAPP_HUB_FORM_TEMPLATE_STATUS', 'pending'),
    ],

    // Días de validez del enlace público enviado por WhatsApp.
    // El controlador rechaza con 404 cualquier link expirado o sin fecha.
    'form_notification_public_link_ttl_days' => (int) env('WHATSAPP_HUB_PUBLIC_LINK_TTL_DAYS', 7),

];
