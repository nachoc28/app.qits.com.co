<?php

namespace App\Services\Seo;

use App\Exceptions\Seo\SeoPropertyNotConfiguredException;
use App\Models\Empresa;
use App\Models\EmpresaSeoProperty;
use App\Models\SeoGscDailyMetric;
use App\Models\SeoGscPage;
use App\Models\SeoGscQuery;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Orquesta la sincronización GSC para una empresa y periodo determinado.
 *
 * Flujo:
 *  1) Resuelve la configuración SEO de empresa.
 *  2) Consulta Search Console API vía SearchConsoleClientService.
 *  3) Persiste en tablas locales con estrategia idempotente:
 *     - daily: upsert por (empresa_id, metric_date)
 *     - queries/pages: replace por rango (delete + insert)
 *  4) Marca last_gsc_sync_at en empresa_seo_properties.
 */
class SearchConsoleSyncService
{
    /** @var SearchConsoleClientService */
    private $client;

    public function __construct(SearchConsoleClientService $client)
    {
        $this->client = $client;
    }

    /**
     * Ejecuta sincronización GSC para una empresa en el rango de fechas indicado.
     *
     * @throws SeoPropertyNotConfiguredException
     */
    public function syncEmpresa(Empresa $empresa, Carbon $from, Carbon $to): SearchConsoleSyncResult
    {
        $property = $empresa->seoProperty;

        if (! $property instanceof EmpresaSeoProperty || ! $property->isGscReady()) {
            throw new SeoPropertyNotConfiguredException($empresa);
        }

        $context = new SeoPropertyContext($empresa, $property);

        $queryLimit = (int) config('seo.sync.gsc_top_queries_limit', 1000);
        $pageLimit  = (int) config('seo.sync.gsc_top_pages_limit', 1000);

        $dailyRows = $this->client->fetchDailyMetrics($context, $from, $to);
        $queryRows = $this->client->fetchTopQueries($context, $from, $to, $queryLimit);
        $pageRows  = $this->client->fetchTopPages($context, $from, $to, $pageLimit);

        Log::info('[SEO][GSC][TEMP_DIAG] Sync request/response por empresa.', [
            'empresa_id' => $empresa->id,
            'property' => $context->gscProperty(),
            'startDate' => $from->toDateString(),
            'endDate' => $to->toDateString(),
            'daily_row_count' => count($dailyRows),
            'daily_first_3_row_keys' => array_slice(array_map(function (array $row): array {
                return [
                    $row['date'] ?? null,
                ];
            }, $dailyRows), 0, 3),
            'query_row_count' => count($queryRows),
            'query_first_3_row_keys' => array_slice(array_map(function (array $row): array {
                return [
                    $row['date'] ?? null,
                    $row['query'] ?? null,
                    $row['page'] ?? null,
                ];
            }, $queryRows), 0, 3),
            'page_row_count' => count($pageRows),
            'page_first_3_row_keys' => array_slice(array_map(function (array $row): array {
                return [
                    $row['date'] ?? null,
                    $row['page'] ?? null,
                ];
            }, $pageRows), 0, 3),
        ]);

        $fromDate = $from->toDateString();
        $toDate   = $to->toDateString();

        DB::transaction(function () use ($empresa, $property, $fromDate, $toDate, $dailyRows, $queryRows, $pageRows): void {
            $dailyCount = $this->persistDailyMetrics($empresa->id, $dailyRows);
            $this->replaceQueryMetrics($empresa->id, $fromDate, $toDate, $queryRows);
            $this->replacePageMetrics($empresa->id, $fromDate, $toDate, $pageRows);

            // Marca sincronización exitosa al final de la transacción.
            $property->markGscSynced();

            // $dailyCount se conserva para claridad del flujo (no-op funcional).
            unset($dailyCount);
        });

        return new SearchConsoleSyncResult(
            count($dailyRows),
            count($queryRows),
            count($pageRows),
            true
        );
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
                'empresa_id'    => $empresaId,
                'metric_date'   => (string) $row['date'],
                'clicks'        => (int) ($row['clicks'] ?? 0),
                'impressions'   => (int) ($row['impressions'] ?? 0),
                'ctr'           => (float) ($row['ctr'] ?? 0),
                'avg_position'  => (float) ($row['avg_position'] ?? 0),
                'created_at'    => $now,
                'updated_at'    => $now,
            ];
        }

        if ($payload === []) {
            return 0;
        }

        SeoGscDailyMetric::upsert(
            $payload,
            ['empresa_id', 'metric_date'],
            ['clicks', 'impressions', 'ctr', 'avg_position', 'updated_at']
        );

        return count($payload);
    }

    /**
     * Estrategia replace para queries: elimina el rango y reinserta snapshot actual.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function replaceQueryMetrics(int $empresaId, string $fromDate, string $toDate, array $rows): void
    {
        SeoGscQuery::where('empresa_id', $empresaId)
            ->whereBetween('metric_date', [$fromDate, $toDate])
            ->delete();

        if ($rows === []) {
            return;
        }

        $now = now();
        $payload = [];

        foreach ($rows as $row) {
            if (! isset($row['date']) || ! isset($row['query']) || $row['query'] === '') {
                continue;
            }

            $payload[] = [
                'empresa_id'    => $empresaId,
                'metric_date'   => (string) $row['date'],
                'query'         => substr((string) $row['query'], 0, 191),
                'page_url'      => isset($row['page']) && $row['page'] !== null
                                    ? substr((string) $row['page'], 0, 500)
                                    : null,
                'clicks'        => (int) ($row['clicks'] ?? 0),
                'impressions'   => (int) ($row['impressions'] ?? 0),
                'ctr'           => (float) ($row['ctr'] ?? 0),
                'avg_position'  => (float) ($row['avg_position'] ?? 0),
                'created_at'    => $now,
                'updated_at'    => $now,
            ];
        }

        if ($payload === []) {
            return;
        }

        foreach (array_chunk($payload, 1000) as $chunk) {
            SeoGscQuery::insert($chunk);
        }
    }

    /**
     * Estrategia replace para páginas: elimina el rango y reinserta snapshot actual.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function replacePageMetrics(int $empresaId, string $fromDate, string $toDate, array $rows): void
    {
        SeoGscPage::where('empresa_id', $empresaId)
            ->whereBetween('metric_date', [$fromDate, $toDate])
            ->delete();

        if ($rows === []) {
            return;
        }

        $now = now();
        $payload = [];

        foreach ($rows as $row) {
            if (! isset($row['date']) || ! isset($row['page']) || $row['page'] === '') {
                continue;
            }

            $payload[] = [
                'empresa_id'    => $empresaId,
                'metric_date'   => (string) $row['date'],
                'page_url'      => substr((string) $row['page'], 0, 255),
                'clicks'        => (int) ($row['clicks'] ?? 0),
                'impressions'   => (int) ($row['impressions'] ?? 0),
                'ctr'           => (float) ($row['ctr'] ?? 0),
                'avg_position'  => (float) ($row['avg_position'] ?? 0),
                'created_at'    => $now,
                'updated_at'    => $now,
            ];
        }

        if ($payload === []) {
            return;
        }

        foreach (array_chunk($payload, 1000) as $chunk) {
            SeoGscPage::insert($chunk);
        }
    }
}
