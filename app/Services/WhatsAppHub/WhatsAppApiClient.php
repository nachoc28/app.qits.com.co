<?php

namespace App\Services\WhatsAppHub;

use App\Models\EmpresaWhatsAppSetting;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Cliente liviano para WhatsApp Cloud API.
 *
 * Mantiene aislado el detalle HTTP para reutilizarlo desde servicios/jobs.
 */
class WhatsAppApiClient
{
    public function sendText(
        EmpresaWhatsAppSetting $setting,
        string $destinationPhone,
        string $message
    ): Response {
        $payload = [
            'messaging_product' => 'whatsapp',
            'to'                => $destinationPhone,
            'type'              => 'text',
            'text'              => [
                'preview_url' => false,
                'body'        => $message,
            ],
        ];

        return $this->post($setting, $payload);
    }

    public function sendDocument(
        EmpresaWhatsAppSetting $setting,
        string $destinationPhone,
        string $publicFileUrl,
        string $fileName,
        ?string $caption = null
    ): Response {
        $document = [
            'link'     => $publicFileUrl,
            'filename' => $fileName,
        ];

        if ($caption !== null && $caption !== '') {
            $document['caption'] = $caption;
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to'                => $destinationPhone,
            'type'              => 'document',
            'document'          => $document,
        ];

        return $this->post($setting, $payload);
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    private function post(EmpresaWhatsAppSetting $setting, array $payload): Response
    {
        $baseUrl = rtrim((string) config('whatsapp_hub.cloud_api.base_url', 'https://graph.facebook.com'), '/');
        $version = trim((string) config('whatsapp_hub.cloud_api.version', 'v20.0'), '/');
        $timeout = (int) config('whatsapp_hub.cloud_api.timeout_seconds', 20);

        $url = $baseUrl . '/' . $version . '/' . $setting->whatsapp_phone_number_id . '/messages';

        return Http::timeout($timeout)
            ->withToken((string) $setting->whatsapp_access_token)
            ->acceptJson()
            ->post($url, $payload);
    }
}
