<?php

namespace App\Services\WhatsAppHub;

/**
 * DTO inmutable que representa los datos de un lead ya normalizados.
 *
 * Se crea en LeadNormalizerService y se consume en LeadPersistenceService.
 * Desacopla el formato de entrada (array del request) del formato de
 * persistencia, facilitando pruebas y cambios futuros.
 */
final class NormalizedLeadData
{
    public string   $fullName;
    public string   $phone;
    public ?string  $email;
    public ?string  $company;
    public ?string  $city;
    public ?string  $message;
    public ?string  $originFormName;
    public ?string  $originUrl;
    public ?array   $payloadJson;

    // Trazabilidad UTM / origen
    public ?string  $domain;
    public ?string  $pageUrl;
    public ?string  $utmSource;
    public ?string  $utmCampaign;
    public ?string  $campaign;
    public ?string  $medium;

    public function __construct(
        string  $fullName,
        string  $phone,
        ?string $email,
        ?string $company,
        ?string $city,
        ?string $message,
        ?string $originFormName,
        ?string $originUrl,
        ?array  $payloadJson,
        ?string $domain,
        ?string $pageUrl,
        ?string $utmSource,
        ?string $utmCampaign,
        ?string $campaign,
        ?string $medium
    ) {
        $this->fullName       = $fullName;
        $this->phone          = $phone;
        $this->email          = $email;
        $this->company        = $company;
        $this->city           = $city;
        $this->message        = $message;
        $this->originFormName = $originFormName;
        $this->originUrl      = $originUrl;
        $this->payloadJson    = $payloadJson;
        $this->domain         = $domain;
        $this->pageUrl        = $pageUrl;
        $this->utmSource      = $utmSource;
        $this->utmCampaign    = $utmCampaign;
        $this->campaign       = $campaign;
        $this->medium         = $medium;
    }

    /** Indica si hay algún dato de trazabilidad UTM/origen disponible. */
    public function hasTrackingData(): bool
    {
        return $this->utmSource !== null
            || $this->utmCampaign !== null
            || $this->pageUrl    !== null
            || $this->domain     !== null;
    }
}
