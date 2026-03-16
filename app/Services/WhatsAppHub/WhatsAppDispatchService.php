<?php

namespace App\Services\WhatsAppHub;

use App\Jobs\WhatsAppHub\SendLeadToWhatsAppJob;
use App\Models\EmpresaWhatsAppSetting;
use App\Models\FormForwardingRule;
use App\Models\LeadDocument;
use App\Models\OutboundMessage;
use App\Models\WaLead;
use App\Services\WhatsAppHub\WhatsAppDispatchResult;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Str;
use Throwable;

/**
 * Orquesta el despacho saliente por WhatsApp para el Modulo 1.
 *
 * Responsabilidades:
 *  - Construir mensaje resumen para el lead.
 *  - Enviar texto por WhatsApp Cloud API.
 *  - Enviar PDF opcional si existe y esta habilitado.
 *  - Registrar cada intento en outbound_messages.
 */
class WhatsAppDispatchService
{
    private WhatsAppApiClient $apiClient;

    public function __construct(WhatsAppApiClient $apiClient)
    {
        $this->apiClient = $apiClient;
    }

    public function dispatchForLead(WaLead $lead, FormForwardingRule $rule): WhatsAppDispatchResult
    {
        $async = (bool) config('whatsapp_hub.dispatch.async', true);

        if ($async) {
            $job = SendLeadToWhatsAppJob::dispatch($lead->id, $rule->id);

            $connection = config('whatsapp_hub.dispatch.queue_connection');
            if ($connection) {
                $job->onConnection((string) $connection);
            }

            $job->onQueue((string) config('whatsapp_hub.dispatch.queue_name', 'whatsapp-hub'));
            return new WhatsAppDispatchResult(true, false, false, false, false, false, false, false, null);
        }

        return $this->dispatchNowByIds($lead->id, $rule->id);
    }

    public function dispatchNowByIds(int $leadId, ?int $ruleId = null): WhatsAppDispatchResult
    {
        $lead = WaLead::with(['empresa.whatsappSetting', 'documents'])
            ->findOrFail($leadId);

        $rule = null;
        if ($ruleId !== null) {
            $rule = FormForwardingRule::find($ruleId);
        }

        return $this->dispatchNow($lead, $rule);
    }

    public function dispatchNow(WaLead $lead, ?FormForwardingRule $rule = null): WhatsAppDispatchResult
    {
        $setting = $lead->empresa && $lead->empresa->whatsappSetting
            ? $lead->empresa->whatsappSetting
            : null;

        if (! $setting || ! $setting->is_active) {
            $lead->events()->create([
                'event_type'   => 'forwarding_skipped',
                'payload_json' => [
                    'reason' => 'missing_or_inactive_whatsapp_setting',
                ],
            ]);
            return new WhatsAppDispatchResult();
        }

        $destinationPhone = trim((string) $setting->destination_phone);
        if ($destinationPhone === '') {
            $lead->events()->create([
                'event_type'   => 'forwarding_skipped',
                'payload_json' => [
                    'reason' => 'empty_destination_phone',
                ],
            ]);
            return new WhatsAppDispatchResult();
        }

        if (! $setting->send_text_enabled) {
            $lead->events()->create([
                'event_type'   => 'forwarding_skipped',
                'payload_json' => [
                    'reason' => 'text_sending_disabled',
                ],
            ]);
            return new WhatsAppDispatchResult();
        }

        $outboundLogged = false;
        $summaryText = $this->buildSummaryMessage($lead, $rule);
        $textSent = $this->sendTextAndLog($lead, $setting, $destinationPhone, $summaryText);
        $textFailed = ! $textSent;
        $outboundLogged = true;

        $documentAttempted = false;
        $documentSent = false;
        $documentFailed = false;

        if (! $setting->send_pdf_enabled) {
            return new WhatsAppDispatchResult(
                false,
                $outboundLogged,
                true,
                $textSent,
                $textFailed,
                false,
                false,
                false,
                null
            );
        }

        $pdfDocument = $this->resolvePdfDocument($lead);
        if (! $pdfDocument) {
            return new WhatsAppDispatchResult(
                false,
                $outboundLogged,
                true,
                $textSent,
                $textFailed,
                false,
                false,
                false,
                null
            );
        }

        $publicPdfUrl = $this->buildPublicFileUrl($pdfDocument);
        if ($publicPdfUrl === null) {
            return new WhatsAppDispatchResult(
                false,
                $outboundLogged,
                true,
                $textSent,
                $textFailed,
                false,
                false,
                false,
                'pdf_public_url_not_available'
            );
        }

        $documentAttempted = true;
        $documentSent = $this->sendDocumentAndLog($lead, $setting, $destinationPhone, $pdfDocument, $publicPdfUrl);
        $documentFailed = ! $documentSent;
        $outboundLogged = true;

        return new WhatsAppDispatchResult(
            false,
            $outboundLogged,
            true,
            $textSent,
            $textFailed,
            $documentAttempted,
            $documentSent,
            $documentFailed,
            null
        );
    }

