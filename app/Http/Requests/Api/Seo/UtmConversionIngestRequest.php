<?php

namespace App\Http\Requests\Api\Seo;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

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
            'raw_payload_json'    => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'conversion_datetime.required' => 'conversion_datetime es requerido.',
            'conversion_datetime.date'     => 'conversion_datetime debe ser una fecha válida.',
            'page_url.url'                 => 'page_url debe ser una URL válida.',
            'raw_payload_json.array'       => 'raw_payload_json debe ser un objeto/array JSON válido.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'El payload de conversión UTM no es válido.',
                'errors'  => $validator->errors(),
            ], 422)
        );
    }
}
