<?php

namespace App\Http\Livewire\Admin\Seo;

use App\Models\Empresa;
use App\Services\Seo\SeoDashboardService;
use App\Services\Seo\SeoPropertyConfigurationState;
use Illuminate\Support\Carbon;
use Livewire\Component;

class EmpresaSeoDashboard extends Component
{
    /** @var Empresa */
    public $empresa;

    /** @var string */
    public $dateFrom;

    /** @var string */
    public $dateTo;

    /** @var array<string, mixed> */
    public $kpis = [];

    /** @var array<int, array<string, mixed>> */
    public $topQueries = [];

    /** @var array<int, array<string, mixed>> */
    public $topLandingPages = [];

    /** @var array<int, array<string, mixed>> */
    public $recentUtmConversions = [];

    /** @var array<string, mixed> */
    public $trends = [];

    /** @var bool */
    public $loaded = false;

    /** @var string */
    public $configurationStatus = SeoPropertyConfigurationState::STATUS_NOT_CONFIGURED;

    /** @var array<int, string> */
    public $statusWarnings = [];

    /** @var array<int, string> */
    public $statusErrors = [];

    /** @var bool */
    public $canShowDashboard = false;

    protected function rules(): array
    {
        return [
            'dateFrom' => ['required', 'date'],
            'dateTo' => ['required', 'date', 'after_or_equal:dateFrom'],
        ];
    }

    public function mount(Empresa $empresa, SeoDashboardService $dashboardService): void
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

        $range = (int) config('seo.dashboard.default_range_days', 28);
        $to = now()->startOfDay();
        $from = now()->subDays(max($range - 1, 0))->startOfDay();

        $this->dateFrom = $from->toDateString();
        $this->dateTo = $to->toDateString();

        $this->syncConfigurationState($dashboardService);

        if ($this->canShowDashboard) {
            $this->loadDashboard($dashboardService);
            return;
        }

        $this->resetDashboardData();
    }

    public function applyFilters(SeoDashboardService $dashboardService): void
    {
        $this->validate();

        $this->syncConfigurationState($dashboardService);

        if (! $this->canShowDashboard) {
            $this->resetDashboardData();
            return;
        }

        $this->loadDashboard($dashboardService);
    }

    private function loadDashboard(SeoDashboardService $dashboardService): void
    {
        $from = Carbon::parse($this->dateFrom)->startOfDay();
        $to = Carbon::parse($this->dateTo)->startOfDay();

        $payload = $dashboardService->getDashboard($this->empresa, $from, $to)->toArray();

        $this->kpis = (array) ($payload['kpis'] ?? []);
        $this->topQueries = (array) ($payload['top_queries'] ?? []);
        $this->topLandingPages = (array) ($payload['top_landing_pages'] ?? []);
        $this->recentUtmConversions = (array) ($payload['recent_utm_conversions'] ?? []);
        $this->trends = (array) ($payload['trends'] ?? []);
        $this->loaded = true;
    }

    private function syncConfigurationState(SeoDashboardService $dashboardService): void
    {
        $state = $dashboardService->configurationState($this->empresa);

        $this->configurationStatus = $state->status;
        $this->statusWarnings = $state->warnings;
        $this->statusErrors = $state->errors;
        $this->canShowDashboard = $state->isConfigured();
    }

    private function resetDashboardData(): void
    {
        $this->kpis = [];
        $this->topQueries = [];
        $this->topLandingPages = [];
        $this->recentUtmConversions = [];
        $this->trends = [];
        $this->loaded = true;
    }

    public function render()
    {
        return view('livewire.admin.seo.empresa-seo-dashboard');
    }
}
