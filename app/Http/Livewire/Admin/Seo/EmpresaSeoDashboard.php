<?php

namespace App\Http\Livewire\Admin\Seo;

use App\Models\Empresa;
use App\Services\Seo\SeoDashboardService;
use App\Services\Seo\SeoPropertyConfigurationState;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;
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

    /** @var string */
    public $querySortColumn = 'clicks';

    /** @var string */
    public $querySortDirection = 'desc';

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
            abort(403);
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
        $this->topQueries = $dashboardService->getTopQueries(
            $this->empresa,
            $from,
            $to,
            null,
            (string) $this->querySortColumn,
            (string) $this->querySortDirection
        );
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

    // ── Top queries: sort ─────────────────────────────────────────────────────

    public function sortTopQueries(string $column, SeoDashboardService $dashboardService): void
    {
        $allowed = ['clicks', 'impressions', 'avg_ctr', 'avg_position'];
        if (! in_array($column, $allowed, true)) {
            return;
        }

        if ($this->querySortColumn === $column) {
            $this->querySortDirection = $this->querySortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->querySortColumn = $column;
            $this->querySortDirection = 'desc';
        }

        $this->loadDashboard($dashboardService);
    }

    public function exportTopQueriesCsv(SeoDashboardService $dashboardService): StreamedResponse
    {
        $this->validate();

        $this->syncConfigurationState($dashboardService);
        abort_unless($this->canShowDashboard, 403);

        $from = Carbon::parse($this->dateFrom)->startOfDay();
        $to = Carbon::parse($this->dateTo)->startOfDay();

        $rows = $dashboardService->getTopQueries(
            $this->empresa,
            $from,
            $to,
            0,
            (string) $this->querySortColumn,
            (string) $this->querySortDirection
        );

        $fileName = sprintf(
            'seo-top-queries-%d-%s-%s.csv',
            (int) $this->empresa->id,
            $from->toDateString(),
            $to->toDateString()
        );

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fputcsv($handle, ['term', 'average_position', 'clicks', 'impressions', 'ctr']);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    (string) ($row['query'] ?? ''),
                    (float) ($row['avg_position'] ?? 0),
                    (int) ($row['clicks'] ?? 0),
                    (int) ($row['impressions'] ?? 0),
                    (float) ($row['avg_ctr'] ?? 0),
                ]);
            }

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportTopLandingPagesCsv(SeoDashboardService $dashboardService): StreamedResponse
    {
        $this->validate();

        $this->syncConfigurationState($dashboardService);
        abort_unless($this->canShowDashboard, 403);

        $from = Carbon::parse($this->dateFrom)->startOfDay();
        $to = Carbon::parse($this->dateTo)->startOfDay();

        $rows = $dashboardService->getTopLandingPages(
            $this->empresa,
            $from,
            $to,
            0
        );

        $fileName = sprintf(
            'seo-top-pages-%d-%s-%s.csv',
            (int) $this->empresa->id,
            $from->toDateString(),
            $to->toDateString()
        );

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fputcsv($handle, ['page', 'users', 'sessions', 'conversions', 'engagement_rate']);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    (string) ($row['landing_page'] ?? ''),
                    (int) ($row['users'] ?? 0),
                    (int) ($row['sessions'] ?? 0),
                    (int) ($row['conversions'] ?? 0),
                    (float) ($row['engagement_rate'] ?? 0),
                ]);
            }

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
