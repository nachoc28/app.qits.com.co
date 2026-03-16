<?php

namespace App\Services\WhatsAppHub;

use App\Models\FormForwardingRule;
use App\Models\LeadDocument;
use App\Models\WaLead;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Gestiona la generacion de PDF para leads del Modulo 1.
 *
 * Este servicio es independiente del envio a WhatsApp: solo renderiza,
 * almacena el archivo y registra su metadata en lead_documents.
 */
class PdfLeadService
{
    private const DISK_CONFIG_KEY = 'whatsapp_hub.pdf_storage.disk';
    private const BASE_DIR_CONFIG_KEY = 'whatsapp_hub.pdf_storage.base_dir';
    private const LONG_FORM_MIN_FIELDS = 8;
    private const LONG_FORM_MIN_MESSAGE_LENGTH = 160;
    private const MAX_PAYLOAD_ROWS = 20;
    private const MAX_PAYLOAD_VALUE_LENGTH = 180;

    /**
     * Genera y persiste PDF para un lead si la regla lo requiere.
     *
     * @return LeadDocument|null
     */
    public function generateAndPersistForLead(WaLead $lead, FormForwardingRule $rule): ?LeadDocument
    {
        if (! $this->shouldGeneratePdf($lead, $rule)) {
            return null;
        }

        $metadata = $this->generateAndStore($lead);

        $document = $lead->documents()->create([
            'file_name'     => $metadata['file_name'],
            'file_path'     => $metadata['file_path'],
            'mime_type'     => $metadata['mime_type'],
            'file_size'     => $metadata['file_size'],
            'document_type' => LeadDocument::TYPE_PDF,
        ]);

        $lead->events()->create([
            'event_type'   => 'pdf_generated',
            'payload_json' => [
                'file_name' => $metadata['file_name'],
                'file_path' => $metadata['file_path'],
                'file_size' => $metadata['file_size'],
            ],
        ]);

        return $document;
    }

    /**
     * Genera el PDF, lo guarda en storage y retorna metadata.
     *
     * @return array<string,mixed>
     */
    public function generateAndStore(WaLead $lead): array
    {
        $disk = (string) config(self::DISK_CONFIG_KEY, 'local');
        $baseDir = trim((string) config(self::BASE_DIR_CONFIG_KEY, 'whatsapp_hub/leads'), '/');

        $payloadRows = $this->extractReadablePayloadRows($lead->payload_json);
        $fileName = $this->buildSafeFileName($lead);
        $relativePath = $this->buildRelativePath($baseDir, $lead, $fileName);

        $pdfBinary = $this->renderPdfBinary($lead, $payloadRows);

        Storage::disk($disk)->put($relativePath, $pdfBinary);

        return [
            'file_path' => $relativePath,
            'file_name' => $fileName,
            'mime_type' => 'application/pdf',
            'file_size' => strlen($pdfBinary),
            'disk'      => $disk,
        ];
    }

    public function shouldGeneratePdf(WaLead $lead, FormForwardingRule $rule): bool
    {
        if ((bool) $rule->generate_pdf_always) {
            return true;
        }

        if ((bool) $rule->only_for_long_forms) {
            return $this->isLongFormLead($lead);
        }

        return false;
    }

    private function isLongFormLead(WaLead $lead): bool
    {
        $payload = is_array($lead->payload_json) ? $lead->payload_json : [];

        $nonEmptyPayloadFields = 0;
        foreach ($payload as $value) {
            if ($value !== null && $value !== '') {
                $nonEmptyPayloadFields++;
            }
        }

        $messageLength = mb_strlen((string) ($lead->message ?? ''), 'UTF-8');

        return $nonEmptyPayloadFields >= self::LONG_FORM_MIN_FIELDS
            || $messageLength >= self::LONG_FORM_MIN_MESSAGE_LENGTH;
    }

    /**
     * @param  array<string,mixed>|null  $payload
     * @return array<int,array<string,string>>
     */
    private function extractReadablePayloadRows(?array $payload): array
    {
        if (! is_array($payload) || empty($payload)) {
            return [];
        }

        $rows = [];
        foreach ($payload as $key => $value) {
            if (count($rows) >= self::MAX_PAYLOAD_ROWS) {
                break;
            }

            if (is_array($value) || is_object($value)) {
                continue;
            }

            $label = trim((string) $key);
            if ($label === '') {
                continue;
            }

            $stringValue = trim((string) $value);
            if ($stringValue === '') {
                continue;
            }

            if (mb_strlen($stringValue, 'UTF-8') > self::MAX_PAYLOAD_VALUE_LENGTH) {
                $stringValue = mb_substr($stringValue, 0, self::MAX_PAYLOAD_VALUE_LENGTH, 'UTF-8') . '...';
            }

            $rows[] = [
                'label' => Str::headline(str_replace('_', ' ', $label)),
                'value' => $stringValue,
            ];
        }

        return $rows;
    }

    /**
     * @param  array<int,array<string,string>>  $payloadRows
     */
    private function renderPdfBinary(WaLead $lead, array $payloadRows): string
    {
        $wrapper = app('dompdf.wrapper');

        return $wrapper
            ->loadView('pdf.whatsapp-hub.lead', [
                'lead' => $lead,
                'payloadRows' => $payloadRows,
            ])
            ->setPaper('a4')
            ->output();
    }

    private function buildSafeFileName(WaLead $lead): string
    {
        $namePart = Str::slug((string) ($lead->full_name ?: 'lead'));
        if ($namePart === '') {
            $namePart = 'lead';
        }

        $created = $lead->created_at ? $lead->created_at->format('Ymd_His') : now()->format('Ymd_His');

        return 'lead_' . $lead->id . '_' . $namePart . '_' . $created . '.pdf';
    }

    private function buildRelativePath(string $baseDir, WaLead $lead, string $fileName): string
    {
        return $baseDir
            . '/empresa_' . $lead->empresa_id
            . '/lead_' . $lead->id
            . '/' . $fileName;
    }
}
