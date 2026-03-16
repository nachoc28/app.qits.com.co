<?php

namespace App\Support\IntegrationSecurity;

/**
 * Tipos de evento de seguridad para integration_security_logs.event_type.
 *
 * Convención de nomenclatura:
 *   auth_*                      — eventos de autenticación (capa de firma/credenciales)
 *   authz_*                     — eventos de autorización (scopes / servicios de negocio)
 *   rate_*                      — eventos de límite de tasa
 *
 * Compatibilidad: PHP 7.4 (clase con constantes, sin enum).
 *
 * Extensibilidad:
 *   Añadir un evento nuevo = agregar una constante aquí.
 *   No tocar servicios ni modelos existentes.
 */
final class IntegrationEventType
{
    // ── Autenticación exitosa ─────────────────────────────────────────────────

    /** Request autenticado y autorizado correctamente. */
    const AUTH_SUCCESS = 'auth_success';

    // ── Fallos de autenticación ───────────────────────────────────────────────

    /** Uno o más headers requeridos (key, timestamp, nonce, signature) están ausentes. */
    const AUTH_FAILED_MISSING_HEADERS = 'auth_failed_missing_headers';

    /** El public key no corresponde a ninguna integración registrada. */
    const AUTH_FAILED_INVALID_KEY = 'auth_failed_invalid_key';

    /** La integración existe pero su status es 'suspended' o 'revoked'. */
    const AUTH_FAILED_INACTIVE_INTEGRATION = 'auth_failed_inactive_integration';

    /** La firma HMAC del request no coincide con la esperada. */
    const AUTH_FAILED_INVALID_SIGNATURE = 'auth_failed_invalid_signature';

    /** El timestamp del request está fuera de la ventana de tolerancia. */
    const AUTH_FAILED_EXPIRED_TIMESTAMP = 'auth_failed_expired_timestamp';

    /** El nonce ya fue usado anteriormente (ataque de replay). */
    const AUTH_FAILED_REPLAY = 'auth_failed_replay';

    // ── Fallos de autorización ────────────────────────────────────────────────

    /** La integración no tiene el scope técnico requerido para el endpoint solicitado. */
    const AUTH_FAILED_SCOPE_DENIED = 'auth_failed_scope_denied';

    /** La empresa no tiene activo el servicio de negocio requerido por el módulo. */
    const BUSINESS_ACCESS_DENIED_SERVICE_MISSING = 'business_access_denied_service_missing';

    // ── Límite de tasa ────────────────────────────────────────────────────────

    /** La integración superó su límite de requests permitidos. */
    const RATE_LIMIT_EXCEEDED = 'rate_limit_exceeded';

    // ── Hardening / spam signals ─────────────────────────────────────────────

    /** Se detectó una señal de hardening (payload sospechoso, repetición, etc.). */
    const HARDENING_SIGNAL_DETECTED = 'hardening_signal_detected';

    // ── Utilidades ────────────────────────────────────────────────────────────

    /** @return string[] */
    public static function all(): array
    {
        return [
            self::AUTH_SUCCESS,
            self::AUTH_FAILED_MISSING_HEADERS,
            self::AUTH_FAILED_INVALID_KEY,
            self::AUTH_FAILED_INACTIVE_INTEGRATION,
            self::AUTH_FAILED_INVALID_SIGNATURE,
            self::AUTH_FAILED_EXPIRED_TIMESTAMP,
            self::AUTH_FAILED_REPLAY,
            self::AUTH_FAILED_SCOPE_DENIED,
            self::BUSINESS_ACCESS_DENIED_SERVICE_MISSING,
            self::RATE_LIMIT_EXCEEDED,
            self::HARDENING_SIGNAL_DETECTED,
        ];
    }

    public static function isValid(string $eventType): bool
    {
        return in_array($eventType, self::all(), true);
    }

    private function __construct() {}
}
