<?php

namespace App\Jobs\WhatsAppHub;

use App\Models\EmpresaWhatsAppSetting;
use App\Models\FormNotificationPublicLink;
use App\Models\WhatsappFormNotification;
use App\Services\WhatsAppHub\WhatsAppApiClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Crypt;
use Throwable;

class DispatchWordpressFormNotificationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $notificationId;

    public int $tries = 3;

    public function __construct(int $notificationId)
    {
        $this->notificationId = $notificationId;
    }

    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(WhatsAppApiClient $apiClient): void
    {
        $notification = WhatsappFormNotification::query()
            ->with('publicLink')
            ->find($this->notificationId);

        if (! $notification instanceof WhatsappFormNotification) {
            return;
        }

        if ((string) $notification->status !== 'queued') {
            return;
        }

        if (! $notification->queued_at || $notification->queued_at->copy()->addMinutes(30)->isPast()) {
            $notification->update([
                'status' => 'expired',
                'expired_at' => now(),
                'failure_reason' => 'Dispatch window expired (queued_at + 30 minutes).',
            ]);

            return;
        }

        $link = $notification->publicLink;
        if (! $link instanceof FormNotificationPublicLink) {
            $notification->update([
                'status' => 'failed',
                'failed_at' => now(),
                'failure_reason' => 'Public link not found for queued notification.',
            ]);

            return;
        }

        if (! $this->isLinkUsable($link)) {
            $notification->update([
                'status' => 'failed',
                'failed_at' => now(),
                'failure_reason' => 'Public link is inactive, revoked, or expired.',
            ]);

            return;
        }

        if (! is_string($link->token_encrypted) || trim($link->token_encrypted) === '') {
            $notification->update([
                'status' => 'failed',
                'failed_at' => now(),
                'failure_reason' => 'Encrypted token is missing in public link.',
            ]);

            return;
        }

        /** @var EmpresaWhatsAppSetting|null $setting */
        $setting = EmpresaWhatsAppSetting::query()
            ->where('empresa_id', $notification->empresa_id)
            ->where('is_active', true)
            ->first();

        if (! $setting instanceof EmpresaWhatsAppSetting) {
            $notification->update([
                'status' => 'failed',
                'failed_at' => now(),
                'failure_reason' => 'Active WhatsApp setting not found for empresa.',
            ]);

            return;
        }

        $destinationPhone = trim((string) $setting->destination_phone);
        if ($destinationPhone === '') {
            $destinationPhone = trim((string) $notification->destination_phone);
        }

        if ($destinationPhone === '') {
            $notification->update([
                'status' => 'failed',
                'failed_at' => now(),
                'failure_reason' => 'Destination phone is empty on queued notification.',
            ]);

            return;
        }

        if ($setting->destination_opt_in !== true || $setting->destination_opt_in_at === null) {
            $notification->update([
                'status' => 'failed',
                'failed_at' => now(),
                'failure_reason' => 'Destination phone requires opt-in=true and destination_opt_in_at not null.',
            ]);

            return;
        }

        $templateConfig = $this->resolveApprovedTemplateConfig();
        if (! $templateConfig['is_ready']) {
            $notification->update([
                'status' => 'awaiting_template',
                'failure_reason' => $templateConfig['reason'],
                'provider_response_json' => [
                    'error' => 'TEMPLATE_CONFIG_MISSING_OR_UNAPPROVED',
                ],
            ]);

            return;
        }

        $payload = is_array($notification->message_payload_json)
            ? $notification->message_payload_json
            : [];

        $variables = Arr::get($payload, 'variables', []);
        $templateName = (string) $templateConfig['name'];
        $templateLanguage = (string) $templateConfig['language'];

        try {
            $token = Crypt::decryptString($link->token_encrypted);
        } catch (Throwable $e) {
            $notification->update([
                'status' => 'failed',
                'failed_at' => now(),
                'failure_reason' => 'Unable to decrypt token_encrypted for public link.',
                'provider_response_json' => ['error' => $e->getMessage()],
            ]);

            return;
        }

        $notification->update([
            'attempts' => (int) $notification->attempts + 1,
            'last_attempt_at' => now(),
        ]);

        $result = $apiClient->sendTemplate(
            $setting,
            $destinationPhone,
            $templateName,
            $templateLanguage,
            [
                (string) Arr::get($variables, 'nombre', ''),
                (string) Arr::get($variables, 'servicio', ''),
                (string) Arr::get($variables, 'telefono', ''),
            ],
            $token
        );

        $success = (bool) Arr::get($result, 'success', false);
        $rawResponse = Arr::get($result, 'raw_response');
        $messageId = Arr::get($result, 'whatsapp_message_id');
        $error = Arr::get($result, 'error');

        if ($success) {
            $notification->update([
                'status' => 'sent',
                'sent_at' => now(),
                'whatsapp_message_id' => is_string($messageId) ? $messageId : null,
                'provider_response_json' => is_array($rawResponse) ? $rawResponse : ['raw' => $rawResponse],
                'failure_reason' => null,
            ]);

            return;
        }

        $notification->update([
            'status' => 'failed',
            'failed_at' => now(),
            'failure_reason' => is_string($error) && $error !== ''
                ? $error
                : 'Provider rejected template dispatch.',
            'provider_response_json' => is_array($rawResponse) ? $rawResponse : ['raw' => $rawResponse],
        ]);
    }

    private function isLinkUsable(FormNotificationPublicLink $link): bool
    {
        if (! $link->is_active) {
            return false;
        }

        if ($link->revoked_at !== null) {
            return false;
        }

        if ($link->expires_at !== null && $link->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * @return array{name:?string,language:?string,is_ready:bool,reason:?string}
     */
    private function resolveApprovedTemplateConfig(): array
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
                'reason' => 'WhatsApp template is missing or not approved in whatsapp_hub.form_notifications_template.',
            ];
        }

        return [
            'name' => $name,
            'language' => $language,
            'is_ready' => true,
            'reason' => null,
        ];
    }
}
