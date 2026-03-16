<?php

namespace App\Services\Seo;

use App\Exceptions\Seo\SeoPropertyNotConfiguredException;
use App\Models\Empresa;
use App\Models\EmpresaSeoProperty;
use App\Models\SeoGa4DailyMetric;
use App\Models\SeoGa4LandingPage;
use App\Models\SeoGscDailyMetric;
use App\Models\SeoGscQuery;
use App\Models\SeoUtmConversion;
use Illuminate\Support\Carbon;

/**
 * Orquestador del dashboard SEO por empresa.
 *
 * Responsabilidades:
 *  - Resolver la configuración SEO de una empresa en un SeoPropertyContext.
 *  - Leer métricas agregadas desde la BD local (ya sincronizadas por jobs).
 *  - Devolver datos listos para el componente Livewire del dashboard.
 *
 * Lo que NO hace:
 *  - No llama a APIs externas (eso es tarea de los jobs de sync).
 *  - No contiene lógica de negocio compleja.
 *  - No formatea para HTML; solo estructuras de datos.
 *
 * Inyección recomendada en Livewire:
 *   public function mount(SeoDashboardService $service) { ... }
 */
class SeoDashboardService
{
    /** @var SeoPropertyConfigurationService */
    private $configurationService;

    public function __construct(SeoPropertyConfigurationService $configurationService)
    {
        $this->configurationService = $configurationService;
    }

    /**
     * Punto de entrada principal para el dashboard SEO.
     *
     * Devuelve una estructura única con:
     *  - KPIs resumidos
     *  - Top queries
     *  - Top landing pages
     *  - Conversiones UTM recientes
     *  - Series de tendencia para gráficos
     *
     */
    public function getDashboard(Empresa $empresa, Carbon $from, Carbon $to): SeoDashboardPayload
    {
        $state = $this->configurationState($empresa);

        $payload = new SeoDashboardPayload($from, $to);

        if (! $state->isConfigured()) {
            $payload->kpis = [
                'organic_clicks' => 0,
                'impressions' => 0,
                'avg_ctr' => 0,
                'avg_position' => 0,
                'users' => 0,
                'sessions' => 0,
                'total_utm_conversions' => 0,
                'flags' => [
                    'gsc_enabled' => false,
                    'ga4_enabled' => false,
                    'utm_enabled' => false,
                ],
            ];

            $payload->trends = [
                'labels' => array_column($this->buildDateSeries($from, $to), 'date'),
                'gsc' => ['clicks' => [], 'impressions' => [], 'ctr' => [], 'position' => []],
                'ga4' => ['users' => [], 'sessions' => [], 'conversions' => []],
                'utm' => ['conversions' => []],
            ];

            return $payload;
        }

        $context = $this->resolveContext($empresa);

        $summary = $this->getSummary($empresa, $from, $to);

        $payload->kpis = [
            'organic_clicks' => $summary->gscClicks,
            'impressions' => $summary->gscImpressions,
            'avg_ctr' => $summary->gscAvgCtr,
            'avg_position' => $summary->gscAvgPosition,
            'users' => $summary->ga4Users,
            'sessions' => $summary->ga4Sessions,
            'total_utm_conversions' => $summary->utmConversions,
            'flags' => [
                'gsc_enabled' => $summary->gscEnabled,
                'ga4_enabled' => $summary->ga4Enabled,
                'utm_enabled' => $summary->utmEnabled,
            ],
        ];

        $payload->topQueries = $context->isGscReady()
            ? $this->getTopQueries($empresa, $from, $to)
            : [];

        $payload->topLandingPages = $context->isGa4Ready()
            ? $this->getTopLandingPages($empresa, $from, $to)
            : [];

        $payload->recentUtmConversions = $context->isUtmReady()
            ? $this->getRecentUtmConversions($empresa, $from, $to)
            : [];

        $payload->trends = $this->getTrendSeries($empresa, $from, $to);

        return $payload;
    }

