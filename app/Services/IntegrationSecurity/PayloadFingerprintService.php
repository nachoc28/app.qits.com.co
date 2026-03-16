<?php

namespace App\Services\IntegrationSecurity;

use Illuminate\Http\Request;

/**
 * Genera fingerprints estables para requests entrantes.
 *
 * Uso:
 *  - detección de duplicados
 *  - trazabilidad en logs de seguridad
 *  - soporte de heurísticas anti-spam
 */
class PayloadFingerprintService
{
    /**
     * Genera fingerprint SHA-256 estable a partir del request.
     *
     * @param array<string, mixed> $context Campos opcionales para reforzar unicidad.
     */
    public function fromRequest(Request $request, array $context = []): string
    {
        $method = strtoupper($request->method());
        $path = '/' . ltrim($request->path(), '/');
        $query = $this->normalizeValue($request->query());
        $body = $this->normalizeRequestBody($request);

        $base = [
            'method' => $method,
            'path' => $path,
            'query' => $query,
            'body' => $body,
            'context' => $this->normalizeValue($context),
        ];

        return hash('sha256', json_encode($base, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Genera fingerprint estable a partir de un array cualquiera.
     *
     * @param array<string, mixed> $payload
     */
    public function fromArray(array $payload): string
    {
        return hash(
            'sha256',
            json_encode($this->normalizeValue($payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    private function normalizeRequestBody(Request $request)
    {
        $raw = (string) $request->getContent();

        if ($raw === '') {
            return '';
        }

        $decoded = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $this->normalizeValue($decoded);
        }

        return trim($raw);
    }

    /**
     * Normaliza recursivamente arrays para garantizar orden determinista.
     *
     * @param mixed $value
     * @return mixed
     */
    private function normalizeValue($value)
    {
        if (is_array($value)) {
            if ($this->isAssoc($value)) {
                ksort($value);
            }

            foreach ($value as $k => $v) {
                $value[$k] = $this->normalizeValue($v);
            }

            return $value;
        }

        if (is_bool($value) || is_int($value) || is_float($value) || is_null($value)) {
            return $value;
        }

        return (string) $value;
    }

    /**
     * @param array<int|string, mixed> $array
     */
    private function isAssoc(array $array): bool
    {
        return array_keys($array) !== range(0, count($array) - 1);
    }
}