    public function buildSummaryMessage(WaLead $lead, ?FormForwardingRule $rule = null): string
    {
        $formName = $lead->origin_form_name ?: ($rule ? $rule->form_name : null) ?: 'N/A';
        $waLink = $this->buildWaMeLink($lead->phone);

        $lines = [
            'Nuevo lead recibido desde sitio web',
            'Nombre: ' . ($lead->full_name ?: 'N/A'),
            'Telefono: ' . ($lead->phone ?: 'N/A'),
            'Formulario: ' . $formName,
        ];

        if ($waLink !== null) {
            $lines[] = 'Iniciar chat: ' . $waLink;
        }

        return implode("\n", $lines);
    }

    private function sendTextAndLog(
        WaLead $lead,
        EmpresaWhatsAppSetting $setting,
        string $destinationPhone,
        string $summaryText
    ): bool {
        $log = OutboundMessage::create([
            'empresa_id'        => $lead->empresa_id,
            'lead_id'           => $lead->id,
            'channel'           => 'whatsapp',
            'destination_phone' => $destinationPhone,
            'message_type'      => 'text',
            'message_body'      => $summaryText,
            'status'            => 'queued',
        ]);

        try {
            $response = $this->apiClient->sendText($setting, $destinationPhone, $summaryText);
            return $this->finalizeOutboundLog($log, $response);
        } catch (Throwable $e) {
            $this->markOutboundFailed($log, $e->getMessage());
            return false;
        }
    }

    private function sendDocumentAndLog(
        WaLead $lead,
        EmpresaWhatsAppSetting $setting,
        string $destinationPhone,
        LeadDocument $pdfDocument,
        string $publicPdfUrl
    ): bool {
        $log = OutboundMessage::create([
            'empresa_id'        => $lead->empresa_id,
            'lead_id'           => $lead->id,
            'channel'           => 'whatsapp',
            'destination_phone' => $destinationPhone,
            'message_type'      => 'document',
            'attachment_path'   => $pdfDocument->file_path,
            'status'            => 'queued',
        ]);

        try {
            $response = $this->apiClient->sendDocument(
                $setting,
                $destinationPhone,
                $publicPdfUrl,
                $pdfDocument->file_name,
                'Documento del lead #' . $lead->id
            );

            return $this->finalizeOutboundLog($log, $response);
        } catch (Throwable $e) {
            $this->markOutboundFailed($log, $e->getMessage());
            return false;
        }
    }

    private function finalizeOutboundLog(OutboundMessage $log, Response $response): bool
    {
        $body = $response->json();
        if (! is_array($body)) {
            $body = ['raw' => $response->body()];
        }

        $messageId = null;
        if (isset($body['messages'][0]['id'])) {
            $messageId = (string) $body['messages'][0]['id'];
        }

        $status = $response->successful() ? 'sent' : 'failed';
        $error = $response->successful() ? null : $response->body();

        $log->update([
            'provider_message_id' => $messageId,
            'provider_response'   => $body,
            'status'              => $status,
            'error_message'       => $error,
        ]);

        return $response->successful();
    }

    private function markOutboundFailed(OutboundMessage $log, string $message): void
    {
        $log->update([
            'status'        => 'failed',
            'error_message' => $message,
        ]);
    }

    private function resolvePdfDocument(WaLead $lead): ?LeadDocument
    {
        if ($lead->relationLoaded('documents')) {
            return $lead->documents
                ->where('document_type', LeadDocument::TYPE_PDF)
                ->sortByDesc('id')
                ->first();
        }

        return $lead->documents()
            ->where('document_type', LeadDocument::TYPE_PDF)
            ->orderByDesc('id')
            ->first();
    }

    private function buildPublicFileUrl(LeadDocument $document): ?string
    {
        $disk = (string) config('whatsapp_hub.pdf_storage.disk', 'local');
        $relative = trim((string) $document->file_path, '/');

        if ($relative === '') {
            return null;
        }

        $url = (string) config('filesystems.disks.' . $disk . '.url', '');

        if ($url === '' && in_array($disk, ['local', 'public'], true)) {
            $url = '/storage';
        }

        if ($url === '') {
            return null;
        }

        $url = rtrim($url, '/') . '/' . ltrim($relative, '/');

        if (Str::startsWith($url, ['http://', 'https://'])) {
            return $url;
        }

        $baseAppUrl = rtrim((string) config('app.url', ''), '/');
        if ($baseAppUrl === '') {
            return null;
        }

        return $baseAppUrl . '/' . ltrim($url, '/');
    }

    private function buildWaMeLink(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);
        if (! is_string($digits) || strlen($digits) < 8) {
            return null;
        }

        return 'https://wa.me/' . $digits;
    }
}
