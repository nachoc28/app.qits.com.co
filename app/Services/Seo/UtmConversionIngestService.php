<?php

namespace App\Services\Seo;

use App\Models\Empresa;
use App\Models\EmpresaIntegration;
use App\Models\SeoUtmConversion;
use Illuminate\Support\Carbon;

/**
 * Recibe, valida y persiste conversiones UTM empujadas desde WordPress.
 *
 * Convención del payload entrante (WordPress → SIGC QITS):
 * {
 *   "conversion_datetime": "2026-03-15T14:23:00",   // ISO 8601, UTC — REQUERIDO
 *   "page_url":            "https://sitio.com/contacto",
 *   "form_name":           "Formulario Contacto",
 *   "source":              "google",
 *   "medium":              "organic",
 *   "campaign":            null,
 *   "term":                null,
 *   "content":             null,
 *   "event_name":          "generate_lead",
 *   "lead_id":             null,           // ID en SIGC si ya fue cruzado
 *   "raw_payload_json":    { ... }          // Payload completo del evento GA4/GTM
 * }
 *
 * Quién llama a este servicio:
 *   - Controlador del endpoint POST /api/seo/utm-conversions.
 *   - No se usa en comandos ni en jobs de sincronización Google.
 */
class UtmConversionIngestService
{
    /**
     * Resuelve la empresa desde el contexto autenticado de integración
     * y persiste la conversión UTM.
     *
     * @param  array<string, mixed>  $payload
     */
    public function ingestFromIntegration(EmpresaIntegration $integration, array $payload): SeoUtmConversion
    {
        $empresa = $integration->relationLoaded('empresa')
            ? $integration->empresa
            : $integration->load('empresa')->empresa;

        return $this->ingest($empresa, $payload);
    }

    /**
     * Valida y persiste una conversión UTM individual.
     *
     * @param  array<string, mixed>  $payload  Payload normalizable del request.
     * @return SeoUtmConversion                Registro persistido.
     * @throws \InvalidArgumentException       Si falta conversion_datetime.
     */
    public function ingest(Empresa $empresa, array $payload): SeoUtmConversion
    {
        $normalized = $this->normalize($payload);

        return SeoUtmConversion::create(array_merge($normalized, [
            'empresa_id' => $empresa->id,
        ]));
    }

    /**
     * Procesa un lote de conversiones.
     * Los errores por registro individual no abortan el lote; se omiten y reportan.
     *
     * @param  array<int, array<string, mixed>>  $payloads
     * @return array{created: int, failed: int, errors: string[]}
     */
    public function ingestBatch(Empresa $empresa, array $payloads): array
    {
        $created = 0;
        $failed  = 0;
        $errors  = [];

        foreach ($payloads as $index => $payload) {
            try {
                $this->ingest($empresa, $payload);
                $created++;
            } catch (\Throwable $e) {
                $failed++;
                $errors[] = "row[{$index}]: " . $e->getMessage();
            }
        }

        return compact('created', 'failed', 'errors');
    }

    // ── Helpers privados ──────────────────────────────────────────────────────

    /**
     * Normaliza y valida el payload. Trunca strings para respetar longitudes de BD.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     * @throws \InvalidArgumentException
     */
    private function normalize(array $payload): array
    {
        $raw = isset($payload['conversion_datetime'])
            ? trim((string) $payload['conversion_datetime'])
            : '';

        if ($raw === '') {
            throw new \InvalidArgumentException('conversion_datetime es requerido.');
        }

        $dt = Carbon::parse($raw);

        return [
            'conversion_datetime' => $dt->toDateTimeString(),
            'page_url'            => $this->truncate($payload, 'page_url',   500),
            'form_name'           => $this->truncate($payload, 'form_name',  150),
            'source'              => $this->truncate($payload, 'source',     120),
            'medium'              => $this->truncate($payload, 'medium',     120),
            'campaign'            => $this->truncate($payload, 'campaign',   150),
            'term'                => $this->truncate($payload, 'term',       150),
            'content'             => $this->truncate($payload, 'content',    150),
            'event_name'          => $this->truncate($payload, 'event_name', 120),
            // lead_id es un entero nullable; 0 se trata como null.
            'lead_id'             => isset($payload['lead_id']) && (int) $payload['lead_id'] > 0
                                        ? (int) $payload['lead_id']
                                        : null,
            // raw_payload_json está casteado como 'array' en el modelo;
            // Eloquent serializa automáticamente al persistir.
            'raw_payload_json'    => isset($payload['raw_payload_json']) && is_array($payload['raw_payload_json'])
                                        ? $payload['raw_payload_json']
                                        : null,
        ];
    }

    /**
     * Extrae y trunca un campo string del payload.
     *
     * @param  array<string, mixed>  $payload
     */
    private function truncate(array $payload, string $key, int $maxLength): ?string
    {
        if (! isset($payload[$key]) || $payload[$key] === '' || $payload[$key] === null) {
            return null;
        }

        $value = trim((string) $payload[$key]);

        return $value !== '' ? substr($value, 0, $maxLength) : null;
    }
}
