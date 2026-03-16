<?php

namespace App\Services\WhatsAppHub;

/**
 * Normaliza los datos crudos del request antes de persistirlos.
 *
 * Reglas aplicadas:
 *  - Nombres: trim + ucwords sin alterar acentos.
 *  - Teléfonos: solo dígitos, +, espacios, guiones, paréntesis.
 *  - Email: strtolower + trim.
 *  - Campos de texto opcionales: trim o null si quedan vacíos.
 *  - URLs: trim.
 *  - El payload original se preserva sin modificar (para auditoría).
 */
class LeadNormalizerService
{
    /**
     * Convierte el array validado del FormIngressRequest en un DTO normalizado.
     *
     * @param  array<string,mixed>  $data
     * @param  string|null          $ruleFormName  nombre del formulario definido en la regla
     */
    public function normalize(array $data, ?string $ruleFormName = null): NormalizedLeadData
    {
        return new NormalizedLeadData(
                $this->normalizeName($data['nombre'] ?? ''),
                $this->normalizePhone($data['telefono'] ?? ''),
                $this->normalizeEmail($data['correo'] ?? null),
                $this->normalizeText($data['empresa'] ?? null),
                $this->normalizeText($data['ciudad'] ?? null),
                $this->normalizeText($data['mensaje'] ?? null),
                $this->normalizeText($data['formulario'] ?? $ruleFormName),
                $this->normalizeUrl($data['url_origen'] ?? null),
                $data['payload_completo'] ?? null,
                $this->normalizeText($data['domain']      ?? null),
                $this->normalizeUrl($data['page_url']     ?? null),
                $this->normalizeText($data['utm_source']  ?? null),
                $this->normalizeText($data['utm_campaign'] ?? null),
                $this->normalizeText($data['campaign']    ?? null),
                $this->normalizeText($data['medium']      ?? null)
        );
    }

    // ── Métodos de normalización ─────────────────────────────────────────────

    private function normalizeName(string $value): string
    {
        // mb_convert_case preserva acentos, ucwords no lo hace correctamente.
        $clean = trim($value);
        return mb_convert_case($clean, MB_CASE_TITLE, 'UTF-8');
    }

    private function normalizePhone(string $value): string
    {
        // Conserva +, dígitos, espacios, guiones y paréntesis.
        // Elimina cualquier otro carácter inesperado.
        return trim(preg_replace('/[^\d\+\s\-\(\)]/', '', $value));
    }

    private function normalizeEmail(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        return strtolower(trim($value));
    }

    private function normalizeText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $clean = trim($value);
        return $clean !== '' ? $clean : null;
    }

    private function normalizeUrl(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $clean = trim($value);
        return $clean !== '' ? $clean : null;
    }
}