    /**
     * Construye el resumen agregado del periodo para el dashboard principal.
     * Lee de la BD local; no dispara sincronización con Google.
     *
     */
    public function getSummary(Empresa $empresa, Carbon $from, Carbon $to): SeoDashboardSummary
    {
        $property = $empresa->relationLoaded('seoProperty')
            ? $empresa->seoProperty
            : $empresa->seoProperty()->first();

        if (! $property instanceof EmpresaSeoProperty) {
            return new SeoDashboardSummary($from, $to);
        }

        $context = $this->resolveContext($empresa);

        $summary              = new SeoDashboardSummary($from, $to);
        $summary->gscEnabled  = $context->isGscReady();
        $summary->ga4Enabled  = $context->isGa4Ready();
        $summary->utmEnabled  = $context->isUtmReady();

        if ($context->isGscReady()) {
            $this->fillGscSummary($summary, $empresa, $from, $to);
        }

        if ($context->isGa4Ready()) {
            $this->fillGa4Summary($summary, $empresa, $from, $to);
        }

        if ($context->isUtmReady()) {
            $summary->utmConversions = SeoUtmConversion::where('empresa_id', $empresa->id)
                ->whereBetween('conversion_datetime', [
                    $from->copy()->startOfDay()->toDateTimeString(),
                    $to->copy()->endOfDay()->toDateTimeString(),
                ])
                ->count();
        }

        return $summary;
    }

    public function configurationState(Empresa $empresa): SeoPropertyConfigurationState
    {
        return $this->configurationService->state($empresa);
    }

