<?php

namespace App\Http\Livewire\Admin\Seo;

use App\Models\Empresa;
use App\Models\EmpresaSeoProperty;
use App\Services\Seo\SeoPropertyConfigurationService;
use App\Services\Seo\SeoPropertyConfigurationState;
use App\Services\Seo\SearchConsoleClientService;
use App\Services\Seo\Ga4ClientService;
use App\Services\Seo\SeoPropertyContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Throwable;

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

    /** @var bool */
    public $isEditingConfiguration = false;

    /** @var bool */
    public $testGscLoading = false;

    /** @var array<string, mixed>|null */
    public $testGscResult = null;

    /** @var string|null */
    public $testGscError = null;

    /** @var bool */
    public $testGa4Loading = false;

    /** @var array<string, mixed>|null */
    public $testGa4Result = null;

    /** @var string|null */
    public $testGa4Error = null;

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
        $this->isEditingConfiguration = true;

        session()->flash('seo_settings_saved', 'Configuración SEO guardada correctamente.');
    }

    public function testGscConnection(SearchConsoleClientService $searchConsoleClient): void
    {
        $this->testGscLoading = true;
        $this->testGscResult = null;
        $this->testGscError = null;

        try {
            if (empty($this->searchConsoleProperty)) {
                $this->testGscError = 'Search Console Property no está configurada.';
                $this->testGscLoading = false;
                return;
            }

            $empresaSeoProperty = $this->empresa->seoProperty;
            if (! $empresaSeoProperty instanceof EmpresaSeoProperty) {
                $this->testGscError = 'Configuración SEO de la empresa no encontrada.';
                $this->testGscLoading = false;
                return;
            }

            $context = new SeoPropertyContext($this->empresa, $empresaSeoProperty);
            $from = now()->subDays(7)->startOfDay();
            $to = now()->startOfDay();

            $dailyRows = $searchConsoleClient->fetchDailyMetrics($context, $from, $to);

            $this->testGscResult = [
                'property' => $context->gscProperty(),
                'dateRange' => $from->toDateString() . ' a ' . $to->toDateString(),
                'rowCount' => count($dailyRows),
                'firstRows' => array_slice($dailyRows, 0, 3),
            ];

            Log::info('[SEO][GSC][TEST] Test exitoso.', [
                'empresa_id' => $this->empresa->id,
                'property' => $context->gscProperty(),
                'row_count' => count($dailyRows),
            ]);
        } catch (Throwable $e) {
            $this->testGscError = 'Error: ' . $e->getMessage();

            $dateRange = (isset($from) && isset($to))
                ? $from->toDateString() . ' a ' . $to->toDateString()
                : 'desconocido';

            $tokenRefreshStatus = strpos($e->getMessage(), 'token') !== false
                ? 'possibly_failed'
                : 'unknown';

            Log::error('[SEO][GSC][TEST] Error en test - ejecución fallida.', [
                'provider' => 'gsc',
                'empresa_id' => $this->empresa->id,
                'search_console_property' => $this->searchConsoleProperty,
                'date_range_start' => isset($from) ? $from->toDateString() : null,
                'date_range_end' => isset($to) ? $to->toDateString() : null,
                'exception_class' => get_class($e),
                'exception_message' => $e->getMessage(),
                'token_refresh_status' => $tokenRefreshStatus,
            ]);
        } finally {
            $this->testGscLoading = false;
        }
    }

    public function testGa4Connection(Ga4ClientService $ga4Client): void
    {
        $this->testGa4Loading = true;
        $this->testGa4Result = null;
        $this->testGa4Error = null;

        try {
            if (empty($this->ga4PropertyId)) {
                $this->testGa4Error = 'GA4 Property ID no está configurado.';
                $this->testGa4Loading = false;
                return;
            }

            $empresaSeoProperty = $this->empresa->seoProperty;
            if (! $empresaSeoProperty instanceof EmpresaSeoProperty) {
                $this->testGa4Error = 'Configuración SEO de la empresa no encontrada.';
                $this->testGa4Loading = false;
                return;
            }

            $from = now()->subDays(7)->startOfDay();
            $to = now()->startOfDay();

            $dailyRows = $ga4Client->fetchDailyMetricsByProperty((string) $this->ga4PropertyId, $from, $to);

            $this->testGa4Result = [
                'ga4PropertyId' => $this->ga4PropertyId,
                'dateRange' => $from->toDateString() . ' a ' . $to->toDateString(),
                'rowCount' => count($dailyRows),
                'firstRows' => array_slice($dailyRows, 0, 3),
            ];

            Log::info('[SEO][GA4][TEST] Test exitoso.', [
                'empresa_id' => $this->empresa->id,
                'ga4_property_id' => $this->ga4PropertyId,
                'row_count' => count($dailyRows),
            ]);
        } catch (Throwable $e) {
            $this->testGa4Error = 'Error: ' . $e->getMessage();

            $dateRange = (isset($from) && isset($to))
                ? $from->toDateString() . ' a ' . $to->toDateString()
                : 'desconocido';

            $tokenRefreshStatus = strpos($e->getMessage(), 'token') !== false
                ? 'possibly_failed'
                : 'unknown';

            Log::error('[SEO][GA4][TEST] Error en test - ejecución fallida.', [
                'provider' => 'ga4',
                'empresa_id' => $this->empresa->id,
                'ga4_property_id' => $this->ga4PropertyId,
                'date_range_start' => isset($from) ? $from->toDateString() : null,
                'date_range_end' => isset($to) ? $to->toDateString() : null,
                'exception_class' => get_class($e),
                'exception_message' => $e->getMessage(),
                'token_refresh_status' => $tokenRefreshStatus,
            ]);
        } finally {
            $this->testGa4Loading = false;
        }
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
            $this->isEditingConfiguration = false;
            $this->siteUrl = null;
            $this->searchConsoleProperty = null;
            $this->ga4PropertyId = null;
            $this->wordpressSiteUrl = null;
            $this->utmTrackingEnabled = false;
            $this->gscEnabled = false;
            $this->ga4Enabled = false;

            return;
        }

        $this->isEditingConfiguration = true;

        $this->siteUrl = $property->site_url;
        $this->searchConsoleProperty = $property->search_console_property;
        $this->ga4PropertyId = $property->ga4_property_id;
        $this->wordpressSiteUrl = $property->wordpress_site_url;
        $this->utmTrackingEnabled = (bool) $property->utm_tracking_enabled;
        $this->gscEnabled = (bool) $property->gsc_enabled;
        $this->ga4Enabled = (bool) $property->ga4_enabled;
    }
}
