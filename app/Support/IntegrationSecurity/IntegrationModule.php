<?php

namespace App\Support\IntegrationSecurity;

/**
 * Claves canónicas de módulos del sistema de integraciones externas.
 *
 * Uso:
 *   $registry->get(IntegrationModule::MODULE1_FORM_INGRESS);
 *   $integration->hasScope(IntegrationModule::scopeFor(IntegrationModule::MODULE1_FORM_INGRESS));
 *
 * Por qué clase con constantes en vez de enum:
 *   - Compatibilidad con PHP 7.4 (enums requieren PHP >= 8.1).
 *   - Las constantes de clase son directamente usables como strings en arrays y config.
 *
 * Extensibilidad:
 *   Añadir un módulo nuevo = agregar una constante aquí + entrada en config/integration_security.php.
 *   No se requieren cambios en controladores ni middlewares existentes.
 */
final class IntegrationModule
{
    // ── Módulo 1 ──────────────────────────────────────────────────────────────
    /** Reenvío de formularios web vía WhatsApp Cloud API */
    const MODULE1_FORM_INGRESS = 'module1.form_ingress';

    // ── Módulo 2 ──────────────────────────────────────────────────────────────
    /** Conversación inbound y gestión de mensajes (WhatsApp QITS Solution) */
    const MODULE2_WHATSAPP_SOLUTION = 'module2.whatsapp_solution';

    // ── Módulo 3 ──────────────────────────────────────────────────────────────
    /** Automatización de flujos avanzados */
    const MODULE3_AUTOMATION = 'module3.automation';

    // ── SEO ───────────────────────────────────────────────────────────────────
    /** Ingesta de conversiones UTM desde sitios WordPress */
    const SEO_UTM_CONVERSIONS_INGEST = 'seo.utm_conversions_ingest';

    // ── Utilidades ────────────────────────────────────────────────────────────

    /**
     * Devuelve todas las claves de módulo definidas como constantes.
     *
     * @return string[]
     */
    public static function all(): array
    {
        return [
            self::MODULE1_FORM_INGRESS,
            self::MODULE2_WHATSAPP_SOLUTION,
            self::MODULE3_AUTOMATION,
            self::SEO_UTM_CONVERSIONS_INGEST,
        ];
    }

    /**
     * Comprueba si una cadena es una clave de módulo válida.
     */
    public static function isValid(string $key): bool
    {
        return in_array($key, self::all(), true);
    }

    // Clase utilitaria: no se instancia.
    private function __construct() {}
}
