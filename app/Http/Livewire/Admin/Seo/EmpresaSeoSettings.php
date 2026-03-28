<?php

namespace App\Http\Livewire\Admin\Seo;

use App\Models\Empresa;
use App\Models\EmpresaSeoProperty;
use App\Services\Seo\SeoPropertyConfigurationService;
use App\Services\Seo\SeoPropertyConfigurationState;
use App\Services\Seo\SearchConsoleClientService;
use App\Services\Seo\SearchConsoleSyncService;
use App\Services\Seo\SeoUtmCsvImporterService;
use App\Services\Seo\Ga4ClientService;
use App\Services\Seo\SeoPropertyContext;
use App\Models\SeoGscDailyMetric;
use App\Models\SeoGscQuery;
use App\Models\SeoGscPage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;
use Throwable;

class EmpresaSeoSettings extends Component
{
    use WithFileUploads;

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

    /** @var bool */
    public $syncGscLoading = false;

    /** @var array<string, mixed>|null */
    public $syncGscResult = null;

    /** @var string|null */
    public $syncGscError = null;

    /** @var mixed */
    public $utmCsvFile;

    /** @var array<string, mixed>|null */
    public $utmCsvImportResult = null;

    /** @var string|null */
    public $utmCsvImportError = null;

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

    public function runGscSyncNow(SearchConsoleSyncService $syncService): void
    {
        $this->syncGscLoading = true;
        $this->syncGscResult = null;
        $this->syncGscError = null;

        try {
            if (empty($this->searchConsoleProperty)) {
                $this->syncGscError = 'Search Console Property no está configurada.';
                $this->syncGscLoading = false;
                return;
            }

            $empresaSeoProperty = $this->empresa->seoProperty;
            if (! $empresaSeoProperty instanceof EmpresaSeoProperty) {
                $this->syncGscError = 'Configuración SEO de la empresa no encontrada.';
                $this->syncGscLoading = false;
                return;
            }

            $from = now()->subDays(30)->startOfDay();
            $to   = now()->subDay()->endOfDay();

            $result = $syncService->syncEmpresa($this->empresa, $from, $to);

            $empresaId = $this->empresa->id;
            $storedDaily   = SeoGscDailyMetric::where('empresa_id', $empresaId)->count();
            $storedQueries = SeoGscQuery::where('empresa_id', $empresaId)->count();
            $storedPages   = SeoGscPage::where('empresa_id', $empresaId)->count();
            $lastDate      = SeoGscDailyMetric::where('empresa_id', $empresaId)
                ->max('metric_date');

            $this->syncGscResult = [
                'empresa_id'       => $empresaId,
                'property'         => $this->searchConsoleProperty,
                'dateRange'        => $from->toDateString() . ' a ' . $to->toDateString(),
                'daily_rows'       => $result->dailyRows,
                'query_rows'       => $result->queryRows,
                'page_rows'        => $result->pageRows,
                'synced'           => $result->synced,
                'stored_daily'     => $storedDaily,
                'stored_queries'   => $storedQueries,
                'stored_pages'     => $storedPages,
                'last_stored_date' => $lastDate,
            ];

            Log::info('[SEO][GSC][SYNC_NOW] Sync manual exitoso.', [
                'empresa_id' => $this->empresa->id,
                'property'   => $this->searchConsoleProperty,
                'from'       => $from->toDateString(),
                'to'         => $to->toDateString(),
                'daily_rows' => $result->dailyRows,
                'query_rows' => $result->queryRows,
                'page_rows'  => $result->pageRows,
            ]);
        } catch (Throwable $e) {
            $this->syncGscError = 'Error: ' . $e->getMessage();

            Log::error('[SEO][GSC][SYNC_NOW] Error en sync manual.', [
                'empresa_id'               => $this->empresa->id,
                'search_console_property'  => $this->searchConsoleProperty,
                'date_range_start'         => isset($from) ? $from->toDateString() : null,
                'date_range_end'           => isset($to) ? $to->toDateString() : null,
                'exception_class'          => get_class($e),
                'exception_message'        => $e->getMessage(),
            ]);
        } finally {
            $this->syncGscLoading = false;
        }
    }

    public function updatedUtmCsvFile(): void
    {
        $this->resetCsvImportFeedback();
        $this->validateOnly('utmCsvFile', $this->csvImportRules(), $this->csvImportMessages());
    }

    public function importUtmCsv(SeoUtmCsvImporterService $csvImporter): void
    {
        $this->resetCsvImportFeedback();

        $this->validate($this->csvImportRules(), $this->csvImportMessages());

        $storedPath = null;

        try {
            $originalName = method_exists($this->utmCsvFile, 'getClientOriginalName')
                ? (string) $this->utmCsvFile->getClientOriginalName()
                : 'utm-import.csv';

            $extension = method_exists($this->utmCsvFile, 'getClientOriginalExtension')
                ? strtolower((string) $this->utmCsvFile->getClientOriginalExtension())
                : 'csv';

            $filename = 'empresa-' . $this->empresa->id
                . '-utm-' . now()->format('YmdHis')
                . '-' . Str::random(8)
                . '.' . ($extension !== '' ? $extension : 'csv');

            $storedPath = $this->utmCsvFile->storeAs('tmp/seo/utm-imports', $filename, 'local');

            $result = $csvImporter->import(
                $this->empresa,
                storage_path('app/' . $storedPath),
                ['filename' => $originalName]
            );

            $this->utmCsvImportResult = $this->prepareImportResultForView($result);

            if (($result['created'] ?? 0) > 0) {
                session()->flash('utm_csv_import_saved', 'CSV UTM importado correctamente.');
            }
        } catch (Throwable $e) {
            Log::error('[SEO][UTM][CSV_IMPORT] Error durante la importación manual.', [
                'empresa_id' => $this->empresa->id,
                'filename' => isset($originalName) ? $originalName : null,
                'exception_class' => get_class($e),
                'exception_message' => $e->getMessage(),
            ]);

            $this->utmCsvImportError = 'Error interno al importar el CSV UTM.';
        } finally {
            if (is_string($storedPath) && Storage::disk('local')->exists($storedPath)) {
                Storage::disk('local')->delete($storedPath);
            }

            $this->utmCsvFile = null;
        }
    }

    public function render()
    {
        return view('livewire.admin.seo.empresa-seo-settings');
    }

    private function csvImportRules(): array
    {
        return [
            'utmCsvFile' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ];
    }

    private function csvImportMessages(): array
    {
        return [
            'utmCsvFile.required' => 'Debes seleccionar un archivo CSV.',
            'utmCsvFile.file' => 'El archivo cargado no es válido.',
            'utmCsvFile.mimes' => 'El archivo debe estar en formato CSV.',
            'utmCsvFile.max' => 'El archivo no puede superar 10 MB.',
        ];
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

    private function resetCsvImportFeedback(): void
    {
        $this->utmCsvImportResult = null;
        $this->utmCsvImportError = null;
        $this->resetErrorBag('utmCsvFile');
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function prepareImportResultForView(array $result): array
    {
        $errors = isset($result['errors']) && is_array($result['errors'])
            ? $result['errors']
            : [];

        $warnings = isset($result['warnings']) && is_array($result['warnings'])
            ? $result['warnings']
            : [];

        $result['errors_preview'] = array_slice($errors, 0, 10);
        $result['errors_remaining'] = max(count($errors) - count($result['errors_preview']), 0);
        $result['warnings_preview'] = array_slice($warnings, 0, 5);
        $result['warnings_remaining'] = max(count($warnings) - count($result['warnings_preview']), 0);

        return $result;
    }
}
