<?php

namespace App\Services\Seo;

use App\Models\Empresa;
use App\Models\EmpresaSeoProperty;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Gestiona la configuración SEO de una empresa.
 *
 * Aplica igual para empresas cliente y empresa interna (QITS),
 * sin lógica especial por tipo de empresa.
 */
class SeoPropertyConfigurationService
{
    /**
     * Carga la configuración SEO de la empresa.
     */
    public function load(Empresa $empresa): ?EmpresaSeoProperty
    {
        return $empresa->relationLoaded('seoProperty')
            ? $empresa->seoProperty
            : $empresa->seoProperty()->first();
    }

    /**
     * Crea o actualiza la configuración SEO de la empresa.
     *
     * @param array<string, mixed> $input
     * @throws ValidationException
     */
    public function save(Empresa $empresa, array $input): SeoPropertyConfigurationState
    {
        $payload = $this->normalizePayload($input);

        $validator = $this->validatorFor($payload);
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $attributes = Arr::only($payload, [
            'site_url',
            'search_console_property',
            'ga4_property_id',
            'wordpress_site_url',
            'utm_tracking_enabled',
            'gsc_enabled',
            'ga4_enabled',
        ]);

        $property = EmpresaSeoProperty::updateOrCreate(
            ['empresa_id' => $empresa->id],
            $attributes
        );

        return $this->evaluate($property->fresh());
    }

    /**
     * Evalúa el estado actual de configuración SEO de la empresa.
     */
    public function state(Empresa $empresa): SeoPropertyConfigurationState
    {
        $property = $this->load($empresa);

        if (! $property instanceof EmpresaSeoProperty) {
            return new SeoPropertyConfigurationState(
                SeoPropertyConfigurationState::STATUS_NOT_CONFIGURED,
                null,
                ['No existe registro SEO para la empresa.']
            );
        }

        return $this->evaluate($property);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function validatorFor(array $payload): ValidatorContract
    {
        return Validator::make($payload, [
            'site_url' => ['required', 'url', 'max:500'],
            'search_console_property' => ['nullable', 'string', 'max:255', 'required_if:gsc_enabled,1'],
            'ga4_property_id' => ['nullable', 'string', 'max:120', 'required_if:ga4_enabled,1'],
            'wordpress_site_url' => ['nullable', 'url', 'max:500'],
            'utm_tracking_enabled' => ['required', 'boolean'],
            'gsc_enabled' => ['required', 'boolean'],
            'ga4_enabled' => ['required', 'boolean'],
        ], [
            'site_url.required' => 'site_url es obligatorio.',
            'site_url.url' => 'site_url debe ser una URL válida.',
            'search_console_property.required_if' => 'search_console_property es obligatorio cuando gsc_enabled está activo.',
            'ga4_property_id.required_if' => 'ga4_property_id es obligatorio cuando ga4_enabled está activo.',
            'wordpress_site_url.url' => 'wordpress_site_url debe ser una URL válida.',
        ]);
    }

    private function evaluate(EmpresaSeoProperty $property): SeoPropertyConfigurationState
    {
        $payload = [
            'site_url' => $property->site_url,
            'search_console_property' => $property->search_console_property,
            'ga4_property_id' => $property->ga4_property_id,
            'wordpress_site_url' => $property->wordpress_site_url,
            'utm_tracking_enabled' => (bool) $property->utm_tracking_enabled,
            'gsc_enabled' => (bool) $property->gsc_enabled,
            'ga4_enabled' => (bool) $property->ga4_enabled,
        ];

        $validator = $this->validatorFor($payload);
        $errors = $validator->fails()
            ? $validator->errors()->all()
            : [];

        $warnings = [];
        if ((bool) $property->utm_tracking_enabled && empty($property->wordpress_site_url)) {
            $warnings[] = 'wordpress_site_url es recomendado cuando utm_tracking_enabled está activo.';
        }

        $status = $errors === []
            ? SeoPropertyConfigurationState::STATUS_CONFIGURED
            : SeoPropertyConfigurationState::STATUS_PARTIALLY_CONFIGURED;

        return new SeoPropertyConfigurationState($status, $property, $errors, $warnings);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function normalizePayload(array $input): array
    {
        return [
            'site_url' => $this->nullableTrim($input, 'site_url'),
            'search_console_property' => $this->nullableTrim($input, 'search_console_property'),
            'ga4_property_id' => $this->nullableTrim($input, 'ga4_property_id'),
            'wordpress_site_url' => $this->nullableTrim($input, 'wordpress_site_url'),
            'utm_tracking_enabled' => (bool) ($input['utm_tracking_enabled'] ?? false),
            'gsc_enabled' => (bool) ($input['gsc_enabled'] ?? false),
            'ga4_enabled' => (bool) ($input['ga4_enabled'] ?? false),
        ];
    }

    /**
     * @param array<string, mixed> $input
     */
    private function nullableTrim(array $input, string $key): ?string
    {
        if (! array_key_exists($key, $input) || $input[$key] === null) {
            return null;
        }

        $value = trim((string) $input[$key]);

        return $value === '' ? null : $value;
    }
}
