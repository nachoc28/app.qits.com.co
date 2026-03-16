<?php

namespace App\Support\IntegrationSecurity;

/**
 * Scopes del sistema de integraciones externas.
 *
 * Cada scope habilita el acceso a un módulo concreto en el middleware de seguridad.
 * El valor de cada constante coincide con el campo 'scope' en config/integration_security.php,
 * que a su vez debe aparecer en EmpresaIntegration::$scopes_json para que el acceso sea aprobado.
 *
 * Scope especial:
 *   WILDCARD ('*') — concede acceso a todos los módulos. Úsase solo en integraciones de confianza total.
 *
 * Extensibilidad:
 *   Añadir un scope nuevo = agregar una constante aquí + entrada en config/integration_security.php.
 */
final class IntegrationScope
{
    // ── Scopes de módulos ─────────────────────────────────────────────────────

    /** Permite enviar formularios al Módulo 1 (form ingress via WhatsApp API) */
    const FORM_INGRESS = 'module1.form_ingress';

    /** Permite interactuar con el flujo de conversación del Módulo 2 */
    const WHATSAPP_SOLUTION = 'module2.whatsapp_solution';

    /** Permite activar flujos de automatización avanzada del Módulo 3 */
    const AUTOMATION = 'module3.automation';

    /** Permite ingesta de conversiones UTM para el módulo SEO */
    const SEO_UTM_CONVERSIONS_INGEST = 'seo.utm_conversions_ingest';

    // ── Scope especial ────────────────────────────────────────────────────────

    /** Acceso total a todos los módulos (usar solo en integraciones internas de confianza plena) */
    const WILDCARD = '*';

    // ── Utilidades ────────────────────────────────────────────────────────────

    /**
     * Devuelve todos los scopes de módulo (excluye el wildcard).
     *
     * @return string[]
     */
    public static function all(): array
    {
        return [
            self::FORM_INGRESS,
            self::WHATSAPP_SOLUTION,
            self::AUTOMATION,
            self::SEO_UTM_CONVERSIONS_INGEST,
        ];
    }

    /**
     * Comprueba si una cadena es un scope conocido (incluye el wildcard).
     */
    public static function isValid(string $scope): bool
    {
        return $scope === self::WILDCARD || in_array($scope, self::all(), true);
    }

    // Clase utilitaria: no se instancia.
    private function __construct() {}
}
