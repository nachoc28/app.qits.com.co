<?php

namespace App\Services\WhatsAppHub;

use App\Jobs\WhatsAppHub\DispatchWordpressFormNotificationJob;
use App\Models\Empresa;
use App\Models\EmpresaWhatsAppSetting;
use App\Models\FormNotificationPublicLink;
use App\Models\EmpresaIntegration;
use App\Models\IntegrationSecurityLog;
use App\Models\WhatsappFormNotification;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class WordpressFormNotificationIngestService
{
    /**
     * Stub inicial para desacoplar el endpoint de la lógica de negocio futura.
     *
     * @param array<string, mixed> $validated
     * @return array<string, mixed>
     */
    public function ingestFromIntegration(EmpresaIntegration $integration, array $validated): array
    {
        $empresaId = (int) $integration->empresa_id;
        $sourceSystem = (string) $validated['source_system'];
        $sourceRecordId = (string) $validated['source_record_id'];

        $existing = WhatsappFormNotification::query()
            ->where('empresa_id', $empresaId)
            ->where('source_system', $sourceSystem)
            ->where('source_record_id', $sourceRecordId)
            ->first();

        if ($existing instanceof WhatsappFormNotification) {
            return [
                'accepted' => true,
                'idempotent' => true,
                'notification_id' => $existing->id,
                'empresa_id' => $existing->empresa_id,
                'status' => $existing->status,
                'source_system' => $existing->source_system,
                'source_record_id' => $existing->source_record_id,
            ];
        }

        return DB::transaction(function () use ($integration, $empresaId, $sourceSystem, $sourceRecordId, $validated) {
            $submittedAt = (string) $validated['submitted_at'];
            $fields = (array) $validated['fields_json'];
            $rawPayload = isset($validated['raw_payload_json']) && is_array($validated['raw_payload_json'])
                ? (array) $validated['raw_payload_json']
                : $validated;

            $normalized = $this->buildNormalizedPayload($validated, $fields);

            $variables = [
                'nombre' => (string) ($normalized['nombre'] ?? ''),
                'servicio' => (string) ($normalized['servicio'] ?? ''),
                'telefono' => (string) ($normalized['telefono'] ?? ''),
            ];

            $messagePayload = [
                'submitted_at' => $submittedAt,
                'variables' => $variables,
                'template' => [
                    'name' => null,
                    'language' => null,
                    'status' => null,
                ],
                'button' => [
                    'type' => 'url',
                    'token_param_key' => 'token',
                ],
            ];

            try {
                $notification = WhatsappFormNotification::query()->create([
                    'empresa_id' => $empresaId,
                    'source_system' => $sourceSystem,
                    'source_record_id' => $sourceRecordId,
                    'status' => 'pending',
                    'raw_payload_json' => $rawPayload,
                    'normalized_payload_json' => $normalized,
                    'message_payload_json' => $messagePayload,
                ]);
            } catch (QueryException $e) {
                if (! $this->isDuplicateKeyException($e)) {
                    throw $e;
                }

                $duplicate = WhatsappFormNotification::query()
                    ->where('empresa_id', $empresaId)
                    ->where('source_system', $sourceSystem)
                    ->where('source_record_id', $sourceRecordId)
                    ->first();

                if (! $duplicate instanceof WhatsappFormNotification) {
                    throw $e;
                }

                return [
                    'accepted' => true,
                    'idempotent' => true,
                    'notification_id' => $duplicate->id,
                    'empresa_id' => $duplicate->empresa_id,
                    'status' => $duplicate->status,
                    'source_system' => $duplicate->source_system,
                    'source_record_id' => $duplicate->source_record_id,
                ];
            }

            if (! $this->isFormNotificationsServiceEnabled($empresaId)) {
                $notification->update([
                    'status' => 'skipped_security',
                    'failure_reason' => 'service_not_enabled',
                ]);

                return $this->buildAcceptedResponse($notification, false);
            }

            $setting = EmpresaWhatsAppSetting::query()
                ->where('empresa_id', $empresaId)
                ->where('is_active', true)
                ->first();

            $destinationPhone = $setting instanceof EmpresaWhatsAppSetting
                ? $this->sanitizePhone((string) $setting->destination_phone)
                : '';

            if ($destinationPhone === '') {
                $notification->update([
                    'status' => 'skipped_no_recipient',
                    'failure_reason' => 'No active destination_phone configured in empresa_whatsapp_settings.',
                ]);

                return $this->buildAcceptedResponse($notification, false);
            }

            if (! $setting instanceof EmpresaWhatsAppSetting
                || $setting->destination_opt_in !== true
                || $setting->destination_opt_in_at === null) {
                $notification->update([
                    'destination_phone' => $destinationPhone,
                    'status' => 'skipped_no_opt_in',
                    'failure_reason' => 'Destination phone requires opt-in=true and destination_opt_in_at not null.',
                ]);

                return $this->buildAcceptedResponse($notification, false);
            }

            $plainToken = $this->generateSecureToken();
            $tokenHash = hash('sha256', $plainToken);
            $tokenEncrypted = Crypt::encryptString($plainToken);

            FormNotificationPublicLink::query()->create([
                'whatsapp_form_notification_id' => $notification->id,
                'token_hash' => $tokenHash,
                'token_encrypted' => $tokenEncrypted,
                'is_active' => true,
            ]);

            $templateConfig = $this->resolveTemplateConfig();
            if (! $templateConfig['is_ready']) {
                $messagePayload['template']['status'] = 'missing_or_unapproved';

                $notification->update([
                    'destination_phone' => $destinationPhone,
                    'status' => 'awaiting_template',
                    'failure_reason' => $templateConfig['reason'],
                    'message_payload_json' => $messagePayload,
                ]);

                return $this->buildAcceptedResponse($notification, false);
            }

            $messagePayload['template'] = [
                'name' => $templateConfig['name'],
                'language' => $templateConfig['language'],
                'status' => 'approved',
            ];

            $notification->update([
                'destination_phone' => $destinationPhone,
                'status' => 'queued',
                'queued_at' => now(),
                'message_payload_json' => $messagePayload,
            ]);

            $limitResult = $this->checkAndConsumeDispatchRateLimit($integration);
            if (! $limitResult['allowed']) {
                $notification->update([
                    'status' => 'skipped_security',
                    'failure_reason' => 'rate_limit_exceeded',
                    'provider_response_json' => [
                        'limit_type' => $limitResult['limit_type'],
                        'limit_value' => $limitResult['limit_value'],
                        'current_count' => $limitResult['current_count'],
                    ],
                ]);

                $this->logRateLimitExceeded($integration, $limitResult);

                return $this->buildAcceptedResponse($notification, false);
            }

            $this->dispatchSendJob($notification);

            return $this->buildAcceptedResponse($notification, false);
        });
    }

    /**
     * @param array<string, mixed> $validated
     * @param array<string, mixed> $fields
     * @return array<string, mixed>
     */
    private function buildNormalizedPayload(array $validated, array $fields): array
    {
        return [
            'submitted_at' => (string) $validated['submitted_at'],
            'form_id' => $this->sanitizeText((string) ($validated['form_id'] ?? ''), 120),
            'form_name' => $this->sanitizeText((string) ($validated['form_name'] ?? ''), 150),
            'page_url' => $this->sanitizeText((string) ($validated['page_url'] ?? ''), 500),
            'nombre' => $this->sanitizeText($this->pickField($fields, ['nombre', 'name', 'full_name']), 180),
            'servicio' => $this->sanitizeText($this->pickField($fields, ['servicio', 'service']), 180),
            'telefono' => $this->sanitizePhone($this->pickField($fields, ['telefono', 'phone', 'mobile'])),
            'fields' => $this->sanitizeArrayValues($fields),
        ];
    }

    /**
     * @param array<string, mixed> $fields
     * @param string[] $candidateKeys
     */
    private function pickField(array $fields, array $candidateKeys): string
    {
        foreach ($candidateKeys as $key) {
            $value = Arr::get($fields, $key);
            if (is_string($value) && trim($value) !== '') {
                return $value;
            }
        }

        return '';
    }

    private function sanitizeText(string $value, int $max): string
    {
        $value = trim($value);
        $value = preg_replace('/\s+/', ' ', $value);
        $value = is_string($value) ? $value : '';

        return Str::limit($value, $max, '');
    }

    private function sanitizePhone(string $value): string
    {
        $clean = preg_replace('/[^0-9+]/', '', trim($value));
        return is_string($clean) ? Str::limit($clean, 50, '') : '';
    }

    /**
     * @param array<int|string, mixed> $data
     * @return array<int|string, mixed>
     */
    private function sanitizeArrayValues(array $data): array
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizeArrayValues($value);
                continue;
            }

            if (is_string($value)) {
                $sanitized[$key] = $this->sanitizeText($value, 500);
                continue;
            }

            $sanitized[$key] = $value;
        }

        return $sanitized;
    }

    /**
     * @return array{name:?string,language:?string,is_ready:bool,reason:?string}
     */
    private function resolveTemplateConfig(): array
    {
        $cfg = (array) config('whatsapp_hub.form_notifications_template', []);

        $name = isset($cfg['name']) && is_string($cfg['name']) ? trim($cfg['name']) : '';
        $language = isset($cfg['language']) && is_string($cfg['language']) ? trim($cfg['language']) : '';
        $status = isset($cfg['status']) && is_string($cfg['status']) ? trim($cfg['status']) : '';

        if ($name === '' || $language === '' || strtolower($status) !== 'approved') {
            return [
                'name' => null,
                'language' => null,
                'is_ready' => false,
                'reason' => 'WhatsApp template is missing or not approved. Configure whatsapp_hub.form_notifications_template{name,language,status=approved}.',
            ];
        }

        return [
            'name' => $name,
            'language' => $language,
            'is_ready' => true,
            'reason' => null,
        ];
    }

    private function generateSecureToken(): string
    {
        return Str::random(80);
    }

    private function dispatchSendJob(WhatsappFormNotification $notification): void
    {
        $connection = config('whatsapp_hub.dispatch.queue_connection');
        $queue = (string) config('whatsapp_hub.dispatch.queue_name', 'whatsapp-hub');

        $job = new DispatchWordpressFormNotificationJob((int) $notification->id);

        if (is_string($connection) && trim($connection) !== '') {
            $job->onConnection($connection);
        }

        if ($queue !== '') {
            $job->onQueue($queue);
        }

        dispatch($job);
    }

    private function isFormNotificationsServiceEnabled(int $empresaId): bool
    {
        /** @var Empresa|null $empresa */
        $empresa = Empresa::query()->find($empresaId);
        if (! $empresa instanceof Empresa) {
            return false;
        }

        $moduleCfg = (array) config('integration_security.modules.wordpress.form_notifications_ingest', []);
        $serviceId = isset($moduleCfg['required_service_id']) ? (int) $moduleCfg['required_service_id'] : 0;
        $serviceSlug = isset($moduleCfg['required_service_slug']) ? (string) $moduleCfg['required_service_slug'] : '';

        $byId = $serviceId > 0 ? $empresa->hasActiveService($serviceId) : false;
        $bySlug = $serviceSlug !== '' ? $empresa->hasActiveServiceBySlug($serviceSlug) : false;

        return $byId || $bySlug;
    }

    /**
     * @return array{allowed:bool,limit_type:?string,limit_value:?int,current_count:?int}
     */
    private function checkAndConsumeDispatchRateLimit(EmpresaIntegration $integration): array
    {
        $cfg = (array) config('integration_security.wordpress_form_notifications_rate_limit', []);

        $limits = [
            'max_per_minute' => [
                'value' => (int) ($cfg['max_per_minute'] ?? 10),
                'window' => 60,
            ],
            'max_per_hour' => [
                'value' => (int) ($cfg['max_per_hour'] ?? 100),
                'window' => 3600,
            ],
            'max_per_day' => [
                'value' => (int) ($cfg['max_per_day'] ?? 500),
                'window' => 86400,
            ],
        ];

        $keys = [];
        foreach ($limits as $limitType => $meta) {
            $limitValue = (int) $meta['value'];
            if ($limitValue <= 0) {
                continue;
            }

            $keys[$limitType] = $this->dispatchRateLimitKey($integration, $limitType);

            if (RateLimiter::tooManyAttempts($keys[$limitType], $limitValue)) {
                return [
                    'allowed' => false,
                    'limit_type' => $limitType,
                    'limit_value' => $limitValue,
                    'current_count' => RateLimiter::attempts($keys[$limitType]),
                ];
            }
        }

        foreach ($limits as $limitType => $meta) {
            if (! isset($keys[$limitType])) {
                continue;
            }

            RateLimiter::hit($keys[$limitType], (int) $meta['window']);
        }

        return [
            'allowed' => true,
            'limit_type' => null,
            'limit_value' => null,
            'current_count' => null,
        ];
    }

    private function dispatchRateLimitKey(EmpresaIntegration $integration, string $limitType): string
    {
        return 'wpfn:dispatch:rl:' . $limitType . ':empresa:'
            . (string) $integration->empresa_id . ':integration:' . (string) $integration->id;
    }

    /**
     * @param array{allowed:bool,limit_type:?string,limit_value:?int,current_count:?int} $limitResult
     */
    private function logRateLimitExceeded(EmpresaIntegration $integration, array $limitResult): void
    {
        try {
            IntegrationSecurityLog::create([
                'integration_id' => $integration->id,
                'empresa_id' => $integration->empresa_id,
                'event_type' => 'rate_limit_exceeded',
                'endpoint' => '/api/wordpress/form-notifications',
                'http_method' => 'POST',
                'status' => 'denied',
                'reason_code' => 'RATE_LIMIT_EXCEEDED',
                'meta_json' => [
                    'limit_type' => $limitResult['limit_type'],
                    'limit_value' => $limitResult['limit_value'],
                    'current_count' => $limitResult['current_count'],
                ],
            ]);
        } catch (\Throwable $e) {
            // El antiabuso no debe romper la ingesta si falla el log de seguridad.
        }
    }

    private function isDuplicateKeyException(QueryException $e): bool
    {
        return (int) $e->getCode() === 23000
            && strpos((string) $e->getMessage(), '1062') !== false;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAcceptedResponse(WhatsappFormNotification $notification, bool $idempotent): array
    {
        return [
            'accepted' => true,
            'idempotent' => $idempotent,
            'notification_id' => (int) $notification->id,
            'empresa_id' => (int) $notification->empresa_id,
            'status' => (string) $notification->status,
            'source_system' => (string) $notification->source_system,
            'source_record_id' => (string) $notification->source_record_id,
        ];
    }
}