    /**
     * Top queries agregadas por clics para el rango dado.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getTopQueries(Empresa $empresa, Carbon $from, Carbon $to, ?int $limit = null): array
    {
        $limit = $limit ?: (int) config('seo.dashboard.top_queries_limit', 50);

        $rows = SeoGscQuery::query()
            ->where('empresa_id', $empresa->id)
            ->whereBetween('metric_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('
                `query`,
                COALESCE(SUM(clicks), 0)       AS clicks,
                COALESCE(SUM(impressions), 0)  AS impressions,
                COALESCE(AVG(ctr), 0)          AS avg_ctr,
                COALESCE(AVG(avg_position), 0) AS avg_position
            ')
            ->groupBy('query')
            ->orderByDesc('clicks')
            ->limit($limit)
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'query' => $row->query,
                'clicks' => (int) $row->clicks,
                'impressions' => (int) $row->impressions,
                'avg_ctr' => round((float) $row->avg_ctr, 4),
                'avg_position' => round((float) $row->avg_position, 2),
            ];
        }

        return $out;
    }

    /**
     * Top landing pages GA4 agregadas por sesiones para el rango dado.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getTopLandingPages(Empresa $empresa, Carbon $from, Carbon $to, ?int $limit = null): array
    {
        $limit = $limit ?: (int) config('seo.dashboard.top_landings_limit', 50);

        $rows = SeoGa4LandingPage::query()
            ->where('empresa_id', $empresa->id)
            ->whereBetween('metric_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('
                landing_page,
                COALESCE(SUM(users), 0)               AS users,
                COALESCE(SUM(sessions), 0)            AS sessions,
                COALESCE(SUM(conversions), 0)         AS conversions,
                COALESCE(AVG(engagement_rate), 0)     AS engagement_rate
            ')
            ->groupBy('landing_page')
            ->orderByDesc('sessions')
            ->limit($limit)
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'landing_page' => $row->landing_page,
                'users' => (int) $row->users,
                'sessions' => (int) $row->sessions,
                'conversions' => (int) $row->conversions,
                'engagement_rate' => round((float) $row->engagement_rate, 4),
            ];
        }

        return $out;
    }

    /**
     * Conversiones UTM recientes dentro del rango solicitado.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRecentUtmConversions(Empresa $empresa, Carbon $from, Carbon $to, int $limit = 20): array
    {
        $rows = SeoUtmConversion::query()
            ->where('empresa_id', $empresa->id)
            ->whereBetween('conversion_datetime', [
                $from->copy()->startOfDay()->toDateTimeString(),
                $to->copy()->endOfDay()->toDateTimeString(),
            ])
            ->select([
                'id',
                'conversion_datetime',
                'page_url',
                'form_name',
                'source',
                'medium',
                'campaign',
                'event_name',
                'lead_id',
            ])
            ->orderByDesc('conversion_datetime')
            ->limit($limit)
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'id' => (int) $row->id,
                'conversion_datetime' => optional($row->conversion_datetime)->toDateTimeString(),
                'page_url' => $row->page_url,
                'form_name' => $row->form_name,
                'source' => $row->source,
                'medium' => $row->medium,
                'campaign' => $row->campaign,
                'event_name' => $row->event_name,
                'lead_id' => $row->lead_id,
            ];
        }

        return $out;
    }

    /**
     * Series diarias para gráficos del dashboard.
     *
     * @return array<string, mixed>
     */
    public function getTrendSeries(Empresa $empresa, Carbon $from, Carbon $to): array
    {
        $dates = $this->buildDateSeries($from, $to);
        $dateKeys = array_column($dates, 'date');

        $gscByDate = SeoGscDailyMetric::query()
            ->where('empresa_id', $empresa->id)
            ->whereBetween('metric_date', [$from->toDateString(), $to->toDateString()])
            ->get()
            ->keyBy(function (SeoGscDailyMetric $row) {
                return $row->metric_date->toDateString();
            });

        $ga4ByDate = SeoGa4DailyMetric::query()
            ->where('empresa_id', $empresa->id)
            ->whereBetween('metric_date', [$from->toDateString(), $to->toDateString()])
            ->get()
            ->keyBy(function (SeoGa4DailyMetric $row) {
                return $row->metric_date->toDateString();
            });

        $utmByDate = SeoUtmConversion::query()
            ->where('empresa_id', $empresa->id)
            ->whereBetween('conversion_datetime', [
                $from->copy()->startOfDay()->toDateTimeString(),
                $to->copy()->endOfDay()->toDateTimeString(),
            ])
            ->selectRaw('DATE(conversion_datetime) AS d, COUNT(*) AS total')
            ->groupBy('d')
            ->pluck('total', 'd');

        $series = [
            'labels' => $dateKeys,
            'gsc' => [
                'clicks' => [],
                'impressions' => [],
                'ctr' => [],
                'position' => [],
            ],
            'ga4' => [
                'users' => [],
                'sessions' => [],
                'conversions' => [],
            ],
            'utm' => [
                'conversions' => [],
            ],
        ];

        foreach ($dateKeys as $date) {
            $gsc = $gscByDate->get($date);
            $ga4 = $ga4ByDate->get($date);

            $series['gsc']['clicks'][] = $gsc ? (int) $gsc->clicks : 0;
            $series['gsc']['impressions'][] = $gsc ? (int) $gsc->impressions : 0;
            $series['gsc']['ctr'][] = $gsc ? (float) $gsc->ctr : 0.0;
            $series['gsc']['position'][] = $gsc ? (float) $gsc->avg_position : 0.0;

            $series['ga4']['users'][] = $ga4 ? (int) $ga4->users : 0;
            $series['ga4']['sessions'][] = $ga4 ? (int) $ga4->sessions : 0;
            $series['ga4']['conversions'][] = $ga4 ? (int) ($ga4->conversions ?? 0) : 0;

            $series['utm']['conversions'][] = (int) ($utmByDate[$date] ?? 0);
        }

        return $series;
    }

