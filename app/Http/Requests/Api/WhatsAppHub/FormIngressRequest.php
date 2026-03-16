<?php

namespace App\Http\Requests\Api\WhatsAppHub;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class FormIngressRequest extends FormRequest
{
    /**
     * Este endpoint es público (no requiere autenticación).
     * La autorización real se hace en el controlador vía site_key + dominio.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Datos de contacto obligatorios
            'nombre'           => ['required', 'string', 'max:180'],
            'telefono'         => ['required', 'string', 'max:50'],

            // Datos de contacto opcionales
            'correo'           => ['nullable', 'email', 'max:180'],
            'empresa'          => ['nullable', 'string', 'max:180'],
            'ciudad'           => ['nullable', 'string', 'max:100'],
            'mensaje'          => ['nullable', 'string', 'max:5000'],

            // Meta del formulario
            'formulario'       => ['nullable', 'string', 'max:150'],
            'url_origen'       => ['nullable', 'url', 'max:500'],

            // Payload completo del formulario (acepta objeto JSON)
            'payload_completo' => ['nullable', 'array'],

            // UTM / trazabilidad — todos opcionales
            'utm_source'       => ['nullable', 'string', 'max:150'],
            'utm_campaign'     => ['nullable', 'string', 'max:150'],
            'medium'           => ['nullable', 'string', 'max:100'],
            'campaign'         => ['nullable', 'string', 'max:150'],
            'page_url'         => ['nullable', 'url', 'max:500'],
            'domain'           => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required'   => 'El nombre del contacto es obligatorio.',
            'telefono.required' => 'El teléfono del contacto es obligatorio.',
            'correo.email'      => 'El correo electrónico no tiene un formato válido.',
            'url_origen.url'    => 'La URL de origen no tiene un formato válido.',
            'page_url.url'      => 'La page_url no tiene un formato válido.',
        ];
    }

    /**
     * Devuelve JSON siempre (no redirige) cuando la validación falla.
     * Necesario para endpoints de API consumidos por sitios externos.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Los datos del formulario no son válidos.',
                'errors'  => $validator->errors(),
            ], 422)
        );
    }
}
