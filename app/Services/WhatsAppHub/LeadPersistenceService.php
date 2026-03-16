<?php

namespace App\Services\WhatsAppHub;

use App\Models\Empresa;
use App\Models\FormForwardingRule;
use App\Models\LeadSource;
use App\Models\WaLead;
use Illuminate\Support\Facades\DB;

/**
 * Persiste el lead normalizado en la base de datos dentro de una transacción.
 *
 * Responsabilidades:
 *  - Crear o reutilizar LeadSource por combinación UTM única por empresa.
 *  - Detectar duplicados recientes (mismo empresa_id + teléfono en ventana de 5 min).
 *  - Crear el registro WaLead.
 *  - Registrar eventos 'received' y 'normalized'.
 *  - Si se detecta duplicado, registrar evento 'duplicate_detected' sin bloquear.
 *
 * Estrategia de duplicados (Stage 1):
 *  - Ventana de 5 minutos por empresa_id + teléfono normalizado.
 *  - No bloquea: el lead se crea de todas formas con status 'received'.
 *  - Se registra un evento 'duplicate_detected' con el id del lead anterior.
 *  - No se lanza excepción; la decisión de bloquear puede añadirse en Stage 2.
 *
 * Índices recomendados para dashboard (ya definidos en la migración):
 *  - wa_leads: empresa_id, status, phone, created_at
 *  - lead_sources: empresa_id, type, domain, utm_source, utm_campaign
 */
class LeadPersistenceService
{
    /** Ventana de deduplicación en minutos. */
    private const DUPLICATE_WINDOW_MINUTES = 5;

    /**
     * @throws \Throwable  (propaga desde DB::transaction)
     */
    public function persist(
        NormalizedLeadData $data,
        Empresa $empresa,
        FormForwardingRule $rule
    ): WaLead {
        return DB::transaction(function () use ($data, $empresa, $rule) {
            $source = $this->resolveSource($empresa, $data);

            $lead = WaLead::create([
                'empresa_id'       => $empresa->id,
                'source_id'        => $source ? $source->id : null,
                'full_name'        => $data->fullName,
                'phone'            => $data->phone,
                'email'            => $data->email,
                'company'          => $data->company,
                'city'             => $data->city,
                'message'          => $data->message,
                'origin_form_name' => $data->originFormName,
                'origin_url'       => $data->originUrl,
                'payload_json'     => $data->payloadJson,
                'status'           => 'received',
            ]);

            // Evento: ingreso recibido
            $lead->events()->create([
                'event_type'   => 'received',
                'payload_json' => [
                    'site_key'  => $rule->site_key,
                    'form_name' => $data->originFormName,
                    'domain'    => $data->domain,
                ],
            ]);

            // Evento: datos normalizados
            $lead->events()->create([
                'event_type'   => 'normalized',
                'payload_json' => [
                    'full_name' => $data->fullName,
                    'phone'     => $data->phone,
                    'email'     => $data->email,
                ],
            ]);

            // Detección de duplicados (no bloqueante)
            $this->checkDuplicate($lead, $empresa);

            return $lead;
        });
    }

    // ── Helpers privados ─────────────────────────────────────────────────────

    /**
     * Busca o crea un LeadSource agrupando por empresa + UTM + dominio.
     * Reutiliza el registro si ya existe idéntica combinación.
     */
    private function resolveSource(Empresa $empresa, NormalizedLeadData $data): ?LeadSource
    {
        if (! $data->hasTrackingData()) {
            return null;
        }

        return LeadSource::firstOrCreate(
            [
                'empresa_id'   => $empresa->id,
                'type'         => 'web_form',
                'utm_source'   => $data->utmSource,
                'utm_campaign' => $data->utmCampaign,
                'page_url'     => $data->pageUrl,
                'domain'       => $data->domain,
            ],
            [
                'campaign' => $data->campaign,
                'medium'   => $data->medium,
            ]
        );
    }

    /**
     * Detecta si existe un lead reciente con el mismo teléfono para la empresa.
     * Si existe, registra un evento 'duplicate_detected' en el nuevo lead.
     * NO bloquea ni lanza excepción.
     */
    private function checkDuplicate(WaLead $newLead, Empresa $empresa): void
    {
        $window = now()->subMinutes(self::DUPLICATE_WINDOW_MINUTES);

        $previous = WaLead::where('empresa_id', $empresa->id)
            ->where('phone', $newLead->phone)
            ->where('id', '!=', $newLead->id)
            ->where('created_at', '>=', $window)
            ->orderByDesc('created_at')
            ->first();

        if ($previous) {
            $newLead->events()->create([
                'event_type'   => 'duplicate_detected',
                'payload_json' => [
                    'previous_lead_id' => $previous->id,
                    'previous_created' => $previous->created_at->toIso8601String(),
                    'window_minutes'   => self::DUPLICATE_WINDOW_MINUTES,
                ],
            ]);
        }
    }
}