    /**
     * Devuelve las métricas GSC diarias del periodo desde la BD local.
     * Ordenadas por fecha ASC, listas para gráficos de serie temporal.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, SeoGscDailyMetric>
     */
    public function getGscDailyMetrics(Empresa $empresa, Carbon $from, Carbon $to)
    {
        return SeoGscDailyMetric::where('empresa_id', $empresa->id)
            ->whereBetween('metric_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('metric_date')
            ->get();
    }

    /**
     * Devuelve las métricas GA4 diarias del periodo desde la BD local.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, SeoGa4DailyMetric>
     */
    public function getGa4DailyMetrics(Empresa $empresa, Carbon $from, Carbon $to)
    {
        return SeoGa4DailyMetric::where('empresa_id', $empresa->id)
            ->whereBetween('metric_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('metric_date')
            ->get();
    }

    /**
     * Devuelve las conversiones UTM del periodo desde la BD local.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, SeoUtmConversion>
     */
    public function getUtmConversions(Empresa $empresa, Carbon $from, Carbon $to)
    {
        return SeoUtmConversion::where('empresa_id', $empresa->id)
            ->whereBetween('conversion_datetime', [
                $from->copy()->startOfDay()->toDateTimeString(),
                $to->copy()->endOfDay()->toDateTimeString(),
            ])
            ->orderBy('conversion_datetime')
            ->get();
    }

    /**
     * Resuelve la configuración SEO de la empresa en un SeoPropertyContext.
     * Punto de entrada del resto de la capa SEO.
     *
     * @throws SeoPropertyNotConfiguredException
     */
    public function resolveContext(Empresa $empresa): SeoPropertyContext
    {
        $property = $empresa->seoProperty;

        if (! $property instanceof EmpresaSeoProperty) {
            throw new SeoPropertyNotConfiguredException($empresa);
        }

        return new SeoPropertyContext($empresa, $property);
    }

    /**
     * @return array<int, array{date: string}>
     */
    private function buildDateSeries(Carbon $from, Carbon $to): array
    {
        $cursor = $from->copy()->startOfDay();
        $end = $to->copy()->startOfDay();
        $dates = [];

        while ($cursor->lte($end)) {
            $dates[] = ['date' => $cursor->toDateString()];
            $cursor->addDay();
        }

        return $dates;
    }

    // ── Helpers privados ──────────────────────────────────────────────────────

    private function fillGscSummary(
        SeoDashboardSummary $summary,
        Empresa $empresa,
        Carbon $from,
        Carbon $to
    ): void {
        $agg = SeoGscDailyMetric::where('empresa_id', $empresa->id)
            ->whereBetween('metric_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('
                COALESCE(SUM(clicks), 0)       AS total_clicks,
                COALESCE(SUM(impressions), 0)  AS total_impressions,
                COALESCE(AVG(ctr), 0)          AS avg_ctr,
                COALESCE(AVG(avg_position), 0) AS avg_position
            ')
            ->first();

        if ($agg) {
            $summary->gscClicks       = (int)   $agg->total_clicks;
            $summary->gscImpressions  = (int)   $agg->total_impressions;
            $summary->gscAvgCtr       = round((float) $agg->avg_ctr, 4);
            $summary->gscAvgPosition  = round((float) $agg->avg_position, 2);
        }
    }

    private function fillGa4Summary(
        SeoDashboardSummary $summary,
        Empresa $empresa,
        Carbon $from,
        Carbon $to
    ): void {
        $agg = SeoGa4DailyMetric::where('empresa_id', $empresa->id)
            ->whereBetween('metric_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('
                COALESCE(SUM(users), 0)            AS total_users,
                COALESCE(SUM(sessions), 0)         AS total_sessions,
                COALESCE(SUM(conversions), 0)      AS total_conversions,
                COALESCE(SUM(organic_sessions), 0) AS total_organic_sessions
            ')
            ->first();

        if ($agg) {
            $summary->ga4Users           = (int) $agg->total_users;
            $summary->ga4Sessions        = (int) $agg->total_sessions;
            $summary->ga4Conversions     = (int) $agg->total_conversions;
            $summary->ga4OrganicSessions = (int) $agg->total_organic_sessions;
        }
    }
}
