<?php

namespace App\Services\WhatsAppHub;

final class FormIngressResult
{
    public int $leadId;
    public int $empresaId;
    public string $siteKey;
    public bool $pdfGenerated;
    public bool $whatsAppQueued;
    public bool $whatsAppSent;
    public bool $whatsAppFailed;
    public bool $outboundLogged;

    public function __construct(
        int $leadId,
        int $empresaId,
        string $siteKey,
        bool $pdfGenerated,
        bool $whatsAppQueued,
        bool $whatsAppSent,
        bool $whatsAppFailed,
        bool $outboundLogged
    ) {
        $this->leadId = $leadId;
        $this->empresaId = $empresaId;
        $this->siteKey = $siteKey;
        $this->pdfGenerated = $pdfGenerated;
        $this->whatsAppQueued = $whatsAppQueued;
        $this->whatsAppSent = $whatsAppSent;
        $this->whatsAppFailed = $whatsAppFailed;
        $this->outboundLogged = $outboundLogged;
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'lead_id'         => $this->leadId,
            'empresa_id'      => $this->empresaId,
            'site_key'        => $this->siteKey,
            'pdf_generated'   => $this->pdfGenerated,
            'whatsapp_queued' => $this->whatsAppQueued,
            'whatsapp_sent'   => $this->whatsAppSent,
            'whatsapp_failed' => $this->whatsAppFailed,
            'outbound_logged' => $this->outboundLogged,
        ];
    }
}
