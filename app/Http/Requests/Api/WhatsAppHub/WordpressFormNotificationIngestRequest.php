<?php

namespace App\Http\Requests\Api\WhatsAppHub;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Str;

class WordpressFormNotificationIngestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $sourceRecordId = $this->input('source_record_id');
        if (is_int($sourceRecordId) || is_float($sourceRecordId)) {
            $this->merge([
                'source_record_id' => (string) $sourceRecordId,
            ]);
        }

        $rawPayload = $this->input('raw_payload_json');
        if (is_string($rawPayload) && trim($rawPayload) !== '') {
            $decoded = json_decode($rawPayload, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $this->merge([
                    'raw_payload_json' => $decoded,
                ]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'source_system' => ['required', 'string', 'in:wordpress_wpforms,wordpress_elementor'],
            'source_record_id' => ['required', 'string', 'max:191'],
            'submitted_at' => ['required', 'date'],
            'form_id' => ['nullable', 'string', 'max:120'],
            'form_name' => ['nullable', 'string', 'max:150'],
            'page_url' => ['nullable', 'url', 'max:500'],
            'fields_json' => ['required', 'array'],
            'raw_payload_json' => ['nullable', 'array'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $maxPayloadBytes = (int) config('integration_security.hardening.max_payload_bytes', 0);
            if ($maxPayloadBytes > 0) {
                $rawContent = (string) $this->getContent();
                if (strlen($rawContent) > $maxPayloadBytes) {
                    $validator->errors()->add(
                        'payload',
                        'El payload excede el tamaño máximo permitido.'
                    );
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'source_system.required' => 'source_system es requerido.',
            'source_system.in' => 'source_system debe ser wordpress_wpforms o wordpress_elementor.',
            'source_record_id.required' => 'source_record_id es requerido.',
            'source_record_id.string' => 'source_record_id debe ser texto.',
            'source_record_id.max' => 'source_record_id no debe superar 191 caracteres.',
            'submitted_at.required' => 'submitted_at es requerido.',
            'submitted_at.date' => 'submitted_at debe ser una fecha válida.',
            'form_id.max' => 'form_id no debe superar 120 caracteres.',
            'form_name.max' => 'form_name no debe superar 150 caracteres.',
            'page_url.url' => 'page_url debe ser una URL válida.',
            'page_url.max' => 'page_url no debe superar 500 caracteres.',
            'fields_json.required' => 'fields_json es requerido.',
            'fields_json.array' => 'fields_json debe ser un objeto/array JSON válido.',
            'raw_payload_json.array' => 'raw_payload_json debe ser un objeto/array JSON válido.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        $errors = $validator->errors()->toArray();
        $failedFields = array_keys($errors);

        $requestIdHeader = $this->header('X-Request-Id');
        $requestId = is_string($requestIdHeader) && trim($requestIdHeader) !== ''
            ? trim($requestIdHeader)
            : (string) Str::uuid();

        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'El payload de notificación de formulario no es válido.',
                'error_code' => 'VALIDATION_FAILED',
                'request_id' => $requestId,
                'errors' => $errors,
                'failed_fields' => $failedFields,
                'debug' => [
                    'received_fields' => array_keys($this->all()),
                ],
            ], 422)
        );
    }
}
