<?php

namespace App\Http\Livewire\Admin\Seo;

use App\Models\Empresa;
use App\Services\Seo\SeoPropertyConfigurationService;
use App\Services\Seo\SeoPropertyConfigurationState;
use Livewire\Component;

class EmpresaSeoSettings extends Component
{
    /** @var Empresa */
    public $empresa;

    /** @var string|null */
    public $siteUrl;

    /** @var string|null */
    public $searchConsoleProperty;

    /** @var string|null */
    public $ga4PropertyId;

    /** @var string|null */
    public $wordpressSiteUrl;

    /** @var bool */
    public $utmTrackingEnabled = false;

    /** @var bool */
    public $gscEnabled = false;

    /** @var bool */
    public $ga4Enabled = false;

    /** @var string */
    public $configurationStatus = SeoPropertyConfigurationState::STATUS_NOT_CONFIGURED;

    /** @var array<int, string> */
    public $statusWarnings = [];

    /** @var array<int, string> */
    public $statusErrors = [];

    protected function rules(): array
    {
        return [
            'siteUrl' => ['required', 'url', 'max:500'],
            'searchConsoleProperty' => ['nullable', 'string', 'max:255', 'required_if:gscEnabled,1'],
            'ga4PropertyId' => ['nullable', 'string', 'max:120', 'required_if:ga4Enabled,1'],
            'wordpressSiteUrl' => ['nullable', 'url', 'max:500'],
            'utmTrackingEnabled' => ['required', 'boolean'],
            'gscEnabled' => ['required', 'boolean'],
            'ga4Enabled' => ['required', 'boolean'],
        ];
    }

    public function mount(Empresa $empresa, SeoPropertyConfigurationService $configurationService): void
    {
        if (! auth()->check()) {
            abort(401);
        }

        /** @var \App\Models\User $user */
        $user = auth()->user();

        if (! $user->isAdmin() && (int) $user->empresa_id !== (int) $empresa->id) {
            abort(403);
        }

        $this->empresa = $empresa;
        $this->loadConfiguration($configurationService);
    }

    public function saveSettings(SeoPropertyConfigurationService $configurationService): void
    {
        $this->validate();

        $state = $configurationService->save($this->empresa, [
            'site_url' => $this->siteUrl,
            'search_console_property' => $this->searchConsoleProperty,
            'ga4_property_id' => $this->ga4PropertyId,
            'wordpress_site_url' => $this->wordpressSiteUrl,
            'utm_tracking_enabled' => $this->utmTrackingEnabled,
            'gsc_enabled' => $this->gscEnabled,
            'ga4_enabled' => $this->ga4Enabled,
        ]);

        $this->configurationStatus = $state->status;
        $this->statusWarnings = $state->warnings;
        $this->statusErrors = $state->errors;

        session()->flash('seo_settings_saved', 'Configuración SEO guardada correctamente.');
    }

    public function render()
    {
        return view('livewire.admin.seo.empresa-seo-settings');
    }

    private function loadConfiguration(SeoPropertyConfigurationService $configurationService): void
    {
        $state = $configurationService->state($this->empresa);

        $this->configurationStatus = $state->status;
        $this->statusWarnings = $state->warnings;
        $this->statusErrors = $state->errors;

        $property = $state->property;
        if (! $property) {
            $this->siteUrl = null;
            $this->searchConsoleProperty = null;
            $this->ga4PropertyId = null;
            $this->wordpressSiteUrl = null;
            $this->utmTrackingEnabled = false;
            $this->gscEnabled = false;
            $this->ga4Enabled = false;

            return;
        }

        $this->siteUrl = $property->site_url;
        $this->searchConsoleProperty = $property->search_console_property;
        $this->ga4PropertyId = $property->ga4_property_id;
        $this->wordpressSiteUrl = $property->wordpress_site_url;
        $this->utmTrackingEnabled = (bool) $property->utm_tracking_enabled;
        $this->gscEnabled = (bool) $property->gsc_enabled;
        $this->ga4Enabled = (bool) $property->ga4_enabled;
    }
}
