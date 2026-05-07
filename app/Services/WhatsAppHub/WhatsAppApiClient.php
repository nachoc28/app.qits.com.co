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
     * Envia un mensaje de plantilla por WhatsApp Cloud API.
     *
     * @param string[] $bodyParameters
     * @return array<string,mixed>
     */
    public function sendTemplate(
        EmpresaWhatsAppSetting $setting,
        string $destinationPhone,
        string $templateName,
        string $templateLanguage,
        array $bodyParameters,
        string $buttonUrlParameter
    ): array {
        $destinationPhone = trim($destinationPhone);
        $templateName = trim($templateName);
        $templateLanguage = trim($templateLanguage);
        $phoneNumberId = trim((string) config('whatsapp_hub.template_sender.phone_number_id', ''));
        $accessToken = trim((string) config('whatsapp_hub.template_sender.access_token', ''));

        if ($destinationPhone === '') {
            return $this->normalizedError('Destination phone is required.');
        }

        if ($templateName === '' || $templateLanguage === '') {
            return $this->normalizedError('Template name and language are required.');
        }

        if ($phoneNumberId === '' || $accessToken === '') {
            return $this->normalizedError('Global WhatsApp template credentials are incomplete in WHATSAPP_TOKEN/WHATSAPP_PHONE_ID.');
        }

        $tokenParam = $this->extractTokenParam($buttonUrlParameter);
        if ($tokenParam === '') {
            return $this->normalizedError('Template button token parameter is required.');
        }

        $bodyComponents = [];
        foreach ($bodyParameters as $value) {
            $bodyComponents[] = [
                'type' => 'text',
                'text' => (string) $value,
            ];
        }

        $components = [];
        if ($bodyComponents !== []) {
            $components[] = [
                'type' => 'body',
                'parameters' => $bodyComponents,
            ];
        }

        $components[] = [
            'type' => 'button',
            'sub_type' => 'url',
            'index' => '0',
            'parameters' => [
                [
                    'type' => 'text',
                    'text' => $tokenParam,
                ],
            ],
        ];

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $destinationPhone,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => [
                    'code' => $templateLanguage,
                ],
                'components' => $components,
            ],
        ];

        try {
            $response = $this->postTemplate($phoneNumberId, $accessToken, $payload);
        } catch (\Throwable $e) {
            return $this->normalizedError('HTTP request failed for WhatsApp template dispatch.', [
                'exception' => $e->getMessage(),
            ]);
        }

        $raw = $response->json();
        if (! is_array($raw)) {
            $raw = ['raw' => $response->body()];
        }

        $messageId = null;
        if (isset($raw['messages'][0]['id']) && is_string($raw['messages'][0]['id'])) {
            $messageId = $raw['messages'][0]['id'];
        }

        if ($response->successful()) {
            return [
                'success' => true,
                'whatsapp_message_id' => $messageId,
                'raw_response' => $raw,
                'error' => null,
            ];
        }

        return [
            'success' => false,
            'whatsapp_message_id' => $messageId,
            'raw_response' => $raw,
            'error' => 'WHATSAPP_TEMPLATE_REJECTED',
        ];
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

    /**
     * @param  array<string,mixed>  $payload
     */
    private function postTemplate(string $phoneNumberId, string $accessToken, array $payload): Response
    {
        $baseUrl = rtrim((string) config('whatsapp_hub.cloud_api.base_url', 'https://graph.facebook.com'), '/');
        $version = trim((string) config('whatsapp_hub.cloud_api.version', 'v20.0'), '/');
        $timeout = (int) config('whatsapp_hub.cloud_api.timeout_seconds', 20);

        $url = $baseUrl . '/' . $version . '/' . $phoneNumberId . '/messages';

        return Http::timeout($timeout)
            ->withToken($accessToken)
            ->acceptJson()
            ->post($url, $payload);
    }

    /**
     * @param array<string,mixed>|null $rawResponse
     * @return array<string,mixed>
     */
    private function normalizedError(string $message, ?array $rawResponse = null): array
    {
        return [
            'success' => false,
            'whatsapp_message_id' => null,
            'raw_response' => $rawResponse,
            'error' => $message,
        ];
    }

    private function extractTokenParam(string $buttonUrlParameter): string
    {
        $buttonUrlParameter = trim($buttonUrlParameter);
        if ($buttonUrlParameter === '') {
            return '';
        }

        if (filter_var($buttonUrlParameter, FILTER_VALIDATE_URL) === false) {
            return $buttonUrlParameter;
        }

        $path = (string) parse_url($buttonUrlParameter, PHP_URL_PATH);
        $segments = array_values(array_filter(explode('/', trim($path, '/')), static function ($v) {
            return $v !== '';
        }));

        if ($segments !== []) {
            return (string) end($segments);
        }

        return '';
    }
}
