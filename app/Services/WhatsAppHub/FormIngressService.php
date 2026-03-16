<?php

namespace App\Services\WhatsAppHub;

use App\Exceptions\WhatsAppHub\DomainNotAllowedException;
use App\Exceptions\WhatsAppHub\InvalidSiteKeyException;
use App\Models\Empresa;
use App\Models\FormForwardingRule;
use App\Models\LeadDocument;
use App\Models\WaLead;
use App\Services\WhatsAppHub\FormIngressResult;
use App\Services\WhatsAppHub\WhatsAppDispatchResult;
use Illuminate\Support\Str;

/**
 * Orquestador del flujo de ingesta del Módulo 1.
 *
 * Delega la normalización a LeadNormalizerService
 * y la persistencia a LeadPersistenceService,
 * actuando como fachada simple para el controlador.
 */
class FormIngressService
{
    private LeadNormalizerService  $normalizer;
    private LeadPersistenceService $persistence;
    private PdfLeadService         $pdfLeadService;
    private WhatsAppDispatchService $whatsAppDispatchService;

    public function __construct(
        LeadNormalizerService  $normalizer,
        LeadPersistenceService $persistence,
        PdfLeadService         $pdfLeadService,
        WhatsAppDispatchService $whatsAppDispatchService
    ) {
        $this->normalizer  = $normalizer;
        $this->persistence = $persistence;
        $this->pdfLeadService = $pdfLeadService;
        $this->whatsAppDispatchService = $whatsAppDispatchService;
    }

    /**
     * Compatibilidad legacy: procesa por site_key resolviendo la empresa de la regla.
     *
     * Preferir processForAuthorizedEmpresa() en nuevos endpoints,
     * porque asume que autenticación/autorización ya fue resuelta por middleware.
     *
     * @param  array<string,mixed>  $payload
     * @param  array<string,string|null>  $requestMeta
     * @throws InvalidSiteKeyException
     */
    public function process(string $siteKey, array $payload, array $requestMeta = []): FormIngressResult
    {
        $rule = FormForwardingRule::query()
            ->where('site_key', $siteKey)
            ->where('is_active', true)
            ->first();

        if (! $rule instanceof FormForwardingRule) {
            throw new InvalidSiteKeyException($siteKey);
        }

        $empresa = Empresa::query()
            ->where('id', $rule->empresa_id)
            ->where('active', true)
            ->first();

        if (! $empresa instanceof Empresa) {
            throw new InvalidSiteKeyException($siteKey);
        }

        return $this->processForAuthorizedEmpresa($empresa, $siteKey, $payload, $requestMeta);
    }

    /**
    * Orquesta el flujo completo de ingreso para Module 1 usando una Empresa
    * previamente autenticada/autorizada por el middleware de integraciones.
     *
     * @param  array<string,mixed>  $payload
     * @param  array<string,string|null>  $requestMeta
     * @throws InvalidSiteKeyException
     * @throws DomainNotAllowedException
     * @throws \Throwable
     */
    public function processForAuthorizedEmpresa(
        Empresa $empresa,
        string $siteKey,
        array $payload,
        array $requestMeta = []
    ): FormIngressResult
    {
        $rule = FormForwardingRule::query()
            ->where('site_key', $siteKey)
            ->where('empresa_id', $empresa->id)
            ->where('is_active', true)
            ->first();

        if (! $rule instanceof FormForwardingRule) {
            throw new InvalidSiteKeyException($siteKey);
        }

        $this->validateOriginDomain($rule->allowed_domain, $payload, $requestMeta);

        $lead = $this->ingest($empresa, $rule, $payload);

        $pdfGenerated = false;
        try {
            $doc = $this->pdfLeadService->generateAndPersistForLead($lead, $rule);
            $pdfGenerated = $doc instanceof LeadDocument;
        } catch (\Throwable $e) {
            $lead->events()->create([
                'event_type'   => 'pdf_generation_failed',
                'payload_json' => ['error' => $e->getMessage()],
            ]);
        }

        $dispatchResult = new WhatsAppDispatchResult();
        try {
            $dispatchResult = $this->whatsAppDispatchService->dispatchForLead($lead, $rule);
        } catch (\Throwable $e) {
            $dispatchResult = new WhatsAppDispatchResult(false, false, false, false, true, false, false, false, $e->getMessage());

            $lead->events()->create([
                'event_type'   => 'dispatch_enqueue_failed',
                'payload_json' => ['error' => $e->getMessage()],
            ]);
        }

        if ($dispatchResult->queued) {
            $lead->events()->create([
                'event_type'   => 'whatsapp_queued',
                'payload_json' => ['site_key' => $siteKey],
            ]);
        }

        if ($dispatchResult->hasAnySent()) {
            $lead->events()->create([
                'event_type'   => 'whatsapp_sent',
                'payload_json' => [
                    'text_sent'     => $dispatchResult->textSent,
                    'document_sent' => $dispatchResult->documentSent,
                ],
            ]);
        }

        if ($dispatchResult->hasFailures()) {
            $lead->events()->create([
                'event_type'   => 'whatsapp_failed',
                'payload_json' => [
                    'text_failed'     => $dispatchResult->textFailed,
                    'document_failed' => $dispatchResult->documentFailed,
                    'error'           => $dispatchResult->error,
                ],
            ]);
        }

        return new FormIngressResult(
            $lead->id,
            $empresa->id,
            $siteKey,
            $pdfGenerated,
            $dispatchResult->queued,
            $dispatchResult->hasAnySent(),
            $dispatchResult->hasFailures(),
            $dispatchResult->outboundLogged
        );
    }

    /**
     * Punto de entrada principal: normaliza y persiste el lead.
     *
     * @param  array<string,mixed>  $data  — datos del FormIngressRequest validado
     * @throws \Throwable
     */
    public function ingest(
        Empresa $empresa,
        FormForwardingRule $rule,
        array $data
    ): WaLead {
        $normalized = $this->normalizer->normalize($data, $rule->form_name);

        return $this->persistence->persist($normalized, $empresa, $rule);
    }

    /**
     * @param  array<string,mixed>  $payload
     * @param  array<string,string|null>  $requestMeta
     * @throws DomainNotAllowedException
     */
    private function validateOriginDomain(string $allowedDomain, array $payload, array $requestMeta): void
    {
        if ($allowedDomain === '*') {
            return;
        }

        $originHeader = isset($requestMeta['origin']) ? $requestMeta['origin'] : null;
        $refererHeader = isset($requestMeta['referer']) ? $requestMeta['referer'] : null;
        $payloadDomain = isset($payload['domain']) ? (string) $payload['domain'] : '';

        $origin = $originHeader ?: $refererHeader ?: $payloadDomain;
        $host = strtolower((string) (parse_url((string) $origin, PHP_URL_HOST) ?: $origin));
        $host = ltrim($host, 'www.');

        $allowed = strtolower(ltrim($allowedDomain, 'www.'));
        if ($host === '' || ! Str::endsWith($host, $allowed)) {
            throw new DomainNotAllowedException($host, $allowedDomain);
        }
    }
}
