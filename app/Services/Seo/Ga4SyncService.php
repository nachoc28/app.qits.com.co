<?php

namespace App\Services\Seo;

use App\Exceptions\Seo\SeoPropertyNotConfiguredException;
use App\Models\Empresa;
use App\Models\EmpresaSeoProperty;
use App\Models\SeoGa4DailyMetric;
use App\Models\SeoGa4LandingPage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Orquesta la sincronización GA4 para una empresa y periodo determinado.
 *
 * Flujo:
 *  1) Resuelve configuración SEO de empresa.
 *  2) Consulta Google Analytics Data API vía Ga4ClientService.
 *  3) Persiste en tablas locales con estrategia idempotente:
 *     - daily: upsert por (empresa_id, metric_date)
 *     - landing pages: replace por rango (delete + insert)
 *  4) Marca last_ga4_sync_at en empresa_seo_properties.
 */
class Ga4SyncService
{
    /** @var Ga4ClientService */
    private $client;

    public function __construct(Ga4ClientService $client)
    {
        $this->client = $client;
    }

    /**
     * Ejecuta sincronización GA4 para una empresa en el rango de fechas indicado.
     *
     * @throws SeoPropertyNotConfiguredException
     */
    public function syncEmpresa(Empresa $empresa, Carbon $from, Carbon $to): Ga4SyncResult
    {
        $property = $empresa->seoProperty;

        if (! $property instanceof EmpresaSeoProperty || ! $property->isGa4Ready()) {
            throw new SeoPropertyNotConfiguredException($empresa);
        }

        $context = new SeoPropertyContext($empresa, $property);

        $landingLimit = (int) config('seo.sync.ga4_landing_pages_limit', 1000);

        $dailyRows = $this->client->fetchDailyMetrics($context, $from, $to);
        $landingRows = $this->client->fetchLandingPages($context, $from, $to, $landingLimit);

        $fromDate = $from->toDateString();
        $toDate   = $to->toDateString();

        DB::transaction(function () use ($empresa, $property, $fromDate, $toDate, $dailyRows, $landingRows): void {
            $dailyCount = $this->persistDailyMetrics($empresa->id, $dailyRows);
            $this->replaceLandingPageMetrics($empresa->id, $fromDate, $toDate, $landingRows);

            $property->markGa4Synced();

            unset($dailyCount);
        });

        return new Ga4SyncResult(count($dailyRows), count($landingRows), true);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function persistDailyMetrics(int $empresaId, array $rows): int
    {
        if ($rows === []) {
            return 0;
        }

        $now = now();
        $payload = [];

        foreach ($rows as $row) {
            if (! isset($row['date']) || $row['date'] === '') {
                continue;
            }

            $payload[] = [
                'empresa_id'        => $empresaId,
                'metric_date'       => (string) $row['date'],
                'users'             => (int) ($row['users'] ?? 0),
                'sessions'          => (int) ($row['sessions'] ?? 0),
                'engaged_sessions'  => isset($row['engaged_sessions']) ? (int) $row['engaged_sessions'] : null,
                'conversions'       => isset($row['conversions']) ? (int) $row['conversions'] : null,
                'organic_sessions'  => isset($row['organic_sessions']) ? (int) $row['organic_sessions'] : null,
                'created_at'        => $now,
                'updated_at'        => $now,
            ];
        }

        if ($payload === []) {
            return 0;
        }

        SeoGa4DailyMetric::upsert(
            $payload,
            ['empresa_id', 'metric_date'],
            ['users', 'sessions', 'engaged_sessions', 'conversions', 'organic_sessions', 'updated_at']
        );

        return count($payload);
    }

    /**
     * Estrategia replace para landing pages: elimina el rango y reinserta snapshot.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function replaceLandingPageMetrics(int $empresaId, string $fromDate, string $toDate, array $rows): void
    {
        SeoGa4LandingPage::where('empresa_id', $empresaId)
            ->whereBetween('metric_date', [$fromDate, $toDate])
            ->delete();

        if ($rows === []) {
            return;
        }

        $now = now();
        $payload = [];

        foreach ($rows as $row) {
            if (! isset($row['date']) || ! isset($row['landing_page']) || $row['landing_page'] === '') {
                continue;
            }

            $payload[] = [
                'empresa_id'        => $empresaId,
                'metric_date'       => (string) $row['date'],
                'landing_page'      => substr((string) $row['landing_page'], 0, 255),
                'users'             => isset($row['users']) ? (int) $row['users'] : null,
                'sessions'          => isset($row['sessions']) ? (int) $row['sessions'] : null,
                'conversions'       => isset($row['conversions']) ? (int) $row['conversions'] : null,
                'engagement_rate'   => isset($row['engagement_rate']) ? (float) $row['engagement_rate'] : null,
                'created_at'        => $now,
                'updated_at'        => $now,
            ];
        }

        if ($payload === []) {
            return;
        }

        foreach (array_chunk($payload, 1000) as $chunk) {
            SeoGa4LandingPage::insert($chunk);
        }
    }
}
