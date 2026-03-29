<?php

namespace App\Http\Requests\Api\Seo;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Str;

class UtmConversionIngestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $raw = $this->input('raw_payload_json');

        // Permite recibir raw_payload_json como objeto o como JSON string.
        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $this->merge([
                    'raw_payload_json' => $decoded,
                ]);
            }
        }

        // Compatibilidad temporal: si llega id legado, se mapea a source_record_id.
        if (! $this->filled('source_record_id') && $this->filled('id')) {
            $this->merge([
                'source_record_id' => (string) $this->input('id'),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'conversion_datetime' => ['required', 'date'],
            'page_url'            => ['nullable', 'url', 'max:500'],
            'form_name'           => ['nullable', 'string', 'max:150'],
            'source'              => ['nullable', 'string', 'max:120'],
            'medium'              => ['nullable', 'string', 'max:120'],
            'campaign'            => ['nullable', 'string', 'max:150'],
            'term'                => ['nullable', 'string', 'max:150'],
            'content'             => ['nullable', 'string', 'max:150'],
            'event_name'          => ['nullable', 'string', 'max:120'],
            'lead_id'             => ['nullable', 'integer', 'min:1'],
            'source_record_id'    => ['required', 'string', 'max:191'],
            'raw_payload_json'    => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'conversion_datetime.required' => 'conversion_datetime es requerido.',
            'conversion_datetime.date'     => 'conversion_datetime debe ser una fecha válida.',
            'page_url.url'                 => 'page_url debe ser una URL válida.',
            'event_name.max'               => 'event_name no debe superar 120 caracteres.',
            'source_record_id.required'    => 'source_record_id es requerido.',
            'source_record_id.max'         => 'source_record_id no debe superar 191 caracteres.',
            'source_record_id.string'      => 'source_record_id debe ser texto.',
            'raw_payload_json.array'       => 'raw_payload_json debe ser un objeto/array JSON válido.',
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
                'message' => 'El payload de conversión UTM no es válido.',
                'error_code' => 'VALIDATION_FAILED',
                'request_id' => $requestId,
                'errors'  => $errors,
                'failed_fields' => $failedFields,
                'debug' => [
                    'received_fields' => array_keys($this->all()),
                ],
            ], 422)
        );
    }
}
